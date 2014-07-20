<?php

/**
 * @file
 * Contains \Drupal\config_devel\EventSubscriber\ConfigDevelSubscriberBase.
 */

namespace Drupal\config_devel\EventSubscriber;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\FileStorage;

class ConfigDevelSubscriberBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The config file storage service.
   *
   * @var \Drupal\Core\Config\FileStorage
   */
  protected $fileStorage;

  /**
   * Constructs the ConfigDevelAutoExportSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ConfigManagerInterface $config_manager, FileStorage $file_storage) {
    $this->configFactory = $config_factory;
    $this->configManager = $config_manager;
    $this->fileStorage = $file_storage;
  }

  /**
   * @param string $entity_type_id
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected function getStorage($entity_type_id) {
    return $this->configManager->getEntityManager()->getStorage($entity_type_id);
  }

  /**
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $entity_storage
   * @param string $config_name
   *
   * @return string
   */
  protected function getEntityId(ConfigEntityStorageInterface $entity_storage, $config_name) {
    // getIDFromConfigName adds a dot but getConfigPrefix has a dot already.
    return $entity_storage::getIDFromConfigName($config_name, substr($entity_storage->getConfigPrefix(), 0, -1));
  }

  /**
   * @return \Drupal\Core\Config\Config
   */
  protected function getSettings() {
    return $this->configFactory->get('config_devel.settings');
  }
}
