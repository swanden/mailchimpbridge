<?php

namespace Drupal\mailchimpbridge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Tableselect;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use MailchimpMarketing\ApiClient;


class CampaignsAdminForm extends FormBase {

  const ACTION_TYPE_IMPORT = '0';
  const ACTION_TYPE_REMOVE = '1';

  const TABLE_NAME = 'mailchimpbridge_campaigns';

  private $client;
  private $database;

  private $currentPageRows = [];
  private $campaigns = [];

  public function __construct()
  {
    $this->client = new ApiClient();
    $this->database = \Drupal::database();
  }

  public function getFormId() {
    return 'campaigns_admin_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('mailchimpbridge.settings');

    $server = $config->get('mailchimpbridge.server');
    $apiKey = $config->get('mailchimpbridge.api_key');

    if (empty($apiKey) || empty($server)) {
      $this->messenger()->addError($this->t('Mailchimp API key or server is empty. Go to the module settings to set API key and server.'));
      return null;
    }

    $this->client->setConfig([
      'apiKey' => $apiKey,
      'server' => $server
    ]);

    try {
      $total = $this->client->campaigns->list()->total_items;
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t($e->getMessage()));
      return null;
    }

    $num_per_page = 20;
    $pager = \Drupal::service('pager.manager')->createPager($total, $num_per_page);
    $page = $pager->getCurrentPage();
    $offset = $num_per_page * $page;

