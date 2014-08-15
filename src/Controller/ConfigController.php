<?php

/**
 * @file
 * Contains \Drupal\config\Controller\ConfigController
 */

namespace Drupal\site_dump\Controller;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Vocabulary;


/**
 * Returns responses for config module routes.
 */
class ConfigController implements ContainerInjectionInterface {

  /**
   * The target storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $targetStorage;

  /**
   * The source storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $sourceStorage;

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The file download controller.
   *
   * @var \Drupal\system\FileDownloadController
   */
  protected $fileDownloadController;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.storage'),
      $container->get('config.storage.staging'),
      $container->get('config.manager'),
      new FileDownloadController()
    );
  }

  /**
   * Constructs a ConfigController object.
   *
   * @param \Drupal\Core\Config\StorageInterface $target_storage
   *   The target storage.
   * @param \Drupal\Core\Config\StorageInterface $source_storage
   *   The source storage
   * @param \Drupal\system\FileDownloadController $file_download_controller
   *   The file download controller.
   */
  public function __construct(StorageInterface $target_storage, StorageInterface $source_storage, ConfigManagerInterface $config_manager, FileDownloadController $file_download_controller) {
    $this->targetStorage = $target_storage;
    $this->sourceStorage = $source_storage;
    $this->configManager = $config_manager;
    $this->fileDownloadController = $file_download_controller;
  }

  /**
   * Downloads a tarball of the site configuration.
   */
  public function downloadExport($exportables) {
    $files = array();
    file_unmanaged_delete(file_directory_temp() . '/site_dump.tar.gz');
    $archiver = new ArchiveTar(file_directory_temp() . '/site_dump.tar.gz', 'gz');
    // Get items to export.
    foreach (explode(':', $exportables) as $exportable) {
      list($entity_type, $bundle, $id) = explode('-', $exportable);
      $exports = $this->getExports($entity_type, $bundle, $id);

      // Create file.
      if (count($exports)) {
        foreach ($exports as $key => $export) {
          $filename = file_directory_temp() . '/' . sprintf("site_dump.%s.%s%s.yml", $entity_type, $key, (($id > 0) ? sprintf('.%d', $id) : ''));

          $fh = fopen($filename, 'w');
          fwrite($fh, Yaml::encode($export));
          fclose($fh);
          $files[] = $filename;
        }
      }
    }
    $archiver->create($files);

    $request = new Request(array('file' => 'site_dump.tar.gz'));
    return $this->fileDownloadController->download($request, 'temporary');

//      foreach ($exports as $key => $export) {
//        $root = sprintf("site_dump.%s.%s%s", $entity_type, $key, (($id > 0) ? sprintf('.%d', $id) : ''));
//        $config = \Drupal::config($root);
//        foreach ($export as $key => $value) {
//          $config->set($key, $value);
//        }
//        $config->save();
//      }
//    } // remove me with config foreach block!!!

  }


  /**
   * Process exports.
   *
   * @param $type
   * @param $bundle
   * @param $id
   * @return array
   */
  public function getExports($type, $bundle = '', $id = '') {
    $exports = array();
    switch ($type) {
      case 'node':
        $entity_type = $type;
        if ($bundle) {
          $properties['type'] = $bundle;
        }
        break;
      case 'user':
        $entity_type = $type;
        break;
      case 'taxonomy_vocabulary':
        $entity_type = 'taxonomy_term';
        if ($bundle) {
          $properties['vid'] = $bundle;
        }
        break;
    }

    $properties['status'] = 1;

    if ($id) {
      $properties['id'] = $id;
    }

    // Get field names.
    $fields = array_keys(\Drupal::entityManager()
      ->getFieldDefinitions($entity_type, ($entity_type == 'user') ? $entity_type : $bundle));
    // Create associative array from field names.
    //$fields = array_flip($fields);

    $entities = \Drupal::entityManager()
      ->getStorage($entity_type)
      ->loadByProperties($properties);

    // @todo: better way to get users having a role.
    if ($entity_type == 'user') {
      $entities = array_filter($entities, function ($entity) use ($bundle) {
        return $entity->hasRole($bundle);
      });
    }

    foreach ($entities as $entity) {
      array_walk($fields, array($this, 'map_field_values'), $entity);
      $exports[$bundle][$entity->uuid()] = $fields;
    }

    return $exports;
  }

  /**
   * Map entity properties to array.
   *
   * @param $value
   * @param $key
   * @param $entity
   */
  public function map_field_values(&$value, $key, $entity) {
    // $value if fieldInfo object

    $field_value = $entity->{$key}->getValue()[0];
    // todo: multi-value fields.
    switch (TRUE) {
      // user roles
      case ($entity instanceof User && $key == 'roles'):
        $value = $entity->getRoles();
        break;

      case (is_array($field_value) && array_key_exists('value', $field_value)):
        $value = $field_value['value'];
        break;

      // @todo: image fields
      case (is_array($field_value) && array_key_exists('fids', $field_value)):
        $value = $field_value['fids'];
        break;

      // @todo: entityref, taxref fields
      case (is_array($field_value) && array_key_exists('target_id', $field_value)):
        $value = $field_value['target_id'];
        break;

      case (is_null($field_value) || empty($field_value)):
        $value = '';
        break;

      default:
        $value = $field_value;
    }
  }

}
