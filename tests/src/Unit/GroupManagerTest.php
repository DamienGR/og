<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Unit\GroupManagerTest.
 */

namespace Drupal\Tests\og\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\og\Event\DefaultRoleEvent;
use Drupal\og\Event\DefaultRoleEventInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\og\Entity\OgRole;
use Drupal\og\Event\PermissionEventInterface;
use Drupal\og\GroupManager;
use Drupal\og\OgRoleInterface;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @group og
 * @coversDefaultClass \Drupal\og\GroupManager
 */
class GroupManagerTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\Config\Config|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $configProphecy;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $configFactoryProphecy;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeManagerProphecy;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityStorageProphecy;

  /**
   * @var \Drupal\og\Entity\OgRole|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $ogRoleProphecy;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeBundleInfoProphecy;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $eventDispatcherProphecy;

  /**
   * @var \Drupal\og\Event\PermissionEventInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $permissionEventProphecy;

  /**
   * @var \Drupal\Core\State\StateInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $stateProphecy;

  /**
   * @var \Drupal\og\Event\DefaultRoleEventInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $defaultRoleEventProphecy;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->configProphecy = $this->prophesize(Config::class);
    $this->configFactoryProphecy = $this->prophesize(ConfigFactoryInterface::class);
    $this->entityTypeManagerProphecy = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityStorageProphecy = $this->prophesize(EntityStorageInterface::class);
    $this->ogRoleProphecy = $this->prophesize(OgRole::class);
    $this->entityTypeBundleInfoProphecy = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $this->eventDispatcherProphecy = $this->prophesize(EventDispatcherInterface::class);
    $this->permissionEventProphecy = $this->prophesize(PermissionEventInterface::class);
    $this->stateProphecy = $this->prophesize(StateInterface::class);
    $this->defaultRoleEventProphecy = $this->prophesize(DefaultRoleEvent::class);
  }

  /**
   * @covers ::__construct
   */
  public function testInstance() {
    // Just creating an instance should be lightweight, no methods should be
    // called.
    $group_manager = $this->createGroupManager();
    $this->assertInstanceOf(GroupManager::class, $group_manager);
  }

  /**
   * @covers ::getAllGroupBundles
   */
  public function testGetAllGroupBundles() {
    // It is expected that the group map will be retrieved from config.
    $groups = ['test_entity' => ['a', 'b']];
    $this->expectGroupMapRetrieval($groups);

    $manager = $this->createGroupManager();

    $this->assertSame($groups, $manager->getAllGroupBundles());
  }

  /**
   * @covers ::isGroup
   *
   * @dataProvider providerTestIsGroup
   */
  public function testIsGroup($entity_type_id, $bundle_id, $expected) {
    // It is expected that the group map will be retrieved from config.
    $groups = ['test_entity' => ['a', 'b']];
    $this->expectGroupMapRetrieval($groups);

    $manager = $this->createGroupManager();

    $this->assertSame($expected, $manager->isGroup($entity_type_id, $bundle_id));
  }

  /**
   * Data provider for testIsGroup
   *
   * @return array
   */
  public function providerTestIsGroup() {
    return [
      ['test_entity', 'a', TRUE],
      ['test_entity', 'b', TRUE],
      ['test_entity', 'c', FALSE],
      ['test_entity_non_existent', 'a', FALSE],
      ['test_entity_non_existent', 'c', FALSE],
    ];
  }

  /**
   * @covers ::getGroupsForEntityType
   */
  public function testGetGroupsForEntityType() {
    // It is expected that the group map will be retrieved from config.
    $groups = ['test_entity' => ['a', 'b']];
    $this->expectGroupMapRetrieval($groups);

    $manager = $this->createGroupManager();

    $this->assertSame($groups['test_entity'], $manager->getGroupsForEntityType('test_entity'));
    $this->assertSame([], $manager->getGroupsForEntityType('test_entity_non_existent'));
  }

  /**
   * @covers ::addGroup
   */
  public function testAddGroupExisting() {
    // It is expected that the group map will be retrieved from config.
    $groups_before = ['test_entity' => ['a', 'b']];
    $this->expectGroupMapRetrieval($groups_before);

    $groups_after = ['test_entity' => ['a', 'b', 'c']];

    $this->configProphecy->get('groups')
      ->willReturn($groups_after)
      ->shouldBeCalled();

    $manager = $this->createGroupManager();

    // Add to existing.
    try {
      $manager->addGroup('test_entity', 'c');
      $this->fail('An entity type that was already declared as a group, was redeclared as a group');
    } catch (\InvalidArgumentException $e) {
      // Expected result. An exception should be thrown when an entity type is
      // redeclared as a group.
    }
  }

  /**
   * @covers ::addGroup
   */
  public function testAddGroupNew() {
    $this->configFactoryProphecy->getEditable('og.settings')
      ->willReturn($this->configProphecy->reveal())
      ->shouldBeCalled();

    // It is expected that the group map will be retrieved from config.
    $groups_before = [];
    $this->expectGroupMapRetrieval($groups_before);

    $groups_after = ['test_entity_new' => ['a']];

    $config_prophecy = $this->configProphecy;
    $this->configProphecy->set('groups', $groups_after)
      ->will(function () use ($groups_after, $config_prophecy) {
        $config_prophecy->get('groups')
          ->willReturn($groups_after)
          ->shouldBeCalled();
      })
      ->shouldBeCalled();

    $this->configProphecy->save()
      ->shouldBeCalled();

    $manager = $this->createGroupManager();

    $this->expectDefaultRoleCreation('test_entity_new', 'a');

    // Add a new entity type.
    $manager->addGroup('test_entity_new', 'a');
    $this->assertSame(['a'], $manager->getGroupsForEntityType('test_entity_new'));
    $this->assertTrue($manager->isGroup('test_entity_new', 'a'));
  }

  /**
   * @covers ::addGroup
   */
  public function testRemoveGroup() {
    $this->configFactoryProphecy->getEditable('og.settings')
      ->willReturn($this->configProphecy->reveal())
      ->shouldBeCalled();

    // It is expected that the group map will be retrieved from config.
    $groups_before = ['test_entity' => ['a', 'b']];
    $this->expectGroupMapRetrieval($groups_before);

    $groups_after = ['test_entity' => ['a']];

    $this->configProphecy->set('groups', $groups_after)
      ->shouldBeCalled();

    $this->configProphecy->save()
      ->shouldBeCalled();

    $this->configProphecy->get('groups')
      ->willReturn($groups_after)
      ->shouldBeCalled();

    $this->expectRoleRemoval('test_entity', 'b');

    $manager = $this->createGroupManager();

    // Add to existing.
    $manager->removeGroup('test_entity', 'b');
    $this->assertSame(['a'], $manager->getGroupsForEntityType('test_entity'));
    $this->assertFalse($manager->isGroup('test_entity', 'b'));
    $this->assertTrue($manager->isGroup('test_entity', 'a'));
  }

  /**
   * Creates a group manager instance with a mock config factory.
   *
   * @return \Drupal\og\GroupManager
   */
  protected function createGroupManager() {
    // It is expected that the role storage will be initialized.
    $this->entityTypeManagerProphecy->getStorage('og_role')
      ->willReturn($this->entityStorageProphecy->reveal())
      ->shouldBeCalled();

    return new GroupManager(
      $this->configFactoryProphecy->reveal(),
      $this->entityTypeManagerProphecy->reveal(),
      $this->entityTypeBundleInfoProphecy->reveal(),
      $this->eventDispatcherProphecy->reveal(),
      $this->stateProphecy->reveal()
    );
  }

  /**
   * Sets up an expectation that the group map will be retrieved from config.
   *
   * @param array $groups
   *   The expected group map that will be returned by the mocked config.
   */
  protected function expectGroupMapRetrieval($groups = []) {
    $this->configFactoryProphecy->get('og.settings')
      ->willReturn($this->configProphecy->reveal())
      ->shouldBeCalled();

    $this->configProphecy->get('groups')
      ->willReturn($groups)
      ->shouldBeCalled();
  }

  /**
   * Mocked method calls when system under test should create default roles.
   *
   * @param string $entity_type
   *   The entity type for which default roles should be created.
   * @param string $bundle
   *   The bundle for which default roles should be created.
   */
  protected function expectDefaultRoleCreation($entity_type, $bundle) {
    // In order to populate the default roles for a new group type, it is
    // expected that the list of default roles to populate will be retrieved
    // from the event listener.
    $this->eventDispatcherProphecy->dispatch(DefaultRoleEventInterface::EVENT_NAME, Argument::type(DefaultRoleEvent::class))
      ->willReturn($this->defaultRoleEventProphecy->reveal())
      ->shouldBeCalled();
    $this->defaultRoleEventProphecy->getRoles()
      ->willReturn([])
      ->shouldBeCalled();

    foreach ([OgRoleInterface::ANONYMOUS, OgRoleInterface::AUTHENTICATED] as $role_name) {
      $this->addNewDefaultRole($entity_type, $bundle, $role_name);
    }
  }

  /**
   * Expected method calls when creating a new default role.
   *
   * @param string $entity_type
   *   The entity type for which the default role should be created.
   * @param string $bundle
   *   The bundle for which the default role should be created.
   * @param string $role_name
   *   The name of the role being created.
   */
  protected function addNewDefaultRole($entity_type, $bundle, $role_name) {
    // It is expected that the OG permissions that need to be populated on the
    // new role will be requested from the PermissionEvent listener.
    $this->eventDispatcherProphecy->dispatch(PermissionEventInterface::EVENT_NAME, Argument::type('\Drupal\og\Event\PermissionEvent'))
      ->willReturn($this->permissionEventProphecy->reveal())
      ->shouldBeCalled();
    $this->permissionEventProphecy->filterByDefaultRole($role_name)
      ->willReturn([])
      ->shouldBeCalled();

    // It is expected that the role will be created with default properties.
    $properties = [
      'group_type' => $entity_type,
      'group_bundle' => $bundle,
      'role_type' => OgRole::getRoleTypeByName($role_name),
      'id' => $role_name,
      'permissions' => [],
    ];
    $this->entityStorageProphecy->create($properties + OgRole::getDefaultRoles()[$role_name])
      ->willReturn($this->ogRoleProphecy->reveal())
      ->shouldBeCalled();

    // The role is expected to be saved.
    $this->ogRoleProphecy->save()
      ->willReturn(1)
      ->shouldBeCalled();
  }

  /**
   * Expected method calls when deleting roles after a group is deleted.
   *
   * @param string $entity_type_id
   *   The entity type for which the roles should be deleted.
   * @param string $bundle_id
   *   The bundle for which the roles should be deleted.
   */
  protected function expectRoleRemoval($entity_type_id, $bundle_id) {
    // It is expected that a call is done to retrieve all roles associated with
    // the group. This will return the 3 default role entities.
    $this->entityTypeManagerProphecy->getStorage('og_role')
      ->willReturn($this->entityStorageProphecy->reveal())
      ->shouldBeCalled();

    $properties = [
      'group_type' => $entity_type_id,
      'group_bundle' => $bundle_id,
    ];
    $this->entityStorageProphecy->loadByProperties($properties)
      ->willReturn([
        $this->ogRoleProphecy->reveal(),
        $this->ogRoleProphecy->reveal(),
        $this->ogRoleProphecy->reveal(),
      ])
      ->shouldBeCalled();

    // It is expected that all roles will be deleted, so three delete() calls
    // will be made.
    $this->ogRoleProphecy->delete()
      ->shouldBeCalledTimes(3);
  }

}
