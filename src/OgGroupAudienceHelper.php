<?php

/**
 * @file
 * Contains \Drupal\og\OgGroupAudienceHelper.
 */

namespace Drupal\og;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldException;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\og\Plugin\Field\FieldWidget\OgComplex;
use Drupal\Component\Utility\Html;

/**
 * OG audience field helper methods.
 */
class OgGroupAudienceHelper {

  /**
   * The default OG audience field name.
   */
  const DEFAULT_FIELD = 'og_group_ref';

  /**
   * Return TRUE if a field can be used and has not reached maximum values.
   *d
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to check the field cardinality for.
   * @param string $field_name
   *   The field name to check the cardinality of.
   *
   * @return bool
   *
   * @throws \Drupal\Core\Field\FieldException
   */
  public static function checkFieldCardinality(ContentEntityInterface $entity, $field_name) {
    $field_definition = $entity->getFieldDefinition($field_name);

    $entity_type_id = $entity->getEntityTypeId();
    $bundle_id = $entity->bundle();

    if (!$field_definition) {
      throw new FieldException("No field with the name $field_name found for $bundle_id $entity_type_id entity.");
    }

    if (!Og::isGroupAudienceField($field_definition)) {
      throw new FieldException("$field_name field on $bundle_id $entity_type_id entity is not an audience field.");
    }

    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();

    if ($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return TRUE;
    }

    return $entity->get($field_name)->count() < $cardinality;
  }

  /**
   * Returns the first group audience field that matches the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The group content to find a matching group audience field for.
   * @param string $group_type
   *   The group type that should be referenced by the group audience field.
   * @param string $group_bundle
   *   The group bundle that should be referenced by the group audience field.
   * @param bool $check_access
   *   (optional) Set this to FALSE to not check if the current user has access
   *   to the field. Defaults to TRUE.
   *
   * @return string|NULL
   *   The name of the group audience field, or NULL if no matching field was
   *   found.
   */
  public static function getMatchingField(ContentEntityInterface $entity, $group_type, $group_bundle, $check_access = TRUE) {
    $fields = Og::getAllGroupAudienceFields($entity->getEntityTypeId(), $entity->bundle());

    // Bail out if there are no group audience fields.
    if (!$fields) {
      return NULL;
    }

    foreach ($fields as $field_name => $field) {
      $handler_settings = $field->getSetting('handler_settings');

      if ($field->getSetting('target_type') !== $group_type) {
        // Group type doesn't match.
        continue;
      }

      if (!empty($handler_settings['target_bundles']) && !in_array($group_bundle, $handler_settings['target_bundles'])) {
        // Bundle doesn't match.
        continue;
      }

      if (!static::checkFieldCardinality($entity, $field_name)) {
        // The field cardinality has reached its maximum
        continue;
      }

      if ($check_access && !$entity->get($field_name)->access('view')) {
        // The user doesn't have access to the field.
        continue;
      }

      return $field_name;
    }

    return NULL;
  }

  /**
   * Get list of available widgets.
   *
   * @return array
   *   List of available entity reference widgets.
   */
  public static function getAvailableWidgets() {
    $widget_manager = \Drupal::getContainer()->get('plugin.manager.field.widget');
    $definitions = $widget_manager->getDefinitions();

    $widgets = [];
    foreach ($definitions as $id => $definition) {

      if (!in_array('entity_reference', $definition['field_types'])) {
        continue;
      }

      $widgets[] = $id;
    }

    return $widgets;
  }

  /**
   * Set the field mode widget.
   *
   * @param $entity_id
   *   The entity id.
   * @param $bundle
   *   The bundle.
   * @param $field_name
   *   The field name.
   * @param array $modes
   *   The field modes. Available keys: default, admin.
   *
   * @return int
   *   Either SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  public static function setWidgets($entity_id, $bundle, $field_name, array $modes) {
    $field = FieldConfig::loadByName($entity_id, $bundle, $field_name);
    $handler = $field->getSetting('handler_settings');
    $handler['handler_settings']['widgets'] = $modes;
    $field->setSetting('handler_settings', $handler);
    return $field->save();
  }

  /**
   * get the field mode widget.
   *
   * @param $entity_id
   *   The entity id.
   * @param $bundle
   *   The bundle.
   * @param $field_name
   *   The field name.
   * @param null $mode
   *   The field mode - admin or default.
   *
   * @return array.
   *   The field modes.
   */
  public static function getWidgets($entity_id, $bundle, $field_name, $mode = NULL) {
    $field = FieldConfig::loadByName($entity_id, $bundle, $field_name);
    $handler = $field->getSetting('handler_settings');
    return $mode ? $handler['handler_settings']['widgets'][$mode] : $handler['handler_settings']['widgets'];
  }

