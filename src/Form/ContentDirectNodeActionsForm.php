<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\node\NodeInterface;
use Drupal\content_direct\RestContentPusher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class ContentDirectNodeActionsForm extends FormBase {

    /**
     * The RestContentPusher service.
     *
     * @var $cron \Drupal\content_direct\RestContentPusher
     */
    protected $pusher;

    /**
     * The node object
     *
     * @var $node \Drupal\node\NodeInterface
     */
    protected $node;

    /**
     * The node exists on remote site
     *
     * @var $remote_exists string
     */
    protected $remote_exists;

    /**
     * Constructs a ContentDirectNodeActionsForm object.
     *
     * @param \Drupal\content_direct\RestContentPusher $pusher
     *   The RestContentPusher service.
     */
    public function __construct(RestContentPusher $pusher) {
        $this->pusher = $pusher;
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
        return 'content_direct_node_actions';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

        $this->node = $node;
        $this->remote_exists = $this->pusher->remoteEntityExists('node',$node->id());
        $actions = array(
            'post' => t('Create'),
            'patch' => t('Update'),
            'delete' => t('Delete'),
        );
        // Change the available Content Direct actions depending on the existence of the entity on the remote site.
        if ($this->remote_exists) {
            unset($actions['post']);
        }
        else {
            $actions = array('post' => t('Create'));
        }

        $form['item'] = array(
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => t('Perform Content Direct action on: %title ?',
                array('%title' => $this->node->getTitle())),
        );
        $form['content_direct_actions'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Action'),
            '#default_value' => key($actions),
            '#options' => $actions,
        );
        $form['nid'] = array(
            '#type' => 'hidden',
            '#name' => 'nid',
            '#value' => $this->node->id(),
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
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // Verify that the given Node has been published.
        if (!$this->node->isPublished()) {
            $form_state->setErrorByName('nid', t('<i>node/%nid</i> must be published before using Content Direct.',
                array(
                    '%nid' => $form_state->getValue('nid'),
                )
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $action = $form_state->getValue('content_direct_actions');
//        drupal_set_message(t('Content direct executed %action on %nid',
//            array(
//                '%action' => $action,
//                '%nid' => $this->node->id(),
//            ))
//        );
        switch ($action) {
            case 'post':
                $data = $this->pusher->getNodeData($this->node);
                $this->pusher->request('post', 'entity/node', array('body' => $data));
                break;
            case 'patch':
                $data = $this->pusher->getNodeData($this->node);
                $this->pusher->request('patch', 'node/' . $this->node->id(), array('body' => $data));
                break;
            case 'delete':
                $this->pusher->request('delete', 'node/' . $this->node->id());
                break;
        }

    }

    /**
     * Form submission handler for the 'cancel' action.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function cancel(array $form, FormStateInterface $form_state) {
        $form_state->setRedirectUrl($this->node->toUrl());
    }

}
