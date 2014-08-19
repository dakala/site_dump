<?php

/**
 * @file
 * Contains \Drupal\config\Form\ConfigExportForm.
 */

namespace Drupal\site_dump\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;


/**
 * Defines the configuration export form.
 */
class SiteDumpExportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
//    $fields = \Drupal::entityManager()->getFieldDefinitions('node', 'test_ct');
//    var_dump($fields['field_my_image']->getType());
//    var_dump($fields['field_my_image']->getType());

    $form['description'] = array(
      '#markup' => '<p>' . $this->t('Use the export button below to download selected items.') . '</p>',
    );

    $form['convert'] = array(
      '#type' => 'details',
      '#title' => t('CSV > YAML'),
      '#open' => TRUE,
    );
    $form['convert']['convert-submit'] = array(
      '#type' => 'submit',
      '#value' => t('Convert CSV to YAML files'),
      '#submit' => array(array($this, 'convertCSVtoYAML')),
    );

    $form['export'] = array(
      '#type' => 'details',
      '#title' => $this->t('Export'),
      '#open' => TRUE,
    );

    $form['export']['export_components'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Exportables'),
      '#options' => $this->components(),
      '#description' => $this->t('Selected items will be exported into files named in a specific format.'),
    );

    $form['export']['export-submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Export all selected'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $exportables = array_filter(array_values($form_state->getValue('export_components')));
    if (count($exportables)) {
      $form_state['redirect_route'] = array(
        'route_name' => 'site_dump.export_download',
        'route_parameters' => array('exportables' => implode(':', $exportables)),
      );
    }
    else {
      drupal_set_message(t('No components to export.'));
    }
  }

  /**
   * Array of exportable components.
   * @return array
   */
  public function components() {
    // Users
    foreach (user_roles(TRUE) as $role) {
      $components['user-' . $role->id()] = '(user) ' . $role->label();
    }
    // Terms
    foreach (Vocabulary::loadMultiple() as $vocab) {
      $components['taxonomy_vocabulary-' . $vocab->id()] = '(term) ' . $vocab->name;
    }
    // Nodes
    foreach (node_type_get_names() as $node_type => $name) {
      $components['node-' . $node_type] = '(node) ' . $name;
    }
    return $components;
  }


  /**
   * Form submit callback function to convert CSV to YAML files.
   *
   * @param array $form
   * @param array $form_state
   */
  public function convertCSVtoYAML(array &$form, FormStateInterface $form_state) {
    $files = glob(DRUPAL_ROOT . '/sites/default/files/site_dump/import/csv/*.csv');
    if (count($files)) {
      foreach ($files as $srcfile) {
        $data = array();
        $f = @fopen($srcfile, "r");
        // Get fields.
        $keys = array_map('trim', fgetcsv($f));
        while (!feof($f)) {
          $row = fgetcsv($f);
          if ($row !== FALSE && count($row) == count($keys)) {
            $rowData = array_combine($keys, $row);
            if (!isset($row['uuid']) || !drupal_strlen($row['uuid'])) {
              $rowData['uuid'] = \Drupal::service('uuid')->generate();
            }
            $data[$rowData['uuid']] = $rowData;
          }
          // TODO: upgrade to migration naming convention.
          if (count($data)) {
            switch (TRUE) {
              case (strpos($srcfile, 'pe_user_example') !== FALSE):
                $component = 'user';
                break;
              case (strpos($srcfile, 'pe_group') !== FALSE):
                $component = 'og';
                break;
              case (strpos($srcfile, 'pe_user_groups') !== FALSE):
                $component = 'og_user';
                break;
              case (strpos($srcfile, 'profile') !== FALSE):
                $component = 'profile';
                break;
              default:
                $component = 'node';
                break;
            }
          }
        }

        if (drupal_strlen($component) && strpos($srcfile, '_example') !== FALSE) {
          $frags = explode('/', $srcfile);
          $key = str_replace('_example.csv', '', array_pop($frags));
          $ymlFile = sprintf('site_dump.%s.%s.yml', $component, $key);
          // Get new path and filename.
          $pathinfo = pathinfo($srcfile);
          $dstfile = str_replace($pathinfo['basename'], $ymlFile, $srcfile);
          $dstfile = str_replace('/csv/', '/yml/', $dstfile);
          // Write data to yaml file.
          $yaml = Yaml::dump($data);
          file_put_contents($dstfile, $yaml);
          chmod($dstfile, 0777);
        }
      }
      drupal_set_message($this->t('CSVs converted to YAML'));
    }
  }

}
