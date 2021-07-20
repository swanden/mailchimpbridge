<?php

namespace Drupal\mailchimpbridge\Form\Campaign;

use Drupal\mailchimpbridge\Entity\Campaign;
use Drupal\node\Entity\Node;

abstract class Row
{
  const NODE_STATUS_PUBLISHED = 'Published';
  const NODE_STATUS_DRAFT = 'Draft';

  const CAMPAIGN_TITLE_ARRAY = 'campaign_title';
  const NODE_TYPE_ARRAY = 'node_type';
  const AUTHOR_ARRAY = 'author';
  const PUBLISHED_ARRAY = 'published';
  const NODE_PATH_ARRAY = 'node_path';

  /**
   * @var ?Node
   */
  protected $node;
  /**
   * @var Campaign
   */
  protected $campaign;
  /**
   * @var int
   */
  protected $num;
  /**
   * @var string
   */
  protected $mailChimpServer;

  public function __construct(Campaign $campaign, int $num, string $mailChimpServer, ?Node $node = null)
  {
    $this->node = $node;
    $this->campaign = $campaign;
    $this->num = $num;
    $this->mailChimpServer = $mailChimpServer;
  }

  public static function getTableHeader(): array
  {
    return [
      'node_title' => t('Node title'),
      'campaign_subject' => t('Campaign subject'),
      'campaign_created_at' => t('Campaign creation date'),
      'node_type' => t('Node type'),
      'author' => t('Author'),
      'campaign_email' => t('Campaign email'),
      'is_published' => t('Node status'),
      'node_path' => t('Node path'),
    ];
  }

  public function toArray(): array
  {
    return [
      'node_title' => $this->getNodeTitleCol(),
      'campaign_subject' => $this->getCampaignSubjectCol(),
      'campaign_created_at' => $this->getCampaignCreatedAtCol(),
      'node_type' => $this->getNodeTypeCol(),
      'author' => $this->getAuthorCol(),
      'campaign_email' => $this->getCampaignEmail(),
      'is_published' => $this->getNodeStatusCol(),
      'node_path' => $this->getNodePathCol(),
    ];
  }

  public function getNode(): ?Node
  {
    return $this->node;
  }

  public function getNum(): int
  {
    return $this->num;
  }

  public function isImported(): bool
  {
    return get_class($this) === ImportedRow::class;
  }

  public function getCampaignId(): string
  {
    return $this->campaign->getId();
  }

  public function getCampaignWebId(): int
  {
    return $this->campaign->getWebId();
  }

  public function getCampaignSubject(): string
  {
    return $this->campaign->getSubject();
  }

  protected function getCampaignSubjectCol(): array
  {
    return [
      'data' => [
        '#type' => 'item',
        '#markup' => "<a target='_blank' href='https://{$this->mailChimpServer}.admin.mailchimp.com/campaigns/edit?id={$this->campaign->getWebId()}'>{$this->campaign->getSubject()}</a>",
      ]
    ];
  }

  public function getCampaignCreatedAt(): string
  {
    return $this->campaign->getCreatedAt();
  }

  protected function getCampaignCreatedAtCol(): array
  {
    return [
      'data' => [
        '#type' => 'item',
        '#markup' => $this->campaign->getCreatedAtFormatted()
      ]
    ];
  }

  public function getCampaignEmail(): string
  {
    return $this->campaign->getEmail();
  }

  abstract function getNodeId(): ?int;

  abstract function getNodeTitle(): ?string;

  abstract protected function getNodeTitleCol(): array;

  /**
   * @return array|string
   */
  abstract protected function getNodeTypeCol();

  /**
   * @return array|string
   */
  abstract protected function getAuthorCol();

  /**
   * @return array|string
   */
  abstract protected function getNodeStatusCol();

  abstract protected function getNodePathCol(): array;
}
