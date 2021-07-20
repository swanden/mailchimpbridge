<?php

namespace Drupal\mailchimpbridge\Service;

use Drupal\mailchimpbridge\Entity\Campaign;
use Exception;
use MailchimpMarketing\ApiClient;
use Drupal\Core\Config\ConfigFactory;

final class MailChimpService
{
  /**
   * @var ApiClient
   */
  private $client;
  /**
   * @var string
   */
  private $server;

  public function __construct(ConfigFactory $configFactory, ApiClient $client)
  {
    $config = $configFactory->get('mailchimpbridge.settings');
    $server = $config->get('mailchimpbridge.server');
    $apiKey = $config->get('mailchimpbridge.api_key');

    $this->client = $client;

    $this->server = $server;
    $this->client->setConfig([
      'apiKey' => $apiKey,
      'server' => $server
    ]);
  }

  public function getServer(): string
  {
    return $this->server;
  }

  /**
   * @throws MailChimpClientException
   */
  public function totalCampaignItems(): int
  {
    try {
      $total = $this->client->campaigns->list()->total_items;
    } catch (Exception $e) {
      throw new MailChimpClientException($e->getMessage());
    }

    return $total;
  }

  /**
   * @return Campaign[]
   *
   * @throws MailChimpClientException
   */
  public function campaignList(int $itemsPerPage, int $offset): array
  {
    try {
      $mailChimpCampaigns = $this->client->campaigns->list(null, null, $itemsPerPage, $offset, null, null, null, null, null, null, null, null, null, 'create_time', 'DESC')->campaigns;
    } catch (Exception $e) {
      throw new MailChimpClientException($e->getMessage());
    }

    $campaigns = [];
    foreach($mailChimpCampaigns as $mailChimpCampaign) {
      $campaigns[] = new Campaign(
        $mailChimpCampaign->id,
        $mailChimpCampaign->web_id,
        null,
        $mailChimpCampaign->settings->subject_line,
        $mailChimpCampaign->settings->reply_to,
        strtotime($mailChimpCampaign->create_time)
      );
    }

    return $campaigns;
  }

  /**
   * @throws MailChimpClientException
   */
  public function campaignHTML(string $campaignId): string
  {
    try {
      $html = $this->client->campaigns->getContent($campaignId)->html;
    } catch (Exception $e) {
      throw new MailChimpClientException($e->getMessage());
    }

    return $html;
  }
}
