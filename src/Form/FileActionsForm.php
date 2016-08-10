<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file_entity\FileEntityInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class FileActionsForm extends ActionsFormBase {

    /**
     * The FileEntity object
     *
     * @var $term \Drupal\file_entity\FileEntityInterface
     */
    protected $file;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'content_direct_file_actions';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, FileEntityInterface $file = NULL) {
        $this->file = $file;
        $form = parent::buildForm($form, $form_state);
        $form['item'] = array(
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => $this->t('Perform Content Direct action on File: <i>%name</i>?',
                array('%name' => $this->file->getFilename())),
        );
        $form['nid'] = array(
            '#type' => 'hidden',
            '#name' => 'fid',
            '#value' => $this->file->id(),
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        return parent::validateForm($form, $form_state);
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
                $data = $this->pusher->getEntityData($this->file);
                $request = $this->pusher->request('post', 'entity/file', array('body' => $data));
                break;
            case 'patch':
                $data = $this->pusher->getEntityData($this->file);
                $request = $this->pusher->request('patch', 'file/' . $this->file->id(), array('body' => $data));
                break;
            case 'delete':
                $request = $this->pusher->request('delete', 'file/' . $this->file->id());
                break;
        }
        if ($request) {
            // Create an HistoryLog Entity to track Content Direct requests made.
            $this->createHistoryLog($this->file->id(), 'file', $remote_site_id, $selected_action, $form_state->getValue('note'));
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
        $form_state->setRedirectUrl($this->file->toUrl());
    }

}
