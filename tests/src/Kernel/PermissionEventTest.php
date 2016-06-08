<?php

namespace Drupal\Tests\og\Kernel;

use Drupal\Core\Entity\Entity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\og\Event\PermissionEvent;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\Og;
use Drupal\og\OgGroupAudienceHelper;

/**
 * Tests the implementations of the PermissionEvent in 'og' and 'og_ui'.
 *
 * @group og
 */
class PermissionEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'node',
    'og',
    'og_ui',
    'system',
    'user',
  ];

  /**
   * The Symfony event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The bundle ID used for the test group.
   *
   * @var string
   */
  protected $groupBundleId;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->eventDispatcher = $this->container->get('event_dispatcher');

    // Create a group entity type.
    $this->groupBundleId = 'test_group';
    NodeType::create([
      'type' => $this->groupBundleId,
      'name' => $this->randomString(),
    ])->save();
    Og::groupManager()->addGroup('node', $this->groupBundleId);

    // Create a group content entity type.
    $group_content_bundle_id = 'test_group_content';
    NodeType::create([
      'type' => $group_content_bundle_id,
      'name' => $this->randomString(),
    ])->save();
    Og::createField(OgGroupAudienceHelper::DEFAULT_FIELD, 'node', $group_content_bundle_id);
  }

  /**
   * Tests that the two OG modules can provide their own OG permissions.
   *
   * Some permissions (such as 'subscribe', 'manage members', etc.) are
   * available for all group types. In addition to this there are also OG
   * permissions for creating, editing and deleting the group content that
   * associated with the group.
   *
   * In this test we will check that the correct permissions are generated for
   * our test group (which includes permissions to create, edit and delete group
   * content of type 'test_group_content'), as well as a control group which
   * doesn't have any group content - in this case it should only return the
   * default permissions that are available to all group types.
   *
   * @param bool $test_group_content_permissions
   *   TRUE to check the permissions expected for a group type that has group
   *   content of type 'test_group_content'. FALSE to only check for the
   *   expected default permissions that are valid for any group type.
   * @param array $expected_permissions
   *   An array of permission names that are expected to be returned.
   *
   * @dataProvider permissionEventDataProvider
   */
  public function testPermissionEventIntegration($test_group_content_permissions, $expected_permissions) {
    $entity_type_id = $test_group_content_permissions ? 'node' : $this->randomMachineName();
    $bundle_id = $test_group_content_permissions ? $this->groupBundleId : $this->randomMachineName();

    // Retrieve the permissions from the listeners.
    /** @var PermissionEvent $permission_event */
    $event = new PermissionEvent($entity_type_id, $bundle_id);
    $permission_event = $this->eventDispatcher->dispatch(PermissionEventInterface::EVENT_NAME, $event);
    $actual_permissions = array_keys($permission_event->getPermissions());

    // Sort the permission arrays so they can be compared.
    sort($expected_permissions);
    sort($actual_permissions);

    $this->assertEquals($expected_permissions, $actual_permissions);
  }

  /**
   * Provides expected results for the testPermissionEventIntegration test.
   *
   * @return array
   *   An array of test properties. Each property is an indexed array with the
   *   following items:
   *   - A boolean indication whether or not to request permissions for a group
   *     that has group content of type 'test_group_content'. If FALSE only the
   *     default permissions that are valid for any group type are returned.
   *   - An array of permission names that are expected to be returned.
   */
  public function permissionEventDataProvider() {
    $default_permissions = [
      'add user',
      'administer group',
      'approve and deny subscription',
      'manage members',
      'manage permissions',
      'manage roles',
      'subscribe without approval',
      'subscribe',
      'unsubscribe',
      'update group',
    ];
    $group_content_permissions = [
      'create test_group_content node',
      'delete any test_group_content node',
      'delete own test_group_content node',
      'update any test_group_content node',
      'update own test_group_content node',
    ];
    return [
      [FALSE, $default_permissions],
      [TRUE, array_merge($default_permissions, $group_content_permissions)],
    ];
  }

}
