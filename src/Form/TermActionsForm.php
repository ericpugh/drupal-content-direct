<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class TermActionsForm extends ActionsFormBase {

  /**
   * The Taxonomy Term object.
   *
   * @var $term \Drupal\taxonomy\TermInterface
   */
  protected $taxonomyTerm;

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
    $this->taxonomyTerm = $taxonomy_term;
    $form = parent::buildForm($form, $form_state);
    $form['item'] = array(
      '#type' => 'item',
      '#input' => FALSE,
      '#markup' => $this->t('Perform Content Direct action on Term: %name ?',
        array('%name' => $this->taxonomyTerm->getName())),
    );
    $form['nid'] = array(
      '#type' => 'hidden',
      '#name' => 'tid',
      '#value' => $this->taxonomyTerm->id(),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Verify that the Term's vocabulary exists remotely.
    $remote_site_id = $form_state->getValue('remote_site');
    $this->pusher->setRemoteSiteByName($remote_site_id);
    $vid = $this->taxonomyTerm->getVocabularyId();
    if (!$this->pusher->remoteEntityExists('taxonomy_vocabulary', $vid)) {
      $form_state->setErrorByName(
        'tid',
        $this->t('Vocabulary <i>%vid</i> does not exist on the remote site, <i>%term</i> cannot be created.',
          array(
            '%vid' => $vid,
            '%term' => $this->taxonomyTerm->getName(),
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
    $remote_site_id = $form_state->getValue('remote_site');
    $this->pusher->setRemoteSiteByName($remote_site_id);
    $request = NULL;
    switch ($selected_action) {
      case 'post':
        $data = $this->pusher->getEntityData($this->taxonomyTerm);
        $request = $this->pusher->request('post', 'entity/taxonomy_term', array('body' => $data));
        break;

      case 'patch':
        $data = $this->pusher->getEntityData($this->taxonomyTerm);
        $request = $this->pusher->request('patch', 'taxonomy/term/' . $this->taxonomyTerm->id(), array('body' => $data));
        break;

      case 'delete':
        $request = $this->pusher->request('delete', 'taxonomy/term/' . $this->taxonomyTerm->id());
        break;
    }
    if ($request) {
      // Create an HistoryLog Entity to track Content Direct requests made.
      $this->createHistoryLog($this->taxonomyTerm->id(), 'taxonomy_term', $remote_site_id, $selected_action, $form_state->getValue('note'));
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
    $form_state->setRedirectUrl($this->taxonomyTerm->toUrl());
  }

}
