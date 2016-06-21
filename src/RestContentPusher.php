<?php

namespace Drupal\content_direct;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\Serializer\SerializerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\BadResponseException;
use Drupal\Core\Path\AliasManager;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Html;

/**
 * Content Pusher service.
 */
class RestContentPusher implements ContentPusherInterface{

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
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

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

  /**
   * The alias manager service.
   *
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $alias_manager;

  /**
   * Content Direct configuration.
   */
  protected $settings;

  /**
   * CSFR Token.
   */
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
      Connection $connection,
      LoggerChannelFactoryInterface $logger_factory,
      ModuleHandlerInterface $module_handler,
      ContainerAwareEventDispatcher $event_dispatcher,
      SerializerInterface $serializer,
      ClientInterface $http_client,
      AliasManager $alias_manager
  ) {
    $this->configFactory = $config_factory;
    $this->connection = $connection;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->serializer = $serializer;
    $this->httpClient = $http_client;
    $this->alias_manager = $alias_manager;
    $this->settings = $this->configFactory->get('content_direct.settings');
    $this->token = $this->getToken();
  }

  /**
   * Get request data from a Node object.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The Node.
   *
   * @return string
   *   Return a json string.
   */
  public function getNodeData(Node $node) {
    $serialized_node = $this->serializer->serialize($node, $this->settings->get('format'));
    $data = json_decode($serialized_node);
    // Remove fields which might create permissions issues on the remote.
    foreach($data as $key => $value) {
      if (in_array($key, $this->ignore_fields)) {
        unset($data->$key);
      }
    }
    // Further clean the data by removing revision_uid from _embedded
    if (property_exists($data, '_embedded')) {
      foreach ($data->_embedded as $key => $value) {
        // Test if the key ends with the string.
        if (substr_compare($key, 'revision_uid', -12, 12) === 0) {
          unset($data->_embedded->$key);
        }
      }
    }
    
    // Manually attach the path alias
    $alias = $this->alias_manager->getAliasByPath('/node/' . $node->id());
    if ($alias) {
      $data->path = array(
          (object) array('alias' => $alias),
      );
    }
    $json = json_encode($data);
    return $this->replaceHypermediaLinks($json);
  }

  /**
   * Get file entities referenced in a Node's fields.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The Node.
   *
   * @return array
   *   Array of file objects.
   */
  public function getFiles(Node $node) {
    $fids = $this->connection->query("SELECT fid FROM {file_usage} WHERE type = 'node' AND id = :nid", array(':nid' => $node->id()))->fetchCol();
    return File::loadMultiple($fids);
  }

  /**
   * Get request data from a File object with Base64 encoded file.
   *
   * @param \Drupal\file_entity\Entity\FileEntity $file
   *   The File.
   *
   * @return string
   *   Return a json string.
   */
  public function getFileData(File $file) {
    //$serialized_file = $this->serializer->serialize($file, $this->settings->get('format'), array('included_fields' => array('data')));
    $serialized_file = $this->serializer->serialize($file, $this->settings->get('format'));
    // Unset the data property that was set on the file object when serialized.
    unset($file->data);
    // Remove fields which might create permissions issues on the remote.
    $data = json_decode($serialized_file);
    // Remove fields which might create permissions issues on the remote.
    foreach($data as $key => $value) {
      if (in_array($key, $this->ignore_fields)) {
        unset($data->$key);
      }
      // Also remove status property from file.
      if ($key == 'status') {
        unset($data->$key);
      }
    }
    $json = json_encode($data);
    return $this->replaceHypermediaLinks($json);
  }

  /**
   * Get taxonomy terms referenced in a Node's fields.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The Node.
   *
   * @return array
   *   Array of taxonomy term objects.
   */
  public function getTerms(Node $node) {
    $tids = $this->connection->query('SELECT tid FROM {taxonomy_index} WHERE nid = :nid', array(':nid' => $node->id()))->fetchCol();
    return Term::loadMultiple($tids);
  }

  /**
   * Get request data from a Term object.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   The Term.
   *
   * @return string
   *   Return a json string.
   */
  public function getTermData(Term $term) {
    $serialized_term = $this->serializer->serialize($term, $this->settings->get('format'));
    $data = json_decode($serialized_term);
    // Remove fields which might create permissions issues on the remote.
    foreach($data as $key => $value) {
      if (in_array($key, $this->ignore_fields)) {
        unset($data->$key);
      }
    }
    // Manually attach the path alias
    $alias = $this->alias_manager->getAliasByPath('/taxonomy/term/' . $term->id());
    if ($alias) {
      $data->path = array(
          (object) array('alias' => $alias),
      );
    }

    $json = json_encode($data);
    return $this->replaceHypermediaLinks($json);
  }

  /**
   * Replace the hypermedia link_domain with the "remote" domain from Content Direct settings.
   *
   * @param string $json
   *   The HAL+JSON payload.
   *
   * @return string
   *   Return a json string.
   */
  public function replaceHypermediaLinks($json) {
    // @TODO: can this be accomplished using setLinkDomain() in Drupal\rest\LinkManager\LinkManager or in link_domain in config?
    $local_protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === TRUE ? 'https' : 'http';
    $find_link = $local_protocol . ':\/\/' . $_SERVER['SERVER_NAME'];
    $replace_link = $this->settings->get('protocol') . ':\/\/' . $this->settings->get('host');
    return str_replace(
        $find_link,
        $replace_link,
        $json
    );
  }

  /**
   * Make an HTTP Request to verify the existence of an Entity.
   *
   * @param string $entity_type
   *   The Entity type
   *
   * @param string $entity_id
   *   The Entity id
   *
   * @return bool
   *   The Entity exists
   */
  public function remoteEntityExists($entity_type, $entity_id) {
    // @TODO: Fix problem to be updated in core 8.2 where a request to /taxonomy/term/X?_format=json returns {"message":"unacceptable format"}
    // see https://www.drupal.org/node/2449143
    // see the 8.2 fix in https://www.drupal.org/node/2730497

    // Get the Request URI based on current Entity type.
    switch (strtolower($entity_type)) {
      case 'taxonomy_term':
          $uri = is_numeric($entity_id) ? 'taxonomy/term/' . $entity_id : NULL;
        break;
      case 'node':
        $uri = is_numeric($entity_id) ? 'node/' . $entity_id : NULL;
        break;
      case 'taxonomy_vocabulary':
        // Note: taxonomy_vocabulary uses a machine name rather than numeric id form $entity_id
        $uri = 'entity/taxonomy_vocabulary/' . $entity_id;
        break;
      default:
        return FALSE;
    }

    // @TODO: Change this to a head request. See: https://www.drupal.org/node/2752325
      $response = $this->request('get', $uri);
      if ($response && $response->getStatusCode() === 200) {
        return TRUE;
      }
      else {
        return FALSE;
      }

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
//    $login = $this->httpClient->request('post', '/user/login', array_merge($options, $login_options));

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
   * @return  \GuzzleHttp\Psr7\Request $response
   * @throws RequestException
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
    // @TODO: handle taxonomy terms that don't exist on remote
    try {
      $uri = $uri . '?_format=' . $format;
      $response = $this->httpClient->request($method, $uri, $options);
      if ($response->getStatusCode() === 200) {
        // @TODO: test success in response and output message
        $this->loggerFactory->get('content_direct')
          ->notice('Request via %method request to %uri with options: %options. Got a %response_code response.',
            array(
              '%method' => $method,
              '%uri' => $uri,
              '%options' => '<pre>' . Html::escape(print_r($options, TRUE)) . '</pre>',
              '%response_code' => $response->getStatusCode(),
            ));
        drupal_set_message(t('Content Direct ' . strtoupper($method) . ' request fired.'), 'status', FALSE);
        return $response;
      }
    }
    catch (BadResponseException $exception) {
      $response = $exception->getResponse();
      drupal_set_message(t('Content Direct: Request failed due to HTTP error "%error"', array('%error' => $response->getStatusCode() . ' ' . $response->getReasonPhrase())), 'error');
      return FALSE;
    }
    catch (RequestException $exception) {
      $this->loggerFactory->get('content_pusher')
        ->error('Content Direct Error, Code: %code, Message: %message, Body: %body',
          array(
            '%code' => $exception->getCode(),
            '%message' => $exception->getMessage(),
            '%body' => '<pre>' . Html::escape($exception->getResponse()->getBody()) . '</pre>',
          ));
      drupal_set_message(t('Content Direct: %method request failed.', array('%method' => strtoupper($method))), 'error');
      return FALSE;
    }

  }

}
