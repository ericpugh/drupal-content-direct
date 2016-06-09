<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for configuring Content Direct settings.
 */
class ContentDirectSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_direct_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array(
      'content_direct.settings',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $settings = $this->config('content_direct.settings');
  // @TODO: should the settings be an entity to allow multiple?

    $form['remote_site'] = array(
      '#type' => 'details',
      '#title' => $this->t('Remote Site'),
      '#open' => TRUE,
        '#description' => $this->t('Note: the remote server\'s REST settings must reflect these settings.'),
    );
    $form['remote_site']['protocol'] = [
        '#type' => 'select',
        '#title' => $this->t('Protocol'),
        '#options' => [
            'https' => 'https',
            'http' => 'http',
        ],
        '#required' => TRUE,
        '#default_value' => $settings->get('protocol'),
    ];
    $form['remote_site']['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $settings->get('host'),
      '#description' => $this->t('Hostname of the remote site.'),
    ];
    $form['remote_site']['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Port'),
      '#size' => 8,
      '#default_value' => $settings->get('port'),
      '#description' => $this->t('Port used by remote site\'s web services.'),
    ];
    $form['remote_site']['format'] = [
        '#type' => 'select',
        '#title' => $this->t('Format'),
        '#options' => [
            'json' => 'JSON',
            'hal_json' => 'HAL+JSON',
        ],
        '#required' => TRUE,
        '#default_value' => $settings->get('format'),
    ];

    $form['basic_authentication'] = array(
        '#type' => 'details',
        '#title' => $this->t('Baisic Authentication'),
        '#open' => TRUE,
        '#description' => $this->t('Note: the remote server\'s REST settings must reflect these settings.'),
    );
    $form['basic_authentication']['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#size' => 60,
        '#maxlength' => 128,
        '#required' => TRUE,
        '#default_value' => $settings->get('username'),
        '#description' => $this->t('Username.'),
    ];
    $form['basic_authentication']['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        '#size' => 60,
        '#maxlength' => 128,
        '#required' => TRUE,
        '#default_value' => $settings->get('password'),
        '#description' => $this->t('Password.'),
    ];
//    $form['basic_authentication']['token'] = [
//        '#type' => 'textfield',
//        '#title' => $this->t('Token'),
//        '#size' => 60,
//        '#maxlength' => 128,
//        '#required' => FALSE,
//        '#default_value' => $settings->get('token'),
//        '#description' => $this->t('CSRF Token. This can be retreived from the endpoint "rest/service/token"'),
//    ];

    // @TODO: add options to select which types of content to push

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @TODO: validate REST settings (e.g. Formats match) and provide feedback if mismatched local settings.
    parent::submitForm($form, $form_state);
    $this->config('content_direct.settings')
        ->set('protocol', $form_state->getValue('protocol'))
        ->set('host', $form_state->getValue('host'))
        ->set('port', $form_state->getValue('port'))
        ->set('format', $form_state->getValue('format'))
        ->set('username', $form_state->getValue('username'))
        ->set('password', $form_state->getValue('password'))
        ->set('token', $form_state->getValue('token'))
      ->save();
  }

}
