<?php

namespace Drupal\og\Event;

/**
 * Interface for DefaultRoleEvent classes.
 *
 * This event allows implementing modules to provide their own default roles or
 * alter existing default roles that are provided by other modules.
 */
interface DefaultRoleEventInterface extends \ArrayAccess, \IteratorAggregate {

  /**
   * The event name.
   */
  const EVENT_NAME = 'og.default_role';

  /**
   * Returns a single default role.
   *
   * @param $name
   *   The name of the role to return.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the role with the given name does not exist.
   */
  public function getRole($name);

  /**
   * Returns all the default role names.
   *
   * @return array
   *   An associative array of default role properties, keyed by role name.
   */
  public function getRoles();

  /**
   * Adds a default role.
   *
   * @param string $name
   *   The name of the role to add.
   * @param array $properties
   *   An associative array of role properties, keyed by the following:
   *   - 'label': The human readable label.
   *   - 'role_type': Either OgRoleInterface::ROLE_TYPE_STANDARD or
   *     OgRoleInterface::ROLE_TYPE_REQUIRED. Defaults to
   *     OgRoleInterface::ROLE_TYPE_STANDARD.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the role that is added already exists, when the role name is
   *   empty, or when the 'label' property is missing.
   */
  public function addRole($name, array $properties);

  /**
   * Adds multiple default roles.
   *
   * @param array $roles
   *   An associative array of default role properties, keyed by role name.
   */
  public function addRoles(array $roles);

  /**
   * Sets a default roles.
   *
   * @param string $name
   *   The name of the role to set.
   * @param array $properties
   *   An associative array of role properties to set, keyed by the following:
   *   - 'label': The human readable label.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the role name is empty, or when the 'label' property is
   *   missing.
   */
  public function setRole($name, array $properties);

  /**
   * Sets multiple default roles.
   *
   * @param array $roles
   *   An associative array of default role properties, keyed by role name.
   */
  public function setRoles(array $roles);

  /**
   * Deletes the given default role.
   *
   * @param string $name
   *   The name of the role to delete.
   */
  public function deleteRole($name);

  /**
   * Returns whether or not the given role exists.
   *
   * @param string $name
   *   The name of the role for which to verify the existance.
   *
   * @return bool
   *   TRUE if the role exists, FALSE otherwise.
   */
  public function hasRole($name);

}
