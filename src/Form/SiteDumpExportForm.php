<?php

/**
 * @file
 * Contains \Drupal\config\Form\ConfigExportForm.
 */

namespace Drupal\site_dump\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\Yaml\Yaml;

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
  public function buildForm(array $form, array &$form_state) {
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

    $components = site_dump_get_components();
    $form['export'] = array(
      '#type' => 'details',
      '#title' => $this->t('Export'),
      '#open' => TRUE,
    );

    $form['export']['export_components'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Exportables'),
      '#options' => $components,
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
  public function submitForm(array &$form, array &$form_state) {
    // 1. generate export files one after the other - $form_state['values']['export_components']
    // 2. call exportDownload with file path param.
    $form_state['redirect_route'] = array(
      'route_name' => 'site_dump.export_download',
    );
  }

  public function convertCSVtoYAML(array &$form, array &$form_state) {
    $files = glob(DRUPAL_ROOT . '/' . drupal_get_path('module', 'site_dump') . '/import/*.csv');
    if(count($files)) {
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
          // TODO: migration naming convention.
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
          $dstfile = str_replace('/import/', '/Fixtures/', $dstfile);
          // Write data to yaml file.
          $yaml = Yaml::dump($data);
          file_put_contents($dstfile, $yaml);
          chmod($dstfile, 0777);
        }
      }
    }
    drupal_set_message($this->t('CSVs converted to YAML'));
  }

}
