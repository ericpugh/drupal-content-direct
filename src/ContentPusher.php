<?php

namespace Drupal\content_direct;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use GuzzleHttp\Exception\RequestException;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Html;
use GuzzleHttp\ClientInterface;

/**
 * Content Pusher service.
 */
class ContentPusher {

  /**
   * Config Factory Service Object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger Factory Service Object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * An event dispatcher instance to use for configuration events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The HTTP client to fetch the data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a new IndependenceDayApi instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, ModuleHandlerInterface $module_handler, ContainerAwareEventDispatcher $event_dispatcher, ClientInterface $http_client) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->httpClient = $http_client;
  }



  /**
   * Create request data.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The Node.
   *
   * @return string
   *   Return a json string.
   */
  public function createData(Node $node) {
//    $document = new UsaSearchDocument($node);
//    $rawData = $document->getRawData();
//    // Let modules alter the document.
//    $this->moduleHandler->alter('usasearch_document', $rawData);
//    return $document->setRawData($rawData);
  }

  /**
   * Get the enabled entity types.
   *
   * @return array
   *   Enabled content type machine names.
   */
  public function getEnabledEntityTypes() {
//    $content_types = $this->configFactory->get('usasearch.settings')->get('content_types');
//    $enabled_content_types = array();
//    foreach ($content_types as $type => $label) {
//      if ($label) {
//        $enabled_content_types[] .= $type;
//      }
//    }
//    return $enabled_content_types;
  }

  /**
   * Make an HTTP Request.
   *
   * @param string $method
   *   The HTTP method to be used.
   * @param string $uri
   *   The URI resource to which the HTTP request will be made.
   * @param array $request_options
   *   An array of options passed directly to the request.
   *
   * @see http://gsa.github.io/slate
   * @see http://guzzle.readthedocs.org/en/5.3/quickstart.html
   *
   * @return bool
   *   Return if request successfully
   */
  public function request($method, $uri, $request_options = array()) {
    $method = strtolower($method);
    $settings = $this->configFactory->get('content_direct.settings');
    $format = $settings->get('format');
    $header_format = 'application/' . str_replace('_', '+', $format);
    $options = array(
      'base_uri' =>  $settings->get('protocol') . '://' . $settings->get('host'),
      'timeout' => 5,
      'connect_timeout' => 5,
      'auth' => array(
        $settings->get('username'),
        $settings->get('password'),
      ),
      'headers' => array(
        'Content-Type' => $header_format,
        'Accept' => $header_format,
          'X-CSRF-Token' => '',
      ),
    );
    if (!empty($request_options)) {
      $options = array_merge($options, $request_options);
    }
    try {
      $uri = $uri . '?_format=' . $format;
      $response = $this->httpClient->request($method, $uri, $options);
      if ($response) {
        $this->loggerFactory->get('content_direct')
          ->notice('Request via %method request to %uri with options: %options. Got a %response_code response.',
            array(
              '%method' => $method,
              '%uri' => $uri,
              '%options' => '<pre>' . Html::escape(print_r($options, TRUE)) . '</pre>',
              '%response_code' => $response->getStatusCode(),
            ));
        drupal_set_message(t('Content Direct Deployed'), 'status', FALSE);
        return TRUE;
      }
    }
    catch (RequestException $exception) {
      $this->loggerFactory->get('content_pusher')
        ->error('Content Direct Error, Code: %code, Message: %message, Body: %body',
          array(
            '%code' => $exception->getCode(),
            '%message' => $exception->getMessage(),
            '%body' => '<pre>' . Html::escape($exception->getResponse()->getBody()) . '</pre>',
          ));
      return FALSE;
    }

  }

}
