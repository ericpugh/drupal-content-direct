<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class TermActionsForm extends ActionsFormBase {

    /**
     * The Taxonomy Term object
     *
     * @var $term \Drupal\taxonomy\TermInterface
     */
    protected $taxonomy_term;

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
        $this->prepareForm($this->taxonomy_term);
        $form = parent::buildForm($form, $form_state);
        $form['item'] = array(
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => $this->t('Perform Content Direct action on Term: %name ?',
                array('%name' => $this->taxonomy_term->getName())),
        );
        $form['nid'] = array(
            '#type' => 'hidden',
            '#name' => 'tid',
            '#value' => $this->taxonomy_term->id(),
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
        $selected_action = $form_state->getValue('content_direct_actions');
        switch ($selected_action) {
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
