<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to delete an History Log.
 */

class HistoryLogDeleteForm extends ContentEntityConfirmFormBase {

    /**
     * {@inheritdoc}
     */
    public function getQuestion() {
        return $this->t('Are you sure you want to delete this log item?');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl() {
        return new Url('content_direct.history_log');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText() {
        return $this->t('Delete');
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $entity = $this->getEntity();
        $entity->delete();
        drupal_set_message($this->t('History log item has been deleted.'));

        $form_state->setRedirectUrl($this->getCancelUrl());
    }
}
