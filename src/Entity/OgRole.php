<?php

namespace Drupal\og\Entity;

use Drupal\Core\Config\ConfigValueException;
use Drupal\og\Exception\OgRoleRequiredException;
use Drupal\og\OgRoleInterface;
use Drupal\user\Entity\Role;

/**
 * Defines the OG user role entity class.
 *
 * @see \Drupal\user\Entity\Role
 *
 * @ConfigEntityType(
 *   id = "og_role",
 *   label = @Translation("OG role"),
 *   static_cache = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "group_type",
 *     "group_bundle",
 *     "group_id",
 *     "permissions",
 *     "role_type"
 *   }
 * )
 */
class OgRole extends Role implements OgRoleInterface {

  /**
   * Sets the ID of the role.
   *
   * @param string $id
   *   The machine name of the role.
   *
   * @return $this
   */
  public function setId($id) {
    $this->set('id', $id);
    return $this;
  }

  /**
   * Returns the label.
   *
   * @return string
   *   The label.
   */
  public function getLabel() {
    return $this->get('label');
  }

  /**
   * Sets the label.
   *
   * @param string $label
   *   The label to set.
   *
   * @return $this
   */
  public function setLabel($label) {
    $this->set('label', $label);
    return $this;
  }

  /**
   * Returns the group ID.
   *
   * @return int
   *   The group ID.
   */
  public function getGroupId() {
    return $this->get('group_id');
  }

  /**
   * Sets the group ID.
   *
   * @param int $group_id
   *   The group ID to set.
   *
   * @return $this
   */
  public function setGroupId($group_id) {
    $this->set('group_id', $group_id);
    return $this;
  }

  /**
   * Returns the group type.
   *
   * @return string
   *   The group type.
   */
  public function getGroupType() {
    return $this->get('group_type');
  }

  /**
   * Sets the group type.
   *
   * @param string $group_type
   *   The group type to set.
   *
   * @return $this
   */
  public function setGroupType($group_type) {
    $this->set('group_type', $group_type);
    return $this;
  }

  /**
   * Returns the group bundle.
   *
   * @return string
   *   The group bundle.
   */
  public function getGroupBundle() {
    return $this->get('group_bundle');
  }

  /**
   * Sets the group bundle.
   *
   * @param string $group_bundle
   *   The group bundle to set.
   *
   * @return $this
   */
  public function setGroupBundle($group_bundle) {
    $this->set('group_bundle', $group_bundle);
    return $this;
  }

  /**
   * Returns the role type.
   *
   * @return string
   *   The role type. One of OgRoleInterface::ROLE_TYPE_IMMUTABLE or
   *   OgRoleInterface::ROLE_TYPE_STANDARD.
   */
  public function getRoleType() {
    return $this->get('role_type');
  }

  /**
   * Sets the role type.
   *
   * @param string $role_type
   *   The role type to set. One of OgRoleInterface::ROLE_TYPE_IMMUTABLE or
   *   OgRoleInterface::ROLE_TYPE_STANDARD.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   Thrown when an invalid role type is given.
   */
  public function setRoleType($role_type) {
    if (!in_array($role_type, [
      self::ROLE_TYPE_IMMUTABLE,
      self::ROLE_TYPE_STANDARD,
    ])) {
      throw new \InvalidArgumentException("'$role_type' is not a valid role type.");
    }
    return $this->set('role_type', $role_type);
  }

