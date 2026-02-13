<?php

declare(strict_types=1);

namespace Drupal\farm_eggs\Plugin\QuickForm;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\farm_location\AssetLocationInterface;
use Drupal\farm_quick\Attribute\QuickForm;
use Drupal\farm_quick\Plugin\QuickForm\QuickFormBase;
use Drupal\farm_quick\Traits\QuickLogTrait;

/**
 * Eggs harvest quick form.
 */
#[QuickForm(
  id: 'eggs',
  label: new TranslatableMarkup('Eggs'),
  description: new TranslatableMarkup('Record an egg harvest.'),
  helpText: new TranslatableMarkup('Use this form to record an egg harvest. A harvest log will be created with standard details filled in.'),
  permissions: [
    'create harvest log',
  ],
)]
class Eggs extends QuickFormBase {

  use QuickLogTrait;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    protected AssetLocationInterface $assetLocation,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $current_user);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Date.
    $form['date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date'),
      '#default_value' => new DrupalDateTime('now', $this->currentUser->getTimeZone()),
      '#required' => TRUE,
    ];

    // Quantity.
    $form['quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity'),
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 1,
    ];

    // Load active assets with the "produces_eggs" field checked.
    $eggProducers = $this->entityTypeManager->getStorage('asset')->loadByProperties([
      'produces_eggs' => TRUE,
      'archived' => FALSE,
    ]);
    $assetsOptions = array_map(fn($asset) => $asset->toLink()->toString(), $eggProducers);

    // If there are asset options, add checkboxes.
    if (!empty($assetsOptions)) {
      $form['assets'] = array(
        '#type' => 'checkboxes',
        '#title' => $this->t('Layer asset'),
        '#description' => $this->t('Select the layer asset that these eggs came from. To add to this list, edit their record and check the "Produces eggs" checkbox.'),
        '#options' => $assetsOptions,
      );

      // If there is only one option, select it by default.
      if (count($assetsOptions) === 1) {
        $form['assets']['#default_value'] = array_keys($assetsOptions);
      }
    }
    // Otherwise, show some text about adding assets.
    else {
      $form['assets'] = array(
        '#type' => 'markup',
        '#markup' => $this->t('If you would like to associate this egg harvest log with an asset, edit their record and check the "Produces eggs" checkbox. Then you will be able to select them here.'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      );
    }

    // Notes.
    $form['notes'] = [
      '#type' => 'details',
      '#title' => $this->t('Notes'),
    ];
    $form['notes']['notes'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Notes'),
      '#title_display' => 'invisible',
      '#format' => 'default',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    // Get the date.
    $timestamp = $form_state->getValue('date')->getTimestamp();

    // Get selected assets ids.
    $assets = array_keys(array_filter($form_state->getValue('assets') ?? []));

    // Get assets locations.
    $locations = [];
    if (!empty($assets)) {
      /** @var \Drupal\asset\Entity\AssetInterface[] */
      $assetsEntities = $this->entityTypeManager->getStorage('asset')->loadMultiple($assets);
      foreach ($assetsEntities as $asset) {
        $assetLocation = $this->assetLocation->getLocation($asset);
        $locations = array_merge($locations, array_map(fn($location) => $location->id(), $assetLocation));
      }
      // Make sure each location is added only once.
      $locations = array_values(array_unique($locations));
    }

    // Create a new egg harvest log.
    $this->createLog([
      'type' => 'harvest',
      'timestamp' => $timestamp,
      'name' => $this->t('Collected @qty egg(s)', ['@qty' => $form_state->getValue('quantity')]),
      'asset' => $assets,
      'quantity' => [
        [
          'measure' => 'count',
          'value' => $form_state->getValue('quantity'),
          'units' => (string) $this->t('egg(s)'),
        ],
      ],
      'location' => $locations,
      'notes' => $form_state->getValue('notes'),
    ]);

  }

}
