<?php

namespace Drupal\content_direct\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Remote Sites.
 *
 * @ingroup content_direct
 */
class RemoteSiteListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Remote Site');
    $header['id'] = $this->t('Machine name');
    $header['host'] = $this->t('Host');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $this->getLabel($entity);
    $row['id'] = $entity->id();
    $row['host'] = sprintf('%s://%s:%s', $entity->get('protocol'), $entity->get('host'), $entity->get('port'));

    return $row + parent::buildRow($entity);
  }

}
