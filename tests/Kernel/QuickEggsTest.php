<?php

namespace Drupal\Tests\farm_eggs\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\farm_quick\Kernel\QuickFormTestBase;

/**
 * Tests for farmOS eggs quick form.
 *
 * @group farm
 */
class QuickEggsTest extends QuickFormTestBase {

  /**
   * Quick form ID.
   *
   * @var string
   */
  protected $quickFormId = 'eggs';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'farm_activity',
    'farm_eggs',
    'farm_group',
    'farm_harvest',
    'farm_location',
    'farm_quantity_standard',
    'farm_structure',
    'farm_unit',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'farm_activity',
      'farm_group',
      'farm_harvest',
      'farm_location',
      'farm_quantity_standard',
      'farm_structure',
      'system',
    ]);
  }

  /**
   * Test eggs quick form submission for asset with location.
   */
  public function testQuickEggsWithLocation() {

    // Get today's date.
    $today = new DrupalDateTime('midnight');

    // Create an egg producer to record harvest for.
    $chickens = $this->assetStorage->create([
      'type' => 'group',
      'name' => 'Chickens',
      'produces_eggs' => TRUE,
    ]);
    $chickens->save();

    // Create a location to move chickens to.
    $location = $this->assetStorage->create([
      'type' => 'structure',
      'name' => 'Chicken Coop',
    ]);
    $location->save();

    // Move chickens to chicken coop.
    $this->logStorage->create([
      'type' => 'activity',
      'name' => 'Move Chickens to Chicken Coop',
      'asset' => $chickens,
      'location' => $location,
      'is_movement' => TRUE,
      'status' => 'done',
    ])->save();

    // Prepare harvest notes content.
    $notes = 'Lorem ipsum';

    // Submit the egg harvest quick form.
    $this->submitQuickForm([
      'date' => [
        'date' => $today->format('Y-m-d'),
        'time' => $today->format('H:i:s'),
      ],
      'assets' => [$chickens->id() => strval($chickens->id())],
      'quantity' => 12,
      'notes' => [
        'value' => $notes,
        'format' => 'default',
      ],
    ]);

    // Confirm egg harvest log was created.
    /** @var \Drupal\log\Entity\LogInterface[] $harvestLogs */
    $harvestLogs = $this->logStorage->loadByProperties(['type' => 'harvest', 'quick' => 'eggs']);
    $this->assertCount(1, $harvestLogs);

    // Confirm that the egg harvest log has the correct values.
    /** @var \Drupal\log\Entity\LogInterface $harvestLog */
    $harvestLog = reset($harvestLogs);
    $this->assertInstanceOf('Drupal\log\Entity\LogInterface', $harvestLog);
    $this->assertEquals($today->getTimestamp(), $harvestLog->get('timestamp')->value);
    $this->assertEquals(
      '12',
      $harvestLog->get('quantity')->referencedEntities()[0]->get('value')[0]->get('decimal')->getValue(),
    );
    $this->assertEquals($chickens->id(), $harvestLog->get('asset')->target_id);
    $this->assertEquals($location->id(), $harvestLog->get('location')->target_id);
    $this->assertEquals($notes, $harvestLog->get('notes')->getValue()[0]['value']);
  }

  /**
   * Test eggs quick form submission for multiple assets in the same location.
   */
  public function testQuickEggsWithMultipleAssetsInTheSameLocation() {

    // Create a location to which egg producer assets will be moved.
    $location = $this->assetStorage->create([
      'type' => 'structure',
      'name' => 'Chicken Coop',
    ]);
    $location->save();

    // Create 2 egg producers and move them to the same location.
    $eggProducers = [];
    for ($i = 0; $i <= 2; $i++) {
      $eggProducer = $this->assetStorage->create([
        'type' => 'group',
        'name' => 'Hen ' . $i,
        'produces_eggs' => TRUE,
      ]);
      $eggProducer->save();
  
      // Move chickens to chicken coop.
      $this->logStorage->create([
        'type' => 'activity',
        'name' => 'Move Hen ' . $i . ' to Chicken Coop',
        'asset' => $eggProducer,
        'location' => $location,
        'is_movement' => TRUE,
        'status' => 'done',
      ])->save();

      $eggProducers[] = $eggProducer;
    }

    // Submit the egg harvest quick form.
    $this->submitQuickForm([
      'assets' => [
        $eggProducers[0]->id() => strval($eggProducers[0]->id()),
        $eggProducers[1]->id() => strval($eggProducers[1]->id()),
      ],
      'quantity' => 12,
    ]);

    // Confirm egg harvest log was created.
    /** @var \Drupal\log\Entity\LogInterface[] $harvestLogs */
    $harvestLogs = $this->logStorage->loadByProperties(['type' => 'harvest', 'quick' => 'eggs']);
    $this->assertCount(1, $harvestLogs);
    $harvestLog = reset($harvestLogs);

    // Make sure the egg harvest has assigned the location only once.
    $this->assertCount(1, $harvestLog->get('location')->getValue());
    $this->assertEquals($location->id(), $harvestLog->get('location')->target_id);
  }

}
