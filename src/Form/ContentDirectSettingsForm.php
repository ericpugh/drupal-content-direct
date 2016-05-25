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
  // @TODO: should the remote site be an entity to allow multiple?
    $form['remote_site'] = array(
      '#type' => 'details',
      '#title' => $this->t('Remote Site Settings'),
      '#open' => TRUE,
    );
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

    // @TODO: add options to select which types of content to push

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('content_direct.settings')
      ->set('host', $form_state->getValue('host'))
      ->set('port', $form_state->getValue('port'))
      ->save();
  }

}
