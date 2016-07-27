<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormStateInterface;
//use Drupal\file\Entity\File;
use Drupal\file_entity\FileEntityInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class ContentDirectFileActionsForm extends ContentDirectActionsFormBase {

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
        return 'content_direct_term_actions';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, FileEntityInterface $file = NULL) {
        $this->file = $file;
        $this->prepareForm($this->file);
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
        switch ($selected_action) {
            case 'post':
                $data = $this->pusher->getFileData($this->file);
                $this->pusher->request('post', 'entity/file', array('body' => $data));
                break;
            case 'patch':
                $data = $this->pusher->getFileData($this->file);
                $this->pusher->request('patch', 'file/' . $this->file->id(), array('body' => $data));
                break;
            case 'delete':
                $this->pusher->request('delete', 'file/' . $this->file->id());
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
        $form_state->setRedirectUrl($this->file->toUrl());
    }

}
