<?php

namespace Drupal\content_direct\Controller;

use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Provides a listing of logged actions.
 *
 * @ingroup content_direct
 */
class ActionLogListBuilder extends EntityListBuilder {

    /**
     * The url generator.
     *
     * @var \Drupal\Core\Routing\UrlGeneratorInterface
     */
    protected $urlGenerator;

    /**
     * The date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatterInterface
     */
    protected $dateFormatter;

    /**
     * {@inheritdoc}
     */
    public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
        return new static(
            $entity_type,
            $container->get('entity.manager')->getStorage($entity_type->id()),
            $container->get('url_generator'),
            $container->get('date.formatter')
        );
    }

    /**
     * Constructs a new ContactListBuilder object.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     * @param \Drupal\Core\Entity\EntityStorageInterface $storage
     *   The entity storage class.
     * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
     *   The url generator.
     */
    public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator, DateFormatterInterface $date_formatter) {
        parent::__construct($entity_type, $storage);
        $this->urlGenerator = $url_generator;
        $this->dateFormatter = $date_formatter;
    }

    /**
     * {@inheritdoc}
     */
    public function render() {
        $build['description'] = array(
            '#markup' => $this->t('Log of all content actions using Content Direct.'),
        );
        $build['table'] = parent::render();
        return $build;
    }

    /**
     * {@inheritdoc}
     */
    public function buildHeader() {
        $header['date'] = $this->t('Date');
        $header['user'] = $this->t('User');
        $header['action'] = $this->t('Action');
        $header['content'] = $this->t('Content');
        $header['remote_site'] = $this->t('Remote Site');

        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity) {
        $row['date'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');
        $row['user']['data'] = array(
            '#theme' => 'username',
            '#account' => $entity->getOwner(),
        );
        $row['action'] = $entity->action->value;
        $target_entity = entity_load($entity->target_entity_type->value, $entity->target_entity_id->value);
        $row['content']['data'] = array(
            '#type' => 'link',
            '#url' => $target_entity->toUrl(),
            '#title' => $target_entity->label(),
        );

        $row['remote_site'] = $entity->remote_site->value;

        return $row + parent::buildRow($entity);
    }

}
