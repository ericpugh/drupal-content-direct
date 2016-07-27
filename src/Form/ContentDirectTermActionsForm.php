<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\taxonomy\TermInterface;
use Drupal\content_direct\RestContentPusher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class ContentDirectTermActionsForm extends FormBase {

    /**
     * The RestContentPusher service.
     *
     * @var $cron \Drupal\content_direct\RestContentPusher
     */
    protected $pusher;

    /**
     * The Taxonomy Term object
     *
     * @var $term \Drupal\taxonomy\TermInterface
     */
    protected $taxonomy_term;

    /**
     * The Term exists on remote site
     *
     * @var $remote_exists string
     */
    protected $remote_exists;

    /**
     * Constructs a ContentDirectTermActionsForm object.
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
        return 'content_direct_term_actions';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, TermInterface $taxonomy_term = NULL) {

        $this->taxonomy_term = $taxonomy_term;
        $this->remote_exists = $this->pusher->remoteEntityExists('taxonomy_term', $this->taxonomy_term->id());
        $actions = array(
            'post' => $this->t('Create'),
            'patch' => $this->t('Update'),
            'delete' => $this->t('Delete'),
        );
        // Change the available Content Direct actions depending on the existence of the entity on the remote site.
        if ($this->remote_exists) {
            unset($actions['post']);
        }
        else {
            $actions = array('post' => $this->t('Create'));
        }

        $form['item'] = array(
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => $this->t('Perform Content Direct action on: %name ?',
                array('%name' => $this->taxonomy_term->getName())),
        );
        $form['content_direct_actions'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Action'),
            '#default_value' => key($actions),
            '#options' => $actions,
        );
        $form['nid'] = array(
            '#type' => 'hidden',
            '#name' => 'tid',
            '#value' => $this->taxonomy_term->id(),
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
        // Verify that the Term's vocabulary exists remotely.
        $vid = $this->taxonomy_term->getVocabularyId();
        if (!$this->pusher->remoteEntityExists('taxonomy_vocabulary', $vid)) {
            $form_state->setErrorByName(
                'tid',
                $this->t('Vocabulary <i>%vid</i> does not exist on the remote site, <i>%term</i> cannot be created.',
                    array(
                        '%vid' => $vid,
                        '%term' => $this->taxonomy_term->getName(),
                    )
                )
            );

        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $action = $form_state->getValue('content_direct_actions');
        switch ($action) {
            case 'post':
                $data = $this->pusher->getTermData($this->taxonomy_term);
                $this->pusher->request('post', 'entity/taxonomy_term', array('body' => $data));
                break;
            case 'patch':
                $data = $this->pusher->getTermData($this->taxonomy_term);
                $this->pusher->request('patch', 'taxonomy/term/' . $this->taxonomy_term->id(), array('body' => $data));
                break;
            case 'delete':
                $this->pusher->request('delete', 'taxonomy/term/' . $this->taxonomy_term->id());
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
        $form_state->setRedirectUrl($this->taxonomy_term->toUrl());
    }

}
