<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_direct\Entity\RemoteSite;
use Drupal\content_direct\RestContentPusher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\content_direct\Entity\HistoryLog;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Url;

/**
 * Provides a form for executing Content Direct actions.
 */
class ActionsFormBase extends FormBase {

  /**
   * The RestContentPusher service.
   *
   * @var $pusher \Drupal\content_direct\RestContentPusher
   */
  protected $pusher;

  /**
   * The entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * The Entity object.
   *
   * @var $entity \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The Term exists on remote site.
   *
   * @var $remoteExists string
   */
  protected $remoteExists;

  /**
   * The default Content Direct actions available.
   *
   * @var $actions array
   */
  protected $actions;

  /**
   * Constructs a ContentDirectTermActionsForm object.
   *
   * @param \Drupal\content_direct\RestContentPusher $pusher
   *   The RestContentPusher service.
   */
  public function __construct(RestContentPusher $pusher, QueryFactory $entity_query) {
    $this->pusher = $pusher;
    $this->entityQuery = $entity_query;
    $this->actions = array(
      'post' => $this->t('Create'),
      'patch' => $this->t('Update'),
      'delete' => $this->t('Delete'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_direct.rest_content_pusher'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return parent::getFormId();
  }

  /**
   * Create a HistoryLog Entity for each request.
   *
   * @param string|int $entity_id
   *   The Entity ID.
   * @param string $entity_type
   *   The Entity Type.
   * @param string $remote_site_id
   *   The Remote Site machine name.
   * @param string $action
   *   The action taken.
   *
   * @return \Drupal\content_direct\Entity\HistoryLog
   *   HistoryLog Entity.
   */
  public function createHistoryLog($entity_id, $entity_type, $remote_site_id, $action, $note = NULL) {
    // Check if exists.
    $query = \Drupal::entityQuery('history_log')
      ->condition('target_entity_id', $entity_id)
      ->condition('target_entity_type', $entity_type)
      ->condition('remote_site', $remote_site_id)
      ->condition('action', $action);
    $existing_logs = $query->execute();
    if ($existing_logs) {
      // Update existing log.
      $log = HistoryLog::load(end($existing_logs));
      $log->setChangedTime(time());
      $log->set('note', $note);
      $log->save();
    }
    else {
      // Create a new history log.
      $values = array(
        'target_entity_id' => $entity_id,
        'target_entity_type' => $entity_type,
        'remote_site' => $remote_site_id,
        'action' => $action,
        'note' => $note,
      );
      return HistoryLog::create($values)->save();

    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Add a select list of configured "Remote Site" config entities.
    $remote_sites = RemoteSite::loadMultiple();
    $options = array();
    foreach ($remote_sites as $name => $site) {
      $options[$name] = $site->label();
    }
    $remote_site_description = $this->t('Select the target remote site.');
    if (empty($options)) {
      $options[''] = $remote_site_description = $this->t('No remote sites defined.');
      // Add a link so the user can create a Remote Site.
      $form['remote_sites_link'] = [
        '#title' => $this->t('Configure Remote Sites'),
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.remote_site.collection'),
        '#weight' => 90,
      ];

    }
    $form['remote_site'] = [
      '#type' => 'select',
      '#title' => $this->t('Remote Site'),
      '#options' => $options,
      '#required' => TRUE,
      '#description' => $remote_site_description,
      '#weight' => 95,
    ];
    // Add action radios for all Content Direct actions forms.
    $form['content_direct_actions'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Action'),
      '#default_value' => key($this->actions),
      '#options' => $this->actions,
      '#weight' => 97,
    );
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Note'),
      '#rows' => 2,
      '#cols' => 5,
      '#weight' => 98,
    ];
    // Submit/Cancel buttons.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    );
    $form['actions']['cancel'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => array('::cancel'),
      '#limit_validation_errors' => array(),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return parent::submitForm($form, $form_state);
  }

}
