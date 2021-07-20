<?php

namespace Drupal\mailchimpbridge\Form\Campaign;

use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal;

final class NotImportedRow extends Row
{
  public function getNodeId(): ?int
  {
    return null;
  }

  public function getNodeTitle(): ?string
  {
    return null;
  }

  protected function getNodeTitleCol(): array
  {
    return [
      'data' => [
        '#type' => 'textfield',
        '#value' => $this->campaign->getSubject(),
        '#name' => self::CAMPAIGN_TITLE_ARRAY . "[{$this->getNum()}]",
        '#attributes' => ['size' => '40']
      ]
    ];
  }

  protected function getNodeTypeCol(): array
  {
    $nodeTypes = NodeType::loadMultiple();
    $nodeTypeOptions = [];
    foreach ($nodeTypes as $nodeType) {
      $nodeTypeOptions[$nodeType->id()] = t($nodeType->label());
    }

    return [
      'data' => [
        '#type' => 'select',
        '#options' => $nodeTypeOptions,
        '#name' => self::NODE_TYPE_ARRAY . "[{$this->getNum()}]"
      ]
    ];
  }

  protected function getAuthorCol(): array
  {
    $selectedUser = user_load_by_mail($this->campaign->getEmail());
    $users = User::loadMultiple();
    $usersOptions = [];
    foreach ($users as $user) {
      $usersOptions[$user->id()] = t($user->label());
    }

    return [
      'data' => [
        '#type' => 'select',
        '#options' => $usersOptions,
        '#name' => self::AUTHOR_ARRAY . "[{$this->getNum()}]",
        '#value' => $selectedUser ? $selectedUser->id() : Drupal::currentUser()->id(),
      ]
    ];
  }

  protected function getNodeStatusCol(): array
  {
    return [
      'data' => [
        '#type' => 'select',
        '#options' => [
          0 => self::NODE_STATUS_DRAFT,
          1 => self::NODE_STATUS_PUBLISHED
        ],
        '#name' => self::PUBLISHED_ARRAY . "[{$this->getNum()}]",
      ]
    ];
  }

  protected function getNodePathCol(): array
  {
    return [
      'data' => [
        '#type' => 'textfield',
        '#value' => '',
        '#name' => self::NODE_PATH_ARRAY . "[{$this->getNum()}]",
        '#attributes' => ['size' => '15']
      ]
    ];
  }
}
