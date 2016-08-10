<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class NodeActionsForm extends ActionsFormBase {

    /**
     * The node object
     *
     * @var $node \Drupal\node\NodeInterface
     */
    protected $node;

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
        $form = parent::buildForm($form, $form_state);
        $form['item'] = array(
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => $this->t('Perform Content Direct action on %content_type: <i>%title</i>?',
                array(
                    '%content_type' => $this->node->getType(),
                    '%title' => $this->node->getTitle(),
                )
            ),
        );
        $form['nid'] = array(
            '#type' => 'hidden',
            '#name' => 'nid',
            '#value' => $this->node->id(),
        );
        if ($this->pusher->referencesEntities($this->node)) {
            drupal_set_message($this->t('Notice: This Node contains references to other entities like
                <em>Taxonomy Terms</em> or <em>Files</em>, which <strong>may</strong> need to be created on the 
                destination site to before they can be associated with the current Node.'), 'warning');
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // Verify that the given Node has been published.
        if (!$this->node->isPublished()) {
            $form_state->setErrorByName('nid', $this->t('<i>node/%nid</i> must be published before using Content Direct.',
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
        $selected_action = $form_state->getValue('content_direct_actions');
        $remote_site_id = $form_state->getValue('remote_site');
        $this->pusher->setRemoteSiteByName($remote_site_id);
        $request = NULL;
        switch ($selected_action) {
            case 'post':
                $data = $this->pusher->getEntityData($this->node);
                $request = $this->pusher->request('post', 'entity/node', array('body' => $data));
                break;
            case 'patch':
                $data = $this->pusher->getEntityData($this->node);
                $request = $this->pusher->request('patch', 'node/' . $this->node->id(), array('body' => $data));
                break;
            case 'delete':
                $request = $this->pusher->request('delete', 'node/' . $this->node->id());
                break;
        }
        if ($request) {
            // Create an ActionLog Entity to track Content Direct requests made.
            $this->createActionLog($this->node->id(), 'node', $remote_site_id, $selected_action, $form_state->getValue('note'));
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
