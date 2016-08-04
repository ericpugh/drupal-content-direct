<?php

namespace Drupal\content_direct;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
* Provides an interface defining a Remote Site entity.
 *
 * @ingroup content_direct
 */
interface ActionLogInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
