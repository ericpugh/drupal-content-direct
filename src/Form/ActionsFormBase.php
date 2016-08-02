<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_direct\RestContentPusher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class ActionsFormBase extends FormBase {

    /**
     * The RestContentPusher service.
     *
     * @var $cron \Drupal\content_direct\RestContentPusher
     */
    protected $pusher;

    /**
     * The Entity object
     *
     * @var $entity \Drupal\Core\Entity\EntityInterface
     */
    protected $entity;

    /**
     * The Term exists on remote site
     *
     * @var $remote_exists string
     */
    protected $remote_exists;

    /**
     * The default Content Direct actions available
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
    public function __construct(RestContentPusher $pusher) {
        $this->pusher = $pusher;
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
            $container->get('content_direct.rest_content_pusher')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return parent::getFormId();
    }

    /**
     * Check if remote entity exists, and set actions accordingly.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The Entity.
     */
    public function prepareForm(EntityInterface $entity) {
        //$this->remote_exists = $this->pusher->remoteEntityExists($entity->getEntityTypeId(), $entity->id());
        // Change the available Content Direct actions depending on the existence of the entity on the remote site.
//        if ($this->remote_exists) {
//            unset($this->actions['post']);
//        }
//        else {
//            $this->actions = array('post' => $this->t('Create'));
//        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        // Add cancel/submit buttons and actions radios for all Content Direct actions forms.
        $form['content_direct_actions'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Action'),
            '#default_value' => key($this->actions),
            '#options' => $this->actions,
            '#weight' => 100,
        );
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
