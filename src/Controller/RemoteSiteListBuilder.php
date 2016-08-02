<?php

namespace Drupal\content_direct\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Remote Sites.
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
