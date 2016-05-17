<?php

/**
 * @file
 * Contains \Drupal\og\GroupManager.
 */

namespace Drupal\og;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\og\Entity\OgRole;

/**
 * A manager to keep track of which entity type/bundles are OG group enabled.
 */
class GroupManager {

  /**
   * The OG settings configuration key.
   *
   * @var string
   */
  const SETTINGS_CONFIG_KEY = 'og.settings';

  /**
   * The OG group settings config key.
   *
   * @var string
   */
  const GROUPS_CONFIG_KEY = 'groups';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity storage for OgRole entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $ogRoleStorage;

  /**
   * A map of entity types and bundles.
   *
   * @var array
   */
  protected $groupMap;

  /**
   * Constructs an GroupManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->ogRoleStorage = $entity_type_manager->getStorage('og_role');
    $this->refreshGroupMap();
  }

  /**
   * Determines whether an entity type ID and bundle ID are group enabled.
   *
   * @param string $entity_type_id
   * @param string $bundle
   *
   * @return bool
   */
  public function isGroup($entity_type_id, $bundle) {
    return isset($this->groupMap[$entity_type_id]) && in_array($bundle, $this->groupMap[$entity_type_id]);
  }

  /**
   * @param $entity_type_id
   *
   * @return array
   */
  public function getGroupsForEntityType($entity_type_id) {
    return isset($this->groupMap[$entity_type_id]) ? $this->groupMap[$entity_type_id] : [];
  }

  /**
   * Get all group bundles keyed by entity type.
   *
   * @return array
   *   An associative array, keyed by entity type, each value an indexed array
   *   of bundle IDs.
   */
  public function getAllGroupBundles($entity_type = NULL) {
    return !empty($this->groupMap[$entity_type]) ? $this->groupMap[$entity_type] : $this->groupMap;
  }

  /**
   * Sets an entity type instance as being an OG group.
   */
  public function addGroup($entity_type_id, $bundle_id) {
    $editable = $this->configFactory->getEditable('og.settings');
    $groups = $editable->get('groups');
    $groups[$entity_type_id][] = $bundle_id;
    // @todo, just key by bundle ID instead?
    $groups[$entity_type_id] = array_unique($groups[$entity_type_id]);

    $editable->set('groups', $groups);
    $saved = $editable->save();

    $this->createDefaultRoles($entity_type_id, $bundle_id);
    $this->refreshGroupMap();

    return $saved;
  }

  /**
   * Removes an entity type instance as being an OG group.
   */
  public function removeGroup($entity_type_id, $bundle_id) {
    $editable = $this->configFactory->getEditable('og.settings');
    $groups = $editable->get('groups');

    if (isset($groups[$entity_type_id])) {
      $search_key = array_search($bundle_id, $groups[$entity_type_id]);

      if ($search_key !== FALSE) {
        unset($groups[$entity_type_id][$search_key]);
      }

      // Clean up entity types that have become empty.
      $groups = array_filter($groups);

      // Only update and refresh the map if a key was found and unset.
      $editable->set('groups', $groups);
      $saved = $editable->save();

      // Remove all roles associated with this group type.
      $this->removeRoles($entity_type_id, $bundle_id);

      $this->refreshGroupMap();

      return $saved;
    }
  }

  /**
   * Creates default roles for the given group type.
   *
   * @param string $entity_type_id
   *   The entity type ID of the group for which to create default roles.
   * @param string $bundle_id
   *   The bundle ID of the group for which to create default roles.
   */
  protected function createDefaultRoles($entity_type_id, $bundle_id) {
    $properties = [
      'group_type' => $entity_type_id,
      'group_bundle' => $bundle_id,
    ];
    foreach ([OgRoleInterface::ANONYMOUS, OgRoleInterface::AUTHENTICATED, OgRoleInterface::ADMINISTRATOR] as $role_name) {
      // @todo: Find a way to deal with potential ID collisions.
      $properties['id'] = $role_name;
      $properties['role_type'] = OgRole::getRoleTypeByName($role_name);
      // Only create the default role if it doesn't exist yet.
      if (!$this->ogRoleStorage->loadByProperties($properties)) {
        $role = $this->ogRoleStorage->create($properties + OgRole::getDefaultProperties($role_name));
        $role->save();
      }
    }
  }

  /**
   * Deletes the roles associated with a group type.
   *
   * @param string $entity_type_id
   *   The entity type ID of the group for which to delete the roles.
   * @param string $bundle_id
   *   The bundle ID of the group for which to delete the roles.
   */
  protected function removeRoles($entity_type_id, $bundle_id) {
    $properties = [
      'group_type' => $entity_type_id,
      'group_bundle' => $bundle_id,
    ];
    foreach ($this->ogRoleStorage->loadByProperties($properties) as $role) {
      $role->delete();
    }
  }

  /**
   * Refreshes the groupMap property with currently configured groups.
   */
  protected function refreshGroupMap() {
    $this->groupMap = $this->configFactory->get(static::SETTINGS_CONFIG_KEY)->get(static::GROUPS_CONFIG_KEY);
  }

}
