<?php

namespace Drupal\content_direct;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a HistoryLog entity.
 *
 * @ingroup content_direct
 */
interface HistoryLogInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