  /**
   * {@inheritdoc}
   */
  public function isMutable() {
    return $this->get('role_type') === OgRoleInterface::ROLE_TYPE_STANDARD;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    if ($this->isNew()) {
      if (empty($this->getGroupType())) {
        throw new ConfigValueException('The group type can not be empty.');
      }

      if (empty($this->getGroupBundle())) {
        throw new ConfigValueException('The group bundle can not be empty.');
      }

      // When assigning a role to group we need to add a prefix to the ID in
      // order to prevent duplicate IDs.
      $prefix = $this->getGroupType() . '-' . $this->getGroupBundle() . '-';

      if (!empty($this->getGroupId())) {
        $prefix .= $this->getGroupId() . '-';
      }

      $this->id = $prefix . $this->id();
    }

    parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    // Prevent the ID, role type, entity type or bundle to be changed for any of
    // the default roles. These default roles are required and shouldn't be
    // tampered with.
    $is_locked_property = in_array($property_name, [
      'id',
      'role_type',
      'group_type',
      'group_bundle',
    ]);
    $is_default_role = $this->getRoleType() !== self::ROLE_TYPE_STANDARD;
    if ($is_locked_property && $is_default_role && !$this->isNew()) {
      throw new OgRoleRequiredException("The $property_name of the default roles 'non-member' and 'member' cannot be changed.");
    }
    return parent::set($property_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // The default roles are required. Prevent them from being deleted for as
    // long as the group still exists.
    if (in_array($this->id(), [self::ANONYMOUS, self::AUTHENTICATED]) && $this->groupManager()->isGroup($this->getGroupType(), $this->getGroupBundle())) {
      throw new OgRoleRequiredException('The default roles "non-member" and "member" cannot be deleted.');
    }
    parent::delete();
  }

  /**
   * Returns default properties for each of the standard role names.
   *
   * @param string $role_name
   *   A role name, one of OgRoleInterface::ANONYMOUS,
   *   OgRoleInterface::AUTHENTICATED, OgRoleInterface::ADMINISTRATOR.
   *
   * @return array
   *   An array of default properties, to pass to OgRole::create().
   */
  public static function getDefaultProperties($role_name) {
    if (!in_array($role_name, [
      self::ANONYMOUS,
      self::AUTHENTICATED,
      self::ADMINISTRATOR,
    ])) {
      throw new \InvalidArgumentException("$role_name is not a default role name.");
    }

    $default_properties = [
      self::ANONYMOUS => [
        'role_type' => OgRoleInterface::ROLE_TYPE_IMMUTABLE,
        'label' => 'Non-member',
        'permissions' => ['subscribe'],
      ],
      self::AUTHENTICATED => [
        'role_type' => OgRoleInterface::ROLE_TYPE_IMMUTABLE,
        'label' => 'Member',
        'permissions' => ['unsubscribe'],
      ],
      self::ADMINISTRATOR => [
        'role_type' => OgRoleInterface::ROLE_TYPE_STANDARD,
        'label' => 'Administrator',
        'permissions' => [
          'add user',
          'administer group',
          'approve and deny subscription',
          'manage members',
          'manage permissions',
          'manage roles',
          'update group',
        ],
      ],
    ];

    return $default_properties[$role_name];
  }

  /**
   * Maps role names to role types.
   *
   * The 'anonymous' and 'authenticated' roles should not be changed or deleted.
   * All others are standard roles.
   *
   * @param string $role_name
   *   The role name for which to return the type.
   *
   * @return string
   *   The role type, either OgRoleInterface::ROLE_TYPE_IMMUTABLE or
   *   OgRoleInterface::ROLE_TYPE_STANDARD.
   */
  public static function getRoleTypeByName($role_name) {
    switch ($role_name) {
      case OgRoleInterface::ANONYMOUS:
      case OgRoleInterface::AUTHENTICATED:
        return OgRoleInterface::ROLE_TYPE_IMMUTABLE;
      default:
        return OgRoleInterface::ROLE_TYPE_STANDARD;
    }
  }

  /**
   * Gets the group manager.
   *
   * @return \Drupal\og\GroupManager
   *   The group manager.
   */
  protected function groupManager() {
    // Returning the group manager by calling the global factory method might
    // seem less than ideal, but Entity classes are not designed to work with
    // proper dependency injection. The ::create() method only accepts a $values
    // array, which is not compatible with ContainerInjectionInterface.
    // See for example Entity::uuidGenerator() in the base Entity class, it
    // also uses this pattern.
    return \Drupal::service('og.group.manager');
  }

}
