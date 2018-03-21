<?php
/**
 * @file
 * Farm eggs quick forms.
 */

/**
 * Implements hook_farm_quick_forms().
 */
function farm_eggs_farm_quick_forms() {
  return array(
    'eggs' => array(
      'label' => t('Eggs'),
      'permission' => 'create farm_harvest log entities',
      'form' => 'farm_eggs_form',
      'file' => 'farm_eggs.farm_quick.inc',
    ),
  );
}

/**
 * Form for adding egg harvest logs.
 */
function farm_eggs_form($form, &$form_state) {

  // Wrapper fieldset.
  $form['eggs'] = array(
    '#type' => 'fieldset',
    '#title' => t('Record an egg harvest'),
    '#description' => t('Use this form to record an egg harvest. A harvest log will be created with standard details filled in.'),
  );

  // Quantity.
  $form['eggs']['quantity'] = array(
    '#type' => 'textfield',
    '#title' => t('Quantity'),
    '#required' => TRUE,
    '#element_validate' => array('element_validate_integer_positive'),
  );

  // Load active assets with the "farm_eggs" asset property.
  $asset_options = array();
  $result = db_query("SELECT a.id FROM {farm_asset_property} p LEFT JOIN {farm_asset} a ON p.id = a.id WHERE p.name = 'farm_eggs' AND a.archived IS NULL");
  foreach ($result as $row) {
    if (!empty($row->id)) {

      // Load the asset.
      $asset = farm_asset_load($row->id);

      // If the asset didn't load, skip it.
      if (empty($asset)) {
        continue;
      }

      // Add an option.
      $asset_options[$asset->id] = entity_label('farm_asset', $asset);
    }
  }

  // If there are asset options, add checkboxes.
  if (!empty($asset_options)) {
    $form['eggs']['assets'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Group/animal'),
      '#description' => t('Select the group/animal that these eggs came from. To add groups/animals to this list, edit their record and check the "Produces eggs" checkbox.'),
      '#options' => $asset_options,
    );
  }

  // Otherwise, show some text about adding groups/animals.
  else {
    $form['eggs']['assets'] = array(
      '#type' => 'markup',
      '#markup' => t('If you would like to associate this egg harvest log with a group/animal asset, edit their record and check the "Produces eggs" checkbox. Then you will be able to select them here.'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    );
  }

  // Submit button.
  $form['eggs']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save log'),
  );

  // Return the form.
  return $form;
}

/**
 * Submit function for eggs quick form.
 */
function farm_eggs_form_submit($form, &$form_state) {

  // Create a new log entity.
  $log = entity_create('log', array('type' => 'farm_harvest'));

  // Create an entity wrapper for the log.
  $log_wrapper = entity_metadata_wrapper('log', $log);

  // Set log name.
  $log_name = t('Collected @qty egg(s)', array('@qty' => $form_state['values']['quantity']));
  $log_wrapper->name->set($log_name);

  // Set the date.
  $log_wrapper->timestamp->set(REQUEST_TIME);

  // If there are assets referenced, add them to the asset reference field.
  if (!empty($form_state['values']['assets'])) {
    foreach ($form_state['values']['assets'] as $asset_id) {

      // Load the asset.
      $asset = farm_asset_load($asset_id);

      // If it loaded, add it to the asset reference.
      if (!empty($asset)) {
        $log_wrapper->field_farm_asset[] = $asset;
      }
    }
  }

  // Create a new quantity field_collection entity attached to the log.
  $quantity = entity_create('field_collection_item', array('field_name' => 'field_farm_quantity'));
  $quantity->setHostEntity('log', $log);

  // Create an entity wrapper for the quantity.
  $quantity_wrapper = entity_metadata_wrapper('field_collection_item', $quantity);

  // Set the quantity measure.
  $quantity_wrapper->field_farm_quantity_measure->set('count');

  // Set the quantity value.
  $value_fraction = fraction_from_decimal($form_state['values']['quantity']);
  $quantity_wrapper->field_farm_quantity_value->numerator->set($value_fraction->getNumerator());
  $quantity_wrapper->field_farm_quantity_value->denominator->set($value_fraction->getDenominator());

  // Look up the units taxonomy term.
  $units = t('egg(s)');
  $units_terms = taxonomy_get_term_by_name($units, 'farm_quantity_units');

  // If terms were found, use the first one.
  if (!empty($units_terms)) {
    $units_term = reset($units_terms);
  }

  // If a term wasn't found, create it.
  else {
    $farm_units = taxonomy_vocabulary_machine_name_load('farm_quantity_units');
    $units_term = new stdClass();
    $units_term->name = check_plain($units);
    $units_term->vid = $farm_units->vid;
    taxonomy_term_save($units_term);
  }

  // Set the quantity units.
  $quantity_wrapper->field_farm_quantity_units = $units_term;

  // Mark the log as done.
  $log_wrapper->done->set(TRUE);

  // Set the log owner.
  global $user;
  $log_wrapper->field_farm_log_owner[] = $user;

  // Save the quantity.
  $quantity_wrapper->save();

  // Save the log.
  $log_wrapper->save();

  // Set a message.
  $label = entity_label('log', $log);
  $uri = entity_uri('log', $log);
  drupal_set_message(t('Log created') . ': ' . l($label, $uri['path']));
}
