<?php

namespace Drupal\mailchimpbridge\Form\Campaign;

use Drupal\mailchimpbridge\Repository\CampaignRepository;
use Drupal\mailchimpbridge\Service\MailChimpService;
use Drupal\Core\Render\Element;
use Drupal\node\Entity\Node;
use Drupal\mailchimpbridge\Service\MailChimpClientException;

class Table
{
  /**
   * @var MailChimpService
   */
  private $mailChimpService;
  /**
   * @var CampaignRepository
   */
  private $campaignRepository;

  /**
   * @var Row[]
   */
  private $pageRows;
  /**
   * @var array
   */
  private $pageOptionsRows;

  public function __construct(MailChimpService $mailChimpService, CampaignRepository $campaignRepository)
  {
    $this->mailChimpService = $mailChimpService;
    $this->campaignRepository = $campaignRepository;

    $this->pageRows = [];
    $this->pageOptionsRows = [];
  }

  /**
   * @return Row[]
   */
  public function getPageRows(): array
  {
    return $this->pageRows;
  }

  public function getPageOptionsRows(): array
  {
    return $this->pageOptionsRows;
  }

  /**
   * @throws MailChimpClientException
   */
  public function fillCurrentPage(int $itemsPerPage, int $offset)
  {
    $campaigns = $this->mailChimpService->campaignList($itemsPerPage, $offset);

    $currentPageCampaignsIDs = array_map(function($campaign) {
      return $campaign->getId();
    }, $campaigns);

    $dbCampaigns = $this->campaignRepository->findByIDs($currentPageCampaignsIDs);

    $rowNum = 0;
    foreach($campaigns as $campaign) {
      $row = null;
      if (array_key_exists($campaign->getId(), $dbCampaigns)) {
        $node = Node::load($dbCampaigns[$campaign->getId()]->node_id);
        $campaign->setNodeId($node->id());

        $row = new ImportedRow($campaign, $rowNum, $this->mailChimpService->getServer(), $node);
      } else {
        $row = new NotImportedRow($campaign, $rowNum, $this->mailChimpService->getServer());
      }

      $this->pageRows[] = $row;
      $this->pageOptionsRows[] = $row->toArray();
      $rowNum++;
    }
  }

  public static function processDisabledRows(array &$element): array {
    foreach (Element::children($element) as $key) {
      $element[$key]['#disabled'] = $element['#options'][$key]['#disabled'] ?? FALSE;
    }
    return $element;
  }
}
