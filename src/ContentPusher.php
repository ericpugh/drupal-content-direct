<?php

namespace Drupal\content_direct;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
//use GuzzleHttp\Cookie\CookieJarInterface;
//use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\Serializer\SerializerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Html;

/**
 * Content Pusher service.
 */
class ContentPusher {

  public $ignore_fields = array(
      'created',
      'changed',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'revision_translation_affected',
      'default_langcode',
      'path',
  );

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
   * Instance of Serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;
  
  /**
   * The HTTP client to fetch the data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  protected $settings;
  protected $token;

  /**
   * Constructs a new ContentPusher instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http client.
   */
  public function __construct(
      ConfigFactoryInterface $config_factory,
      LoggerChannelFactoryInterface $logger_factory,
      ModuleHandlerInterface $module_handler,
      ContainerAwareEventDispatcher $event_dispatcher,
      SerializerInterface $serializer,
      ClientInterface $http_client
  ) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->serializer = $serializer;
    $this->httpClient = $http_client;

    $this->settings = $this->configFactory->get('content_direct.settings');
    $this->token = $this->getToken();
  }

  /**
   * get request data from a Node object.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The Node.
   *
   * @return string
   *   Return a json string.
   */
  public function getData(Node $node) {
    $full_data = $this->serializer->serialize($node, $this->settings->get('format'), ['plugin_id' => 'entity']);
    $clean_data = array();
    foreach(json_decode($full_data) as $k => $v) {
      if(!in_array($k, $this->ignore_fields)) {
        $clean_data[$k] = $v;
      }
    }
    return json_encode($clean_data);
  }

  /**
   * Make an HTTP Request to retrieve the remote CSRF token.
   *
   * @return string
   *   Return CSRF token
   */
  public function getToken() {
    //@TODO try/catch
    $base_uri = $this->settings->get('protocol') . '://' . $this->settings->get('host');
    $options = array(
        'base_uri' =>  $base_uri,
        'allow_redirects' => TRUE,
        'timeout' => 5,
        'connect_timeout' => 5,
    );
    // Login with cookie.
//    $jar = new CookieJar();
//    $login_options = array(
//        "form_params" => [
//            "name"=> $this->settings->get('username'),
//            "pass"=> $this->settings->get('password'),
//            'form_id' => 'user_login_form',
//        ],
//        'cookies' => $jar,
//    );
    //$login = $this->httpClient->request('post', '/user/login', array_merge($options, $login_options));

    $token = $this->httpClient->request('get', 'rest/session/token', $options)->getBody();
    return $token->__toString();
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
    $format = $this->settings->get('format');
    $header_format = 'application/' . str_replace('_', '+', $format);
    $options = array(
      'base_uri' =>  $this->settings->get('protocol') . '://' . $this->settings->get('host'),
      'timeout' => 5,
      'connect_timeout' => 5,
      'auth' => array(
        $this->settings->get('username'),
        $this->settings->get('password'),
      ),
      'headers' => array(
        'Content-Type' => $header_format,
        'Accept' => $header_format,
          'X-CSRF-Token' => $this->token,
      ),
    );
    if (!empty($request_options)) {
      $options = array_merge($options, $request_options);
    }
    try {
      // @TODO: handle taxonomy terms that don't exist on remote
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
