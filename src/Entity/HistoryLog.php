<?php

namespace Drupal\content_direct\Entity;

use Drupal\content_direct\HistoryLogInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the History Log entity.
 *
 * @ContentEntityType(
 *   id = "history_log",
 *   label = @Translation("History Log"),
 *   handlers = {
 *     "list_builder" = "Drupal\content_direct\Controller\HistoryLogListBuilder",
 *     "access" = "Drupal\content_direct\HistoryLogControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\content_direct\Form\HistoryLogDeleteForm",
 *     },
 *   },
 *   base_table = "content_direct_history_log",
 *   admin_permission = "administer_content_direct",
 *   list_cache_contexts = { "user" },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "delete-form" = "/admin/config/content_direct/history_log/{history_log}/delete",
 *   }
 * )
 */
class HistoryLog extends ContentEntityBase implements HistoryLogInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'user_id' => \Drupal::currentUser()->id(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the History Log entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the History Log entity.'))
      ->setReadOnly(TRUE);

    // User field.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The Name of the associated user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    // Target Entity Type field.
    $fields['target_entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target Entity Type'))
      ->setDescription(t('The entity type be acted on.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ));

    // Target Entity ID.
    $fields['target_entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target Entity ID'))
      ->setDescription(t('The entity id be acted on.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ));

    // The Content Direct action.
    $fields['action'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Action'))
      ->setDescription(t('The action taken.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ));

    // @TODO: Change this to entity reference?
    // The Remote Site.
    $fields['remote_site'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote Site'))
      ->setDescription(t('The Remote Site type be acted on.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ));

    // Note field.
    $fields['note'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Note'))
      ->setDescription(t('Briefly describe the changes you have made.'));

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code of ContentEntityExample entity.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
