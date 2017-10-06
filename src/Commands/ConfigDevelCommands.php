<?php

namespace Drupal\config_devel\Commands;

use Drupal\config_devel\EventSubscriber\ConfigDevelAutoExportSubscriber;
use Drupal\config_devel\EventSubscriber\ConfigDevelAutoImportSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush integration for the Configuration Development module.
 */
class ConfigDevelCommands extends DrushCommands {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The parser for info.yml files.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected $infoParser;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The event subscriber that listens to config change events.
   *
   * @var \Drupal\config_devel\EventSubscriber\ConfigDevelAutoExportSubscriber
   */
  protected $autoExportSubscriber;

  /**
   * The event subscriber that listens to config change events.
   *
   * @var \Drupal\config_devel\EventSubscriber\ConfigDevelAutoImportSubscriber
   */
  protected $autoImportSubscriber;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new ConfigDevelCommands object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   The parser for info.yml files.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration object factory.
   * @param \Drupal\config_devel\EventSubscriber\ConfigDevelAutoExportSubscriber $autoExportSubscriber
   *   The event subscriber that listens to config change events, and happens to
   *   contain some code that we depend on which should be factored out into a
   *   separate service.
   * @param \Drupal\config_devel\EventSubscriber\ConfigDevelAutoImportSubscriber $autoImportSubscriber
   *   The event subscriber that listens to config change events, and happens to
   *   contain some code that we depend on which should be factored out into a
   *   separate service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler, InfoParserInterface $infoParser, ConfigFactoryInterface $configFactory, ConfigDevelAutoExportSubscriber $autoExportSubscriber, ConfigDevelAutoImportSubscriber $autoImportSubscriber, FileSystemInterface $fileSystem) {
    parent::__construct();

    $this->moduleHandler = $moduleHandler;
    $this->themeHandler = $themeHandler;
    $this->infoParser = $infoParser;
    $this->configFactory = $configFactory;
    // @todo We should not depend on event subscribers directly.
    // @see https://www.drupal.org/node/2388253
    $this->autoExportSubscriber = $autoExportSubscriber;
    $this->autoImportSubscriber = $autoImportSubscriber;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Write back configuration to module's config directory.
   *
   * List which configuration settings you want to export in the module's info
   * file by listing them under 'config_devel', as shown below:
   *
   * config_devel:
   *   install:
   *     - entity.view_display.node.article.default
   *     - entity.view_display.node.article.teaser
   *     - field.instance.node.article.body
   *   optional:
   *     - field.instance.node.article.tags
   *
   * @command config-devel-export
   * @param string $extension Machine name of the module, profile or theme to export.
   * @usage drush config-devel-export MODULE_NAME
   *   Write back configuration to the specified module, based on .info file.
   * @aliases cde,cd-em
   *
   * @throws \Exception
   *   Thrown when the passed in extension
   */
  public function export($extension) {
    // Determine the type of extension we're dealing with.
    $type = $this->getExtensionType($extension);

    if (!$type) {
      throw new \Exception("Couldn't export configuration. The '$extension' extension is not enabled.");
    }

    // Get the config.
    $config = $this->getConfig($type, $extension);

    // Export the required config.
    if (isset($config['install'])) {
      $this->exportConfig($config['install'], $type, $extension, InstallStorage::CONFIG_INSTALL_DIRECTORY);
    }

    // If we have any optional configuration, export that as well.
    if (isset($config['optional'])) {
      $this->exportConfig($config['optional'], $type, $extension, InstallStorage::CONFIG_OPTIONAL_DIRECTORY);
    }
  }

  /**
   * Import configuration from module's config directory to active storage.
   *
   * List which configuration settings you want to export in the module's info
   * file by listing them under 'config_devel', as shown below:
   *
   * config_devel:
   *   install:
   *     - entity.view_display.node.article.default
   *     - entity.view_display.node.article.teaser
   *     - field.instance.node.article.body
   *   optional:
   *     - field.instance.node.article.tags
   *
   * @command config-devel-import
   * @param string $extension Machine name of the module, profile or theme.
   * @usage drush config-devel-import MODULE_NAME
   *   Import configuration from the specified module, profile or theme into the active storage, based on .info file.
   * @aliases cdi,cd-im
   *
   * @throws \Exception
   *   Thrown when the passed in extension is not enabled.
   */
  public function import($extension) {
    // Determine the type of extension we're dealing with.
    $type = $this->getExtensionType($extension);

    if (!$type) {
      throw new \Exception("Couldn't import configuration. The '$extension' extension is not enabled.");
    }

    // Get the config
    $config = $this->getConfig($type, $extension);

    // Import config
    if (isset($config['install'])) {
      $this->importConfig($config['install'], $type, $extension, InstallStorage::CONFIG_INSTALL_DIRECTORY);
    }

    // Import optional config
    if (isset($config['optional'])) {
      $this->importConfig($config['optional'], $type, $extension, InstallStorage::CONFIG_OPTIONAL_DIRECTORY);
    }
  }

  /**
   * Import a single config item into active storage.
   *
   * List which configuration settings you want to export in the module's info
   * file by listing them under 'config_devel', as shown below:
   *
   * config_devel:
   *   install:
   *     - entity.view_display.node.article.default
   *     - entity.view_display.node.article.teaser
   *     - field.instance.node.article.body
   *   optional:
   *     - field.instance.node.article.tags
   *
   * @command config-devel-import-one
   * @param string $path Config file name.
   * @usage drush config-devel-import-one system.site.yml
   *   Import the contents of system.site.yml into the config object system.site.
   * @usage drush config-devel-import-one system.site
   *   Import the standard input into the config object system.site. Helpful for scripting copying to remote.
   * @aliases cdi1,cd-i1
   *
   * @throws \Exception
   *   Thrown when the given file was not found.
   */
  public function importSingle($path) {
    $contents = '';
    if (!file_exists($path)) {
      if (substr($path, -4) != '.yml') {
        $contents = file_get_contents('php://stdin');
      }
      elseif (!empty($_SERVER['PWD'])) {
        $path = $_SERVER['PWD'] . '/' . trim($path, '/');
      }
    }
    if ($contents || file_exists($path)) {
      $this->autoImportSubscriber->importOne($path, '', $contents);
    }
    else {
      throw new \Exception("File '$path' not found.");
    }
  }

  /**
   * Returns the type for the given extension.
   *
   * @param string $extension
   *   The extension name.
   *
   * @return string
   *   Either 'module', 'theme', 'profile', or NULL if no valid extension was
   *   provided.
   */
  protected function getExtensionType($extension) {
    $type = NULL;

    if ($this->moduleHandler->moduleExists($extension)) {
      $type = 'module';
    }
    elseif ($this->themeHandler->themeExists($extension)) {
      $type = 'theme';
    }
    elseif (\Drupal::installProfile() === $extension) {
      $type = 'profile';
    }

    return $type;
  }

  /**
   * Returns the config for the given extension.
   *
   * @param string $type
   *   Either 'module', 'theme' or 'profile'.
   * @param string $extension
   *   The name of the extension for which to return the config.
   *
   * @return array
   *   An array containing install and optional config.
   */
  protected function getConfig($type, $extension) {
    $filename = drupal_get_path($type, $extension) . '/' . $extension .'.info.yml';
    $info = $this->infoParser->parse($filename);

    $config = [];
    if (isset($info['config_devel'])) {
      // Keep backwards compatibility for the old format. This has config names
      // listed directly beneath 'config_devel', rather than an intermediate
      // level for 'install' and 'optional'.
      // Detect the old format based on whether there's neither of these two
      // keys.
      if (!isset($info['config_devel']['install']) && !isset($info['config_devel']['optional'])) {
        $info['config_devel']['install'] = $info['config_devel'];
      }

      $config['install'] = $info['config_devel']['install'];

      // If we have any optional configuration, fetch that as well.
      if (isset($info['config_devel']['optional'])) {
        $config['optional'] = $info['config_devel']['optional'];
      }
    }

    return $config;
  }

  /**
   * Exports a list of configuration entities.
   *
   * @param array $config_list
   *   An array of configuration entities.
   * @param string $type
   *   The type of extension we're exporting, one of 'module', 'profile' or
   *   'theme'.
   * @param string $extension
   *   The name of the extension we're exporting.
   * @param string $directory
   *   The directory we're exporting to.
   *
   * @throws \Exception
   *   Thrown when the directory to export to is missing and could not be
   *   created.
   */
  protected function exportConfig($config_list, $type, $extension, $directory) {
    $config_path = drupal_get_path($type, $extension) . "/$directory";
    // Ensure the directory always exists.
    if (!file_exists($config_path) && !$this->fileSystem->mkdir($config_path, NULL, TRUE)) {
      throw new \Exception(sprintf('The %s directory could not be created', $config_path));
    }

    foreach ($config_list as $name) {
      $config = $this->configFactory->get($name);
      $file_names = [$config_path . '/' . $name . '.yml'];
      $this->autoExportSubscriber->writeBackConfig($config, $file_names);
    }
  }

  /**
   * Imports a list of configuration entities.
   *
   * @param array $config_list
   *   An array of configuration entities.
   * @param string $type
   *   The type of extension we're importing, one of 'module'. 'profile' or
   *   'theme'.
   * @param string $extension
   *   The module, theme or install profile we're importing.
   * @param string $directory
   *   The directory we're importing.
   */
  protected function importConfig($config_list, $type, $extension, $directory) {
    $config_path = drupal_get_path($type, $extension) . "/$directory";
    foreach ($config_list as $name) {
      $file_name = $config_path . '/' . $name . '.yml';
      $this->importSingle($file_name);
    }
  }

}
