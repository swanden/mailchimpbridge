<?php

namespace Drupal\mailchimpbridge\UseCase;

use Drupal\mailchimpbridge\Entity\Campaign;
use Drupal\mailchimpbridge\Form\Campaign\Row;
use Drupal\mailchimpbridge\Repository\CampaignRepository;
use Drupal\mailchimpbridge\Service\MailChimpClientException;
use Drupal\mailchimpbridge\Service\MailChimpService;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Exception;

class UseCaseService
{
  /**
   * @var MailChimpService
   */
  private $mailChimpService;
  /**
   * @var CampaignRepository
   */
  private $campaignRepository;

  public function __construct(MailChimpService $mailChimpService, CampaignRepository $campaignRepository)
  {
    $this->mailChimpService = $mailChimpService;
    $this->campaignRepository = $campaignRepository;
  }

  /**
   * @throws MailChimpClientException
   * @throws EntityStorageException
   * @throws Exception
   */
  public function importCampaign(Row $row, array $formValues): void
  {
    $html = $this->mailChimpService->campaignHTML($row->getCampaignId());

    preg_match('/<body[^>]*>([\w|\W]*)<\/body>/', $html, $bodyMatches);
    preg_match('/<style[^>]*>([\w|\W]*)<\/style>/', $html, $styleMatches);
    $innerBody = $bodyMatches[1];
    $outerStyles = $styleMatches[0];
//    $divWrappedBody = str_replace(['<body', '</body>'], ['<div', '</div>'], $bodyMatches[0]);

    $body = $outerStyles . "\n" . $innerBody;

    $node = Node::create([
      'type' => $formValues[Row::NODE_TYPE_ARRAY][$row->getNum()],
      'title' => $formValues[Row::CAMPAIGN_TITLE_ARRAY][$row->getNum()],
      'created' => $row->getCampaignCreatedAt(),
      'uid' => $formValues[Row::AUTHOR_ARRAY][$row->getNum()],
      'path' => !empty(trim($formValues[Row::NODE_PATH_ARRAY][$row->getNum()])) ? $formValues[Row::NODE_PATH_ARRAY][$row->getNum()] : '',
      'body' => [
        'value' => $body,
        'format' => 'full_html'
      ]
    ]);
    $node->setPublished((bool) $formValues[Row::PUBLISHED_ARRAY][$row->getNum()]);
    $formValues[Row::PUBLISHED_ARRAY][$row->getNum()] ? $node->set('moderation_state' , 'published') : $node->set('moderation_state' , 'draft');

    $node->save();

    $this->campaignRepository->add(new Campaign(
      $row->getCampaignId(),
      $row->getCampaignWebId(),
      $node->id(),
      $row->getCampaignSubject(),
      $row->getCampaignEmail(),
      $row->getCampaignCreatedAt()
    ));
  }

  /**
   * @throws EntityStorageException
   */
  public function deleteNode(Row $row): void
  {
    $row->getNode()->delete();

    $this->campaignRepository->deleteByNodeId($row->getNodeId());
  }
}
