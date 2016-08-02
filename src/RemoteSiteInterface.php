<?php

namespace Drupal\content_direct;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
* Provides an interface defining a Remote Site entity.
 *
 * @ingroup content_direct
 */
interface RemoteSiteInterface extends ConfigEntityInterface {

    /**
     * Returns the Remote Site ID.
     *
     * @return string
     *   The id of the remote site.
     */
    public function getId();

    /**
     * Sets the Remote Site ID.
     *
     * @param string $id
     *   The id of the remote site.
     *
     * @return \Drupal\content_direct\RemoteSiteInterface
     *   The class instance this method is called on.
     */
    public function setId($id);

    /**
     * Returns the Remote Site label.
     *
     * @return string
     *   The label of the remote site.
     */
    public function getLabel();

    /**
     * Sets the Remote Site label.
     *
     * @param string $label
     *   The label of the remote site.
     *
     * @return \Drupal\content_direct\RemoteSiteInterface
     *   The class instance this method is called on.
     */
    public function setLabel($label);

}
