<?php

namespace Drupal\Tests\farm_eggs\Kernel;

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

    // Submit the egg harvest quick form.
    $this->submitQuickForm([
      'assets' => [$chickens->id() => strval($chickens->id())],
      'quantity' => 12,
    ]);

    // Confirm egg harvest log was created.
    /** @var \Drupal\log\Entity\LogInterface[] $harvestLogs */
    $harvestLogs = $this->logStorage->loadByProperties(['type' => 'harvest', 'quick' => 'eggs']);
    $this->assertCount(1, $harvestLogs);

    // Confirm that the egg harvest log has the correct values.
    /** @var \Drupal\log\Entity\LogInterface $harvestLog */
    $harvestLog = reset($harvestLogs);
    $this->assertInstanceOf('Drupal\log\Entity\LogInterface', $harvestLog);
    $this->assertEquals(
      '12',
      $harvestLog->get('quantity')->referencedEntities()[0]->get('value')[0]->get('decimal')->getValue(),
    );
    $this->assertEquals($chickens->id(), $harvestLog->get('asset')->target_id);
    $this->assertEquals($location->id(), $harvestLog->get('location')->target_id);
  }

}
