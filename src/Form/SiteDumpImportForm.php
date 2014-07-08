<?php

/**
 * @file
 * Contains \Drupal\config\Form\ConfigImportForm.
 */

namespace Drupal\site_dump\Form;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Archiver\ArchiveTar;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the configuration import form.
 */
class SiteDumpImportForm extends FormBase {

  /**
   * The configuration storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Constructs a new ConfigImportForm.
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The configuration storage.
   */
  public function __construct(StorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.storage.staging')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_dump_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $importables = site_dump_discover_importables();

    switch (TRUE) {
      case (count($importables)):
        $form['import'] = array(
          '#type' => 'details',
          '#title' => $this->t('Import'),
          '#open' => TRUE,
        );

        $form['import']['import_components'] = array(
          '#type' => 'checkboxes',
          '#title' => $this->t('Importables'),
          '#options' => $importables,
          '#description' => $this->t('Selected items will be imported into this site. Best in this order: taxonomy > content_types > nodes'),
        );

        $form['import']['import-submit'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Import all selected'),
          '#submit' => array('site_dump_import_components_submit'),
        );
        break;

      default:
        $form['message']['#markup'] = $this->t('There are no importables items found.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    $file_upload = $this->getRequest()->files->get('files[import_tarball]', NULL, TRUE);
    if ($file_upload && $file_upload->isValid()) {
      $form_state['values']['import_tarball'] = $file_upload->getRealPath();
    }
    else {
      $this->setFormError('import_tarball', $form_state, $this->t('The import tarball could not be uploaded.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    if ($path = $form_state['values']['import_tarball']) {
      $this->configStorage->deleteAll();
      try {
        $archiver = new ArchiveTar($path, 'gz');
        $files = array();
        foreach ($archiver->listContent() as $file) {
          $files[] = $file['filename'];
        }
        $archiver->extractList($files, config_get_config_directory(CONFIG_STAGING_DIRECTORY));
        drupal_set_message($this->t('Your configuration files were successfully uploaded, ready for import.'));
        $form_state['redirect_route']['route_name'] = 'config.sync';
      }
      catch (\Exception $e) {
        $this->setFormError('import_tarball', $form_state, $this->t('Could not extract the contents of the tar file. The error message is <em>@message</em>', array('@message' => $e->getMessage())));
      }
      drupal_unlink($path);
    }
  }

}

