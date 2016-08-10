<?php

namespace Drupal\content_direct\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Provides a form for executing Content Direct actions.
 */
class MenuLinkContentActionsForm extends ActionsFormBase {

    /**
     * The menu_link_content object
     *
     * @var $node \Drupal\menu_link_content\Entity\MenuLinkContent
     */
    protected $menu_link_content;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'content_direct_menu_link_content_actions';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, MenuLinkContentInterface $menu_link_content = NULL) {
        $this->menu_link_content = $menu_link_content;
        $form = parent::buildForm($form, $form_state);
        $form['item'] = array(
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => $this->t('Perform Content Direct action on %menu_name menu: <i>%title</i>?',
                array(
                    '%menu_name' => $this->menu_link_content->getMenuName(),
                    '%title' => $this->menu_link_content->getTitle(),
                )
            ),
        );
        $form['id'] = array(
            '#type' => 'hidden',
            '#name' => 'id',
            '#value' => $this->menu_link_content->id(),
        );
        return $form;
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
                $data = $this->pusher->getEntityData($this->menu_link_content);
                $request = $this->pusher->request('post', '/entity/menu_link_content', array('body' => $data));
                break;
            case 'patch':
                $data = $this->pusher->getEntityData($this->menu_link_content);
                $request = $this->pusher->request('patch', '/admin/structure/menu/item/' . $this->menu_link_content->id() . '/edit', array('body' => $data));
                break;
            case 'delete':
                $request = $this->pusher->request('delete', '/admin/structure/menu/item/' . $this->menu_link_content->id() . '/edit');
                break;
        }
        if ($request) {
            // Create an ActionLog Entity to track Content Direct requests made.
            $this->createActionLog($this->menu_link_content->id(), 'menu_link_content', $remote_site_id, $selected_action, $form_state->getValue('note'));
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
        $form_state->setRedirectUrl($this->menu_link_content->toUrl());
    }

}
