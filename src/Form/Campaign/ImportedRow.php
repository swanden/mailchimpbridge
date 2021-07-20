<?php

namespace Drupal\mailchimpbridge\Form\Campaign;

use Drupal\node\Entity\NodeType;

final class ImportedRow extends Row
{
  public function getNodeId(): ?int
  {
    return $this->node->id();
  }

  public function getNodeTitle(): ?string
  {
    return $this->node->getTitle();
  }

  protected function getNodeTitleCol(): array
  {
    return [
      'data' => [
        '#type' => 'item',
        '#markup' => "<a target='_blank' href='/node/{$this->node->id()}/edit?destination=/admin/content'>{$this->node->getTitle()}</a>",
      ]
    ];
  }

  protected function getNodeTypeCol(): string
  {
    return NodeType::load($this->node->getType())->label();
  }

  protected function getAuthorCol(): string
  {
    return $this->node->getOwner()->label();
  }

  protected function getNodeStatusCol(): string
  {
    return $this->node->isPublished() ? self::NODE_STATUS_PUBLISHED : self::NODE_STATUS_DRAFT;
  }

  protected function getNodePathCol(): array
  {
    return [
      'data' => [
        '#type' => 'item',
        '#markup' => "<a target='_blank' href='{$this->node->url()}'>{$this->node->url()}</a>",
      ]
    ];
  }
}
