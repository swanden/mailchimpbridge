<?php

namespace Drupal\mailchimpbridge\Form\Campaign;

use Drupal\mailchimpbridge\Service\MailChimpClientException;
use Drupal\mailchimpbridge\Service\MailChimpService;
use Drupal\mailchimpbridge\UseCase\UseCaseService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Tableselect;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManager;


class AdminForm extends FormBase {

  const ITEMS_PER_PAGE = 20;

  const ACTION_TYPE_IMPORT = '0';
  const ACTION_TYPE_REMOVE = '1';

  /**
   * @var MailChimpService
   */
  private $mailChimpService;
  /**
   * @var UseCaseService
   */
  private $useCaseService;
  /**
   * @var PagerManager
   */
  private $pagerManager;

  /**
   * @var ?string
   */
  private $server;
  /**
   * @var ?string
   */
  private $apiKey;
  /**
   * @var Table
   */
  private $table;

  public function __construct()
  {
    $config = $this->config('mailchimpbridge.settings');

    $this->server = $config->get('mailchimpbridge.server');
    $this->apiKey = $config->get('mailchimpbridge.api_key');
  }

  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->pagerManager = $container->get('pager.manager');
    $instance->mailChimpService = $container->get('mailchimpbridge.mailchimpservice');
    $instance->table = $container->get('mailchimpbridge.table');
    $instance->useCaseService = $container->get('mailchimpbridge.usecaseservice');

    return $instance;
  }

  public function getFormId(): string {
    return 'campaigns_admin_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): ?array {
    if (empty($this->apiKey) || empty($this->server)) {
      $this->messenger()->addError($this->t('Mailchimp API key or server is empty. Go to the module settings to set API key and server.'));
      return null;
    }

    try {
      $total = $this->mailChimpService->totalCampaignItems();
    } catch (MailChimpClientException $e) {
      $this->messenger()->addError($this->t($e->getMessage()));
      return null;
    }

    $pager = $this->pagerManager->createPager($total, self::ITEMS_PER_PAGE);
    $page = $pager->getCurrentPage();
    $offset = self::ITEMS_PER_PAGE * $page;

    try {
      $this->table->fillCurrentPage(self::ITEMS_PER_PAGE, $offset);
    } catch (MailChimpClientException $e) {
      $this->messenger()->addError($this->t($e->getMessage()));
      return null;
    }


    $form['actions']['action_type'] = array(
      '#type' => 'select',
      '#title' => t('Action'),
      '#options' => [
        self::ACTION_TYPE_IMPORT => t('Import campaign'),
        self::ACTION_TYPE_REMOVE => t('Delete node'),
      ],
    );

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
      '#attributes' => ['style' => 'margin: 0 0 0.75em 0;']
    ];

    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => Row::getTableHeader(),
      '#options' => $this->table->getPageOptionsRows(),
      '#empty' => t('No items found'),
      '#process' => [
        // This is the original #process callback.
        [Tableselect::class, 'processTableselect'],
        // Additional #process callback.
        [Table::class, 'processDisabledRows'],
      ],
    ];

    $form['submit_down'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
      '#attributes' => ['style' => 'margin: 0;']
    ];

    $form['pager'] = [
      '#type' => 'pager'
    ];

    $form[Row::CAMPAIGN_TITLE_ARRAY] = [
      '#type' => 'value',
    ];
    $form[Row::NODE_TYPE_ARRAY] = [
      '#type' => 'value',
    ];
    $form[Row::AUTHOR_ARRAY] = [
      '#type' => 'value',
    ];
    $form[Row::PUBLISHED_ARRAY] = [
      '#type' => 'value',
    ];
    $form[Row::NODE_PATH_ARRAY] = [
      '#type' => 'value',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $campaignTitles = $form_state->getValue(Row::CAMPAIGN_TITLE_ARRAY);

    foreach($campaignTitles as $rowNum => $title) {
      if (strlen(trim($title)) < 1) {
        $row = $this->table->getPageRows()[$rowNum];
        $this->messenger()->addError($this->t('Node name of campaign "@campaignSubject" is empty', ['@campaignSubject' => $row->getCampaignSubject()]));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $formValues = [
      Row::CAMPAIGN_TITLE_ARRAY => $form_state->getValue(Row::CAMPAIGN_TITLE_ARRAY),
      Row::NODE_TYPE_ARRAY => $form_state->getValue(Row::NODE_TYPE_ARRAY),
      Row::AUTHOR_ARRAY => $form_state->getValue(Row::AUTHOR_ARRAY),
      Row::PUBLISHED_ARRAY => $form_state->getValue(Row::PUBLISHED_ARRAY),
      Row::NODE_PATH_ARRAY => $form_state->getValue(Row::NODE_PATH_ARRAY),
    ];

    foreach($form_state->getValue('table') as $rowNum) {
      if ($rowNum === 0) {
        continue;
      }

      $row = $this->table->getPageRows()[$rowNum];
      if ($form_state->getValue('action_type') === self::ACTION_TYPE_IMPORT) {
        if ($row->isImported()) {
          $this->messenger()->addError($this->t('Campaign "@campaignSubject" has already been imported', ['@campaignSubject' => $row->getCampaignSubject()]));
          continue;
        }

        if (empty(trim($formValues[Row::CAMPAIGN_TITLE_ARRAY][$row->getNum()]))) {
          $this->messenger()->addError($this->t('Node name of campaign "@campaignSubject" is empty', ['@campaignSubject' => $row->getCampaignSubject()]));
          continue;
        }

        try {
          $this->useCaseService->importCampaign($row, $formValues);
        } catch (Exception $e) {
          $this->messenger()->addError($this->t($e->getMessage()));
          continue;
        }

        $this->messenger()->addStatus($this->t('Campaign "@campaignSubject" has been successfully imported', ['@campaignSubject' => $formValues[Row::CAMPAIGN_TITLE_ARRAY][$row->getNum()]]));
        continue;
      }

      if (!$row->isImported() || is_null($row->getNode())) {
        $this->messenger()->addError($this->t('Node "@nodeName" does not exists', ['@nodeName' => $row->getCampaignSubject()]));
        continue;
      }

      try {
        $this->useCaseService->deleteNode($row);
      } catch (Exception $e) {
        $this->messenger()->addError($this->t($e->getMessage()));
        continue;
      }
      $this->messenger()->addStatus($this->t('Node "@nodeName" deleted', ['@nodeName' => $row->getNodeTitle()]));
    }
  }
}