    $campaigns = [];
    try {
      $campaigns = $this->client->campaigns->list(null, null, 20, $offset, null, null, null, null, null, null, null, null, null, 'create_time', 'DESC')->campaigns;
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t($e->getMessage()));
      return null;
    }

    $currentPageCampaignsIDs = array_map(function($campaign) {
      return $campaign->id;
    }, $campaigns);

    $query = $this->database->select(self::TABLE_NAME, 'c');
    $query->fields('c');
    $query->condition('id', $currentPageCampaignsIDs, 'IN');
    $result = $query->execute();
    $dbCampaigns = $result->fetchAllAssoc('id');

    $rowNum = 0;
    foreach($campaigns as $campaign) {
      $nodeName = null;
      $isImported = false;
      $nodeId = null;
      $nodeCreatedAt = '';
      $nodeType = null;
      $user = null;
      $isPublished = null;
      $nodeAlias = null;
      if (array_key_exists($campaign->id, $dbCampaigns)) {
        $dbCampaign = $dbCampaigns[$campaign->id];
        $node = Node::load($dbCampaign->node_id);
        $nodeName = [
          'value' => $node->getTitle(),
          'data' => [
            '#type' => 'item',
            '#markup' => "<a target='_blank' href='/node/{$node->id()}/edit?destination=/admin/content'>{$node->getTitle()}</a>",
          ]
        ];


        $isImported = true;
        $nodeId = $node->id();
        $nodeCreatedAt = $node->getCreatedTime();
        $nodeType = NodeType::load($node->getType())->label();
        $user = $node->getOwner()->label();
        $isPublished = $node->isPublished() ? 'Published' : 'Draft';
        $nodeAlias = [
          'value' => $node->url(),
          'data' => [
            '#type' => 'item',
            '#markup' => "<a target='_blank' href='{$node->url()}'>{$node->url()}</a>",
          ]
        ];
      } else {
        $nodeName = [
          'data' => [
            '#type' => 'textfield',
            '#value' => $campaign->settings->subject_line,
            '#name' => "campaign_title[$rowNum]"
          ]
        ];

        $nodeTypes = NodeType::loadMultiple();
        $nodeTypeOptions = [];
        foreach ($nodeTypes as $nodeType) {
          $nodeTypeOptions[$nodeType->id()] = t($nodeType->label());
        }
        $nodeType = [
          'data' => [
            '#type' => 'select',
            '#options' => $nodeTypeOptions,
            '#name' => "node_type[$rowNum]"
          ]
        ];

        $campaignEmail = $campaign->settings->reply_to;
        $selectedUser = user_load_by_mail($campaignEmail);
        $users = User::loadMultiple();
        $usersOptions = [];
        foreach ($users as $user) {
          $usersOptions[$user->id()] = t($user->label());
        }
        $user = [
          'data' => [
            '#type' => 'select',
            '#options' => $usersOptions,
            '#name' => "user[$rowNum]",
            '#value' => $selectedUser ? $selectedUser->id() : \Drupal::currentUser()->id(),
          ]
        ];

        $isPublished = [
          'data' => [
            '#type' => 'select',
            '#options' => [
              0 => 'Draft',
              1 => 'Published'
            ],
            '#name' => "published[$rowNum]",
          ]
        ];

        $nodeAlias = [
          'data' => [
            '#type' => 'textfield',
            '#value' => '',
            '#name' => "node_alias[$rowNum]",
            '#attributes' => ['size' => '20']
          ]
        ];
      }

      $this->currentPageRows[] = [
        'isImported' => $isImported,
        'node_id' => $nodeId,
        'campaign_id' => $campaign->id,
        'campaign_web_id' => $campaign->web_id,
        'node_name' => $nodeName,
        'campaign_subject' => [
          'value' => $campaign->settings->subject_line,
          'data' => [
            '#type' => 'item',
            '#markup' => "<a target='_blank' href='https://$server.admin.mailchimp.com/campaigns/edit?id={$campaign->web_id}'>{$campaign->settings->subject_line}</a>",
          ]
        ],
        'campaign_created_at' => [
          'value' => $campaign->create_time,
          'data' => [
            '#type' => 'item',
            '#markup' => date('d.m.Y H:i:s', strtotime($campaign->create_time))
          ]
        ],
        'node_created_at' => date('d.m.Y H:i:s', $nodeCreatedAt),
        'node_type' => $nodeType,
        'user' => $user,
        'is_published' => $isPublished,
        'node_alias' => $nodeAlias,
      ];
      $rowNum++;
    }

    $header = [
      'node_name' => t('Node name'),
      'campaign_subject' => t('Campaign subject'),
      'campaign_created_at' => t('Campaign creation date'),
      'node_type' => t('Node type'),
      'user' => t('Node user'),
      'is_published' => t('Node status'),
      'node_alias' => t('Node alias'),
    ];

    $form['actions']['action_type'] = array(
      '#type' => 'select',
      '#title' => t('Action'),
      '#options' => array(
        self::ACTION_TYPE_IMPORT => t('Import campaign'),
        self::ACTION_TYPE_REMOVE => t('Delete node'),
      ),
    );

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
      '#attributes' => ['style' => 'margin: 0 0 0.75em 0;']
    ];

    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $this->currentPageRows,
      '#empty' => t('No items found'),
      '#process' => [
        // This is the original #process callback.
        [Tableselect::class, 'processTableselect'],
        // Additional #process callback.
        [static::class, 'processDisabledRows'],
      ],
    ];

    $form['submit_down'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
      '#attributes' => ['style' => 'margin: 0;']
    ];

    $form['pager'] = array(
      '#type' => 'pager'
    );
    $form['campaign_title'] = array(
      '#type' => 'value',
    );
    $form['node_type'] = array(
      '#type' => 'value',
    );
    $form['user'] = array(
      '#type' => 'value',
    );
    $form['published'] = array(
      '#type' => 'value',
    );
    $form['node_alias'] = array(
      '#type' => 'value',
    );

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $campaignTitles = $form_state->getValue('campaign_title');

    foreach($campaignTitles as $rowNum => $title) {
      if (strlen(trim($title)) < 1) {
        $row = $this->currentPageRows[$rowNum];
        $this->messenger()->addError($this->t('Node name of campaign "@campaignSubject" is empty', ['@campaignSubject' => $row['campaign_subject']['value']]));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $campaignTitles = $form_state->getValue('campaign_title');
    $nodeTypes = $form_state->getValue('node_type');
    $users = $form_state->getValue('user');
    $published = $form_state->getValue('published');
    $nodeAliases = $form_state->getValue('node_alias');

    foreach($form_state->getValue('table') as $rowNum) {
      if ($rowNum === 0) {
        continue;
      }

      if ($form_state->getValue('action_type') === self::ACTION_TYPE_IMPORT) {
        $this->importCampaign($rowNum, $campaignTitles, $nodeTypes, $users, $published, $nodeAliases);
        continue;
      }

      $this->deleteNode($rowNum);
    }
  }

  private function importCampaign(int $rowNum, array $campaignTitles, array $nodeTypes, array $users, array $published, array $nodeAliases): void
  {
    $row = $this->currentPageRows[$rowNum];

    if ($row['isImported']) {
      $this->messenger()->addError($this->t('Campaign "@campaignSubject" has already been imported', ['@campaignSubject' => $row['campaign_subject']['value']]));
      return;
    }

    if (empty(trim($campaignTitles[$rowNum]))) {
      $this->messenger()->addError($this->t('Node name of campaign "@campaignSubject" is empty', ['@campaignSubject' => $row['campaign_subject']['value']]));
      return;
    }

    $html = $this->client->campaigns->getContent($row['campaign_id'])->html;
    preg_match('/<body[^>]*>([\w|\W]*)<\/body>/', $html, $bodyMatches);
    preg_match('/<style[^>]*>([\w|\W]*)<\/style>/', $html, $styleMatches);

    $body = $styleMatches[0] . "\n" . $bodyMatches[1];

    $node = Node::create([
      'type' => $nodeTypes[$rowNum],
      'title' => $campaignTitles[$rowNum],
      'created' => strtotime($row['campaign_created_at']['value']),
      'uid' => $users[$rowNum],
      'path' => !empty(trim($nodeAliases[$rowNum])) ? $nodeAliases[$rowNum] : '',
      'body' => [
        'value' => $body,
        'format' => 'full_html'
      ]
    ]);
    $node->setPublished((bool) $published[$rowNum]);
    $published[$rowNum] ? $node->set('moderation_state' , 'published') : $node->set('moderation_state' , 'draft');

    $node->save();

    $this->database->insert(self::TABLE_NAME)
      ->fields([
        'id' => $row['campaign_id'],
        'web_id' => $row['campaign_web_id'],
        'node_id' => $node->id(),
        'title' => $row['campaign_subject']['value'],
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Campaign "@campaignSubject" has been successfully imported', ['@campaignSubject' => $campaignTitles[$rowNum]]));
  }

  private function deleteNode(int $rowNum): void
  {
    $row = $this->currentPageRows[$rowNum];
    $node = Node::load($row['node_id']);

    if (!$row['isImported'] || !$node) {
      $this->messenger()->addError($this->t('Node "@nodeName" does not exists', ['@nodeName' => $row['campaign_subject']['value']]));
      return;
    }

    $node->delete();

    $this->database->delete(self::TABLE_NAME)
      ->condition('node_id', $row['node_id'])
      ->execute();

    $this->messenger()->addStatus($this->t('Node "@nodeName" deleted', ['@nodeName' => $row['node_name']['value']]));
  }

  private function getCampaignById(string $id): ?array
  {
    $campaigns = array_filter($this->campaigns, function ($campaign) use($id) {
      return $campaign['id'] === $id;
    });

    if (count($campaigns) > 0) {
      return $campaigns[0];
    }

    return null;
  }

  public static function processDisabledRows(array &$element): array {
    foreach (Element::children($element) as $key) {
      $element[$key]['#disabled'] = isset($element['#options'][$key]['#disabled']) ? $element['#options'][$key]['#disabled'] : FALSE;
    }
    return $element;
  }
}
