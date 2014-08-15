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
//    $values = array(
//      'title' => 'this is my test ct page',
//      'created' => '2014-07-14 08:14:59',
//      'uid' => 'admin',
//      'promote' => 1,
//      'sticky' => 0,
//      'type' => 'test_ct',
//      'body' => 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
//      'field_my_country' => 'PL',
//      'field_friends' => array(
//        'Jan Nowak',
//        'Adam Kowalski',
//        'Janina Kowalska'
//      ),
//      'field_home_address' => array(
//        'streetAddress' => 'ul Kosciuszki 6/16',
//        'addressLocality' => 'Goleniow',
//        'addressRegion' => 'Zachodniopomorskie',
//        'postalCode' => '72-100',
//        'postOfficeBoxNumber' => '',
//        'addressCountry' => 'PL',
//      ),
//    );
//
//    $node = entity_create('node', $values);
//    var_dump($node->save());

    $importables = $this->components();

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
  public function submitForm(array &$form, array &$form_state) {


    drupal_set_message(t('Import of selected component(s) done.'));
  }

  /**
   * Components to import from YML files in the files directory.
   *
   * @todo: core CMI???
   * @return array
   */
  function components() {
    $importables = array();
    $path = DRUPAL_ROOT . '/sites/default/files/site_dump/import/yml';
    if ($handle = opendir($path)) {
      while (FALSE !== ($file = readdir($handle))) {
        // Don't include ., .., files beginning with .
        if ($file != "." && $file != ".." && $file[0] != '.') {
          $frags = explode('.', $file);
          if (array_pop($frags) == 'yml') {
            $key = sprintf('%s--%s%s', $frags[1], $frags[2], (isset($frags[3]) ? sprintf('--%s', $frags[3]) : ''));
            $importables[$key] = sprintf("(%s%s) %s", $frags[1], (isset($frags[3]) ? sprintf('-%s', $frags[3]) : ''), $frags[2]);
          }
        }
      }
      closedir($handle);
    }
    return $importables;
  }

}
