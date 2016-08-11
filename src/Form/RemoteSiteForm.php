<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to create a RemoteSite Entity.
 */
class RemoteSiteForm extends EntityForm {

  /**
   * Construct a RemoteSiteForm.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   */
  public function __construct(QueryFactory $entity_query) {
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $remote_site = $this->entity;

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $remote_site->label(),
      '#description' => $this->t("Label for the Remote Site."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $remote_site->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exist'),
      ),
      '#disabled' => !$remote_site->isNew(),
    );
    $form['protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#options' => [
        'https' => 'https',
        'http' => 'http',
      ],
      '#required' => TRUE,
      '#default_value' => $remote_site->get('protocol'),
    ];
    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $remote_site->get('host'),
      '#description' => $this->t('Hostname of the remote site.'),
    ];
    $form['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Port'),
      '#size' => 8,
      '#default_value' => $remote_site->get('port'),
      '#description' => $this->t("Port used by remote site's web services."),
    ];
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => [
        'hal_json' => 'HAL+JSON',
      ],
      '#required' => TRUE,
      '#default_value' => $remote_site->get('format'),
      '#description' => $this->t('Only hal+json is supported at this time.'),
    ];

    $form['basic_authentication'] = array(
      '#type' => 'details',
      '#title' => $this->t('Baisic Authentication'),
      '#open' => TRUE,
      '#description' => $this->t("Note: User must have the proper REST permissions on the remote server."),
    );
    $form['basic_authentication']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $remote_site->get('username'),
      '#description' => $this->t('Username.'),
    ];
    $form['basic_authentication']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $remote_site->get('password'),
      '#description' => $this->t('Password.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $remote_site = $this->entity;
    $status = $remote_site->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label Remote Site.', array(
        '%label' => $remote_site->label(),
      )));
    }
    else {
      drupal_set_message($this->t('The %label Example was not saved.', array(
        '%label' => $remote_site->label(),
      )));
    }

    $form_state->setRedirect('entity.remote_site.collection');
  }

  /**
   * Check if an entity exists give an id.
   *
   * @return bool
   *   The RemoteSite entity exists.
   */
  public function exist($id) {
    $entity = $this->entityQuery->get('remote_site')
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
