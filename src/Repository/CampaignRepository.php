<?php

namespace Drupal\mailchimpbridge\Repository;

use Drupal\Core\Database\Connection;
use Drupal\mailchimpbridge\Entity\Campaign;
use Exception;

class CampaignRepository
{
  const TABLE_NAME = 'mailchimpbridge_campaigns';

  /**
   * @var Connection
   */
  private $database;

  public function __construct(Connection $database)
  {
    $this->database = $database;
  }

  public function findByIDs(array $campaignsIDs): array
  {
    $query = $this->database->select(self::TABLE_NAME, 'c');
    $query->fields('c');
    $query->condition('id', $campaignsIDs, 'IN');
    $result = $query->execute();

    return $result->fetchAllAssoc('id');
  }

  /**
   * @throws Exception
   */
  public function add(Campaign $campaign): void
  {
    $this->database->insert(self::TABLE_NAME)
      ->fields([
        'id' => $campaign->getId(),
        'web_id' => $campaign->getWebId(),
        'node_id' => $campaign->getNodeId(),
        'subject' => $campaign->getSubject(),
        'email' => $campaign->getEmail(),
        'created_at' => $campaign->getCreatedAt(),
      ])
      ->execute();
  }

  public function deleteByNodeId(int $id): void
  {
    $this->database->delete(self::TABLE_NAME)
      ->condition('node_id', $id)
      ->execute();
  }
}
