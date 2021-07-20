<?php

namespace Drupal\mailchimpbridge\Entity;

final class Campaign
{
  const DATE_FORMAT = 'd.m.Y H:i:s';

  /**
   * @var string
   */
  private $id;
  /**
   * @var int
   */
  private $web_id;
  /**
   * @var ?int
   */
  private $node_id;
  /**
   * @var string
   */
  private $subject;
  /**
   * @var string
   */
  private $email;
  /**
   * @var int
   */
  private $created_at;

  public function __construct(string $id, int $web_id, ?int $node_id, string $subject, string $email, int $created_at)
  {
    $this->id = $id;
    $this->web_id = $web_id;
    $this->node_id = $node_id;
    $this->subject = $subject;
    $this->email = $email;
    $this->created_at = $created_at;
  }

  public function getId(): string
  {
    return $this->id;
  }

  public function getWebId(): int
  {
    return $this->web_id;
  }

  public function getNodeId(): ?int
  {
    return $this->node_id;
  }

  public function setNodeId(?int $node_id): void
  {
    $this->node_id = $node_id;
  }

  public function getSubject(): string
  {
    return $this->subject;
  }

  public function getEmail(): string
  {
    return $this->email;
  }

  public function getCreatedAt(): int
  {
    return $this->created_at;
  }

  public function getCreatedAtFormatted(): string
  {
    return date(self::DATE_FORMAT, $this->created_at);
  }
}
