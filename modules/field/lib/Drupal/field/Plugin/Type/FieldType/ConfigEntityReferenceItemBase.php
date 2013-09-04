<?php

/**
 * @file
 * Contains \Drupal\field\Plugin\Type\FieldType\ConfigEntityReferenceItemBase.
 */

namespace Drupal\field\Plugin\Type\FieldType;

use Drupal\Core\Entity\Plugin\DataType\EntityReferenceItem;
use Drupal\field\Plugin\Type\FieldType\ConfigFieldItemInterface;
use Drupal\field\FieldInterface;
use Drupal\field\FieldInstanceInterface;

/**
 * A common base class for configurable entity reference fields.
 *
 * Extends the Core 'entity_reference' entity field item with properties for
 * revision ids, labels (for autocreate) and access.
 *
 * Required settings (below the definition's 'settings' key) are:
 *  - target_type: The entity type to reference.
 */
class ConfigEntityReferenceItemBase extends EntityReferenceItem implements ConfigFieldItemInterface {

  /**
   * Definitions of the contained properties.
   *
   * @see ConfigurableEntityReferenceItem::getPropertyDefinitions()
   *
   * @var array
   */
  static $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    // Definitions vary by entity type and bundle, so key them accordingly.
    $key = $this->definition['settings']['target_type'] . ':';
    $key .= isset($this->definition['settings']['target_bundle']) ? $this->definition['settings']['target_bundle'] : '';

    if (!isset(static::$propertyDefinitions[$key])) {
      // Call the parent to define the target_id and entity properties.
      parent::getPropertyDefinitions();

      static::$propertyDefinitions[$key]['revision_id'] = array(
        // @todo: Lookup the entity type's ID data type and use it here.
        'type' => 'integer',
        'label' => t('Revision ID'),
        'constraints' => array(
          'Range' => array('min' => 0),
        ),
      );
      static::$propertyDefinitions[$key]['label'] = array(
        'type' => 'string',
        'label' => t('Label (auto-create)'),
        'computed' => TRUE,
      );
      static::$propertyDefinitions[$key]['access'] = array(
        'type' => 'boolean',
        'label' => t('Access'),
        'computed' => TRUE,
      );
    }
    return static::$propertyDefinitions[$key];
  }

  /**
   * {@inheritdoc}
   *
   * Copied from \Drupal\field\Plugin\field\field_type\LegacyConfigFieldItem,
   * since we cannot extend it.
   */
  public static function schema(FieldInterface $field) {
    $definition = \Drupal::service('plugin.manager.entity.field.field_type')->getDefinition($field->type);
    $module = $definition['provider'];
    module_load_install($module);
    $callback = "{$module}_field_schema";
    if (function_exists($callback)) {
      return $callback($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Avoid loading the entity by first checking the 'target_id'.
    $target_id = $this->get('target_id')->getValue();
    if (!empty($target_id) && is_numeric($target_id)) {
      return FALSE;
    }
    // Allow auto-create entities.
    if (empty($target_id) && ($entity = $this->get('entity')->getValue()) && $entity->isNew()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Copied from \Drupal\field\Plugin\field\field_type\LegacyConfigFieldItem,
   * since we cannot extend it.
   */
  public function settingsForm(array $form, array &$form_state, $has_data) {
    if ($callback = $this->getLegacyCallback('settings_form')) {
      $instance = $this->getFieldDefinition();
      if (!($instance instanceof FieldInstanceInterface)) {
        throw new \UnexpectedValueException('ConfigEntityReferenceItemBase::settingsForm() called for a field whose definition is not a field instance.');
      }
      // hook_field_settings_form() used to receive the $instance (not actually
      // needed), and the value of field_has_data().
      return $callback($instance->getField(), $instance, $has_data);
    }
    return array();
  }

  /**
   * {@inheritdoc}
   *
   * Copied from \Drupal\field\Plugin\field\field_type\LegacyConfigFieldItem,
   * since we cannot extend it.
   */
  public function instanceSettingsForm(array $form, array &$form_state) {
    if ($callback = $this->getLegacyCallback('instance_settings_form')) {
      $instance = $this->getFieldDefinition();
      if (!($instance instanceof FieldInstanceInterface)) {
        throw new \UnexpectedValueException('ConfigEntityReferenceItemBase::instanceSettingsForm() called for a field whose definition is not a field instance.');
      }
      return $callback($instance->getField(), $instance, $form_state);
    }
    return array();
  }

  /**
   * Returns options provided via the legacy callback hook_options_list().
   *
   * @todo: Convert all legacy callback implementations to methods.
   *
   * @see \Drupal\Core\TypedData\AllowedValuesInterface
   */
  public function getSettableOptions() {
    $definition = $this->getPluginDefinition();
    $callback = "{$definition['provider']}_options_list";
    if (function_exists($callback)) {
      // We are at the field item level, so we need to go two levels up to get
      // to the entity object.
      $entity = $this->getParent()->getParent();
      return $callback($this->getFieldDefinition(), $entity);
    }
  }

  /**
   * Returns the legacy callback for a given field type "hook".
   *
   * Copied from \Drupal\field\Plugin\field\field_type\LegacyConfigFieldItem,
   * since we cannot extend it.
   *
   * @param string $hook
   *   The name of the hook, e.g. 'settings_form', 'is_empty'.
   *
   * @return string|null
   *   The name of the legacy callback, or NULL if it does not exist.
   */
  protected function getLegacyCallback($hook) {
    $definition = $this->getPluginDefinition();
    $module = $definition['provider'];
    $callback = "{$module}_field_{$hook}";
    if (function_exists($callback)) {
      return $callback;
    }
  }

}
