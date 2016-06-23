<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\RequestException;

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
    $form['remote_site'] = array(
      '#type' => 'details',
      '#title' => $this->t('Remote Site'),
      '#open' => TRUE,
      '#description' => $this->t("Note: the remote server's REST settings must reflect these settings."),
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
      '#description' => $this->t("Port used by remote site's web services."),
    ];
    $form['remote_site']['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => [
        'hal_json' => 'HAL+JSON',
      ],
      '#required' => TRUE,
      '#default_value' => $settings->get('format'),
      '#description' => $this->t('Only hal+json is supported at this time.'),
    ];

    $form['basic_authentication'] = array(
      '#type' => 'details',
      '#title' => $this->t('Baisic Authentication'),
      '#open' => TRUE,
      '#description' => $this->t("Note: the remote server's REST settings must reflect these settings."),
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

    // @TODO: add options to select which types of content to push

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate that the provided "Remote Site" settings are a valid HTTP accessible Drupal installation.
    try {
      $client = \Drupal::httpClient();
      $url_parts = array(
          'scheme' => $form_state->getValue('protocol'),
          'host' => $form_state->getValue('host'),
          'port' => $form_state->getValue('port'),
      );
      $uri = $url_parts['scheme'] . '://' . $url_parts['host'];
      if (!empty($url_parts['port'])) {
        $uri .= ':' . $url_parts['port'];
      }
      $request = $client->request('get', $uri);
      $generator_header = $request->getHeaderLine('X-Generator');
      if ($request->getStatusCode() && strpos($generator_header, 'Drupal') === FALSE) {
        $form_state->setErrorByName('host', $this->t('<i>%host</i> is inaccessible or not a Drupal installation.',
            array(
                '%host' => $form_state->getValue('host'),
            )
        ));
      }
    }
    catch (RequestException $exception) {
      $form_state->setErrorByName('host', $this->t('<i>%host</i> is inaccessible or not a Drupal installation. %error',
          array(
              '%host' => $form_state->getValue('host'),
              '%error' => $exception->getMessage(),
          )
      ));
    }
    // @TODO: Validate given authentication method against remote site.
    // @TODO: Also validate REST settings and provide feedback if mismatched local settings.

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
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
