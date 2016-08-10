<?php

namespace Drupal\content_direct\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\content_direct\RestContentPusher;

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
     * The entity query.
     *
     * @var \Drupal\Core\Entity\Query\QueryFactory
     */
    protected $entityQuery;

    /**
     * The route match service.
     *
     * @var \Drupal\Core\Routing\RouteMatchInterface;
     */
    protected $routeMatch;

    /**
     * The Target Entity ID used to filter list.
     *
     * @var string $entity_id_filter
     */
    protected $entity_id_filter;

    /**
     * {@inheritdoc}
     */
    public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
        return new static(
            $entity_type,
            $container->get('entity.manager')->getStorage($entity_type->id()),
            $container->get('url_generator'),
            $container->get('date.formatter'),
            $container->get('entity.query'),
            $container->get('current_route_match')
        );
    }

    /**
     * Constructs a new HistoryListBuilder object.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     * @param \Drupal\Core\Entity\EntityStorageInterface $storage
     *   The entity storage class.
     * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
     *   The url generator.
     */
    public function __construct(
            EntityTypeInterface $entity_type,
            EntityStorageInterface $storage,
            UrlGeneratorInterface $url_generator,
            DateFormatterInterface $date_formatter,
            QueryFactory $entity_query,
            RouteMatchInterface $route_match) {
        parent::__construct($entity_type, $storage);
        $this->urlGenerator = $url_generator;
        $this->dateFormatter = $date_formatter;
        $this->entityQuery = $entity_query;
        $this->routeMatch = $route_match;

        // If exists, get the entity from params which can be used to filter entity query.
        foreach (RestContentPusher::SUPPORTED_ENTITY_TYPES as $type) {
            $entity = $this->routeMatch->getParameter($type);
            if ($entity) {
                $this->entity_id_filter = $entity->id();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load() {
        $entity_query = $this->entityQuery->get('action_log');
        if ($this->entity_id_filter) {
            $entity_query->condition('target_entity_id', $this->entity_id_filter);
        }
        $header = $this->buildHeader();
        $entity_query->pager(50);
        $entity_query->tableSort($header);
        $ids = $entity_query->execute();
        return $this->storage->loadMultiple($ids);
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
        $header['date'] = [
            'data' => $this->t('Date'),
            'field' => 'changed',
            'specifier' => 'changed',
        ];
        $header['user'] = $this->t('User');
        $header['action'] = [
            'data' => $this->t('Action'),
            'field' => 'action',
            'specifier' => 'action',
        ];
      $header['content_type'] = $this->t('Content Type');
      $header['content'] = $this->t('Content');
        $header['remote_site'] = $this->t('Remote Site');
        $header['note'] = $this->t('Note');

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
        $row['action'] = strtoupper($entity->action->value);
        $target_entity = entity_load($entity->target_entity_type->value, $entity->target_entity_id->value);
        if ($target_entity) {
          $row['content_type'] = $target_entity->getEntityType()->getLabel();
          $row['content']['data'] = array(
            '#type' => 'link',
            '#url' => $target_entity->toUrl(),
            '#title' => $target_entity->label(),
          );
        }
        $row['remote_site'] = $entity->remote_site->value;
        $row['note'] = $entity->note->value;

        return $row + parent::buildRow($entity);
    }

}
