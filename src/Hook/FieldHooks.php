<?php

declare(strict_types=1);

namespace Drupal\farm_eggs\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\farm_field\FarmFieldFactoryInterface;

/**
 * Field hook implementations for farm_eggs.
 */
class FieldHooks {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected FarmFieldFactoryInterface $farmFieldFactory,
  ) {}

  /**
   * Implements hook_farm_entity_bundle_field_info().
   */
  #[Hook('farm_entity_bundle_field_info')]
  public function farmEntityBundleFieldInfo(EntityTypeInterface $entity_type, string $bundle): array  {
    $fields = [];

    // Add "Produces eggs" field to group and animal assets.
    if ($entity_type->id() == 'asset' && in_array($bundle, ['group', 'animal'])) {
      $options = [
        'type' => 'boolean',
        'label' => $this->t('Produces eggs'),
        'description' => $this->t('Checking this box will make this asset available in the <a href="@egg_form_path">egg harvest form</a>.', ['@egg_form_path' => '/quick/eggs']),
        'weight' => [
          'form' => 30,
          'view' => -30,
        ],
      ];
      $fields['produces_eggs'] =$this->farmFieldFactory->bundleFieldDefinition($options);
    }

    return $fields;
  }

}
