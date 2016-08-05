<?php

namespace Drupal\content_direct\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for routes.
 */
class ContentDirectController extends ControllerBase {

    /**
     * System Manager Service.
     *
     * @var \Drupal\system\SystemManager
     */
    protected $systemManager;

    /**
     * Constructs a new ContentDirectController.
     *
     * @param \Drupal\system\SystemManager $systemManager
     *   System manager service.
     */
    public function __construct(SystemManager $systemManager) {
        $this->systemManager = $systemManager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('system.manager')
        );
    }

    /**
     * Provides a single block from the administration menu as a page.
     */
    public function adminMenuPage() {
        return $this->systemManager->getBlockContents();
    }


}