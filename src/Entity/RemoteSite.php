<?php

namespace Drupal\content_direct\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\content_direct\RemoteSiteInterface;

/**
 * Defines the Remote Site entity.
 *
 * @ConfigEntityType(
 *   id = "remote_site",
 *   label = @Translation("Remote Site"),
 *   handlers = {
 *     "list_builder" = "Drupal\content_direct\Controller\RemoteSiteListBuilder",
 *     "form" = {
 *       "add" = "Drupal\content_direct\Form\RemoteSiteForm",
 *       "edit" = "Drupal\content_direct\Form\RemoteSiteForm",
 *       "delete" = "Drupal\content_direct\Form\RemoteSiteDeleteForm",
 *     }
 *   },
 *   config_prefix = "remote_site",
 *   admin_permission = "administer_content_direct",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/content_direct/remote_site/{remote_site}",
 *     "delete-form" = "/admin/config/content_direct/remote_site/{remote_site}/delete",
 *   }
 * )
 */
class RemoteSite extends ConfigEntityBase implements RemoteSiteInterface {

  /**
   * The Remote Site ID (machine name).
   *
   * @var string
   */
  public $id;

  /**
   * The Remote Site label.
   *
   * @var string
   */
  public $label;

  /**
   * The Remote Site HTTP protocol.
   *
   * @var string
   */
  public $protocol;

  /**
   * The Remote Site host name.
   *
   * @var string
   */

  public $host;

  /**
   * The Remote Site port used.
   *
   * @var string
   */
  public $port;

  /**
   * The Remote Site format used.
   *
   * @var string
   */
  public $format;

  /**
   * The Remote Site admin username.
   *
   * @var string
   */
  protected $username;

  /**
   * The Remote Site admin password.
   *
   * @var string
   */
  protected $password;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->get('id');
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->get('id');
  }

  /**
   * {@inheritdoc}
   */
  public function setId($id) {
    $this->set('id', $id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->get('label');
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->set('label', $label);
    return $this;
  }

}