  /**
   * @param FieldDefinitionInterface $field
   *   The field definition.
   * @param $widget_id
   *   An entity reference widget plugin id i.e: options_select, options_buttons.
   * @param string $field_name
   *   The field name. Default to self::DEFAULT_FIELD.
   * @param array $configuration
   *   Configuration which will be passed to the widget instance.
   *
   * @return WidgetBase The form API widget element.
   * The form API widget element.
   */
  public static function renderWidget(FieldDefinitionInterface $field, $widget_id, $field_name = self::DEFAULT_FIELD, array $configuration = []) {
    $config = FieldConfig::load($field->getTargetEntityTypeId() . '.' . $field->getTargetBundle() . '.' . $field_name);

    $default_configuration = $configuration + [
      'type' => 'og_complex',
      'settings' => [
        'match_operator' => 'CONTAINS',
        'size' => 60,
        'placeholder' => '',
      ],
      'third_party_settings' => [],
      'field_definition' => $config,
    ];

    return \Drupal::getContainer()->get('plugin.manager.field.widget')->createInstance($widget_id, $default_configuration);
  }

  public static function autoCompleteHelper(&$element, OgComplex $ogComplex, $cardinality, $field_name, FormState $form_state, $user_group_ids, $parents) {
    // Determine the number of widgets to display.
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = static::getWidgetState($parents, $field_name, $form_state);
        $max = $field_state['items_count'];
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $title = $ogComplex->getFieldDefinition()->getLabel();
    $description = FieldFilteredMarkup::create(\Drupal::token()->replace($ogComplex->getfieldDefinition()->getDescription()));

    $elements = array();

    for ($delta = 0; $delta <= $max; $delta++) {
      // Add a new empty item if it doesn't exist yet at this delta.
      if (!isset($items[$delta])) {
        $items->appendItem();
      }
      elseif (!in_array($items[$delta]->get('target_id')->getValue(), $user_group_ids)) {
        continue;
      }

      // For multiple fields, title and description are handled by the wrapping
      // table.
      if ($is_multiple) {
        $element = [
          '#title' => $ogComplex->t('@title (value @number)', ['@title' => $title, '@number' => $delta + 1]),
          '#title_display' => 'invisible',
          '#description' => '',
        ];
      }
      else {
        $element = [
          '#title' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      }

      $element = $ogComplex->formSingleElement($items, $delta, $element, $form, $form_state);

      if ($element) {
        // Input field for the delta (drag-n-drop reordering).
        if ($is_multiple) {
          // We name the element '_weight' to avoid clashing with elements
          // defined by widget.
          $element['_weight'] = array(
            '#type' => 'weight',
            '#title' => $ogComplex->t('Weight for row @number', array('@number' => $delta + 1)),
            '#title_display' => 'invisible',
            // Note: this 'delta' is the FAPI #type 'weight' element's property.
            '#delta' => $max,
            '#default_value' => $items[$delta]->_weight ?: $delta,
            '#weight' => 100,
          );
        }

        $elements[$delta] = $element;
      }
    }

    if ($elements) {
      $elements += array(
        '#theme' => 'field_multiple_value_form',
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $ogComplex->getFieldDefinition()->getFieldStorageDefinition()->isMultiple(),
        '#required' => $ogComplex->getFieldDefinition()->isRequired(),
        '#title' => $title,
        '#description' => $description,
        '#max_delta' => $max,
      );

      // Add 'add more' button, if not working with a programmed form.
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
        $id_prefix = implode('-', array_merge($parents, array($field_name)));
        $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
        $elements['#prefix'] = '<div id="' . $wrapper_id . '">';
        $elements['#suffix'] = '</div>';

        $elements['add_more'] = array(
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => t('Add another item'),
          '#attributes' => array('class' => array('field-add-more-submit')),
          '#limit_validation_errors' => array(array_merge($parents, array($field_name))),
          '#submit' => array(array(get_class($ogComplex), 'addMoreSubmit')),
          '#ajax' => array(
            'callback' => array(get_class($ogComplex), 'addMoreAjax'),
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ),
        );
      }
    }
  }

}
