<?php

namespace Drupal\content_direct;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file_entity\FileEntityInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\Path\AliasManager;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\file\Entity\File;
use Drupal\content_direct\Entity\RemoteSite;
use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_direct\Entity\HistoryLog;
use Drupa\content_direct\ContentDirectLogStorage;
use Drupal\content_direct\RemoteSiteInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Executable\ExecutableException;

/**
 * Content Pusher service.
 */
class RestContentPusher implements ContentPusherInterface {

  use StringTranslationTrait;

  const SUPPORTED_ENTITY_TYPES =
    array(
      'node',
      'file',
      'taxonomy_term',
      'menu_link_content',
    );

  /**
   * User message stating the RemoteSite as a requirement.
   *
   * @var string
   */
  public $remoteSiteRequiredMessage = '';

  /**
   * Data fields which should not be added to the request payload.
   *
   * @var array
   */
  public $ignoreFields = array(
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
  protected $aliasManager;

  /**
   * Content Direct configuration entity.
   *
   * @var \Drupal\content_direct\RemoteSiteInterface
   */
  protected $remoteSite;

  /**
   * CSFR Token.
   */
  protected $token;

  /**
   * Constructs a new ContentPusher instance.
   *
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
    EventDispatcherInterface $event_dispatcher,
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
    $this->aliasManager = $alias_manager;
    $this->remoteSiteRequiredMessage = $this->t('Content Direct: Remote Site is not configured.');
  }

  /**
   * Get the entity types supported by RestContentPusher.
   *
   * @return array
   *   Supported entity type names.
   */
  public function getSupportedEntityTypes() {
    return self::SUPPORTED_ENTITY_TYPES;
  }

  /**
   * Set the Remote Site config entity given the entity.
   *
   * @param \Drupal\content_direct\RemoteSiteInterface $remoteSite
   *   The Entity.
   *
   * @return \Drupal\content_direct\RestContentPusher
   *   Return pusher service after setting the remote site.
   */
  public function setRemoteSite(RemoteSiteInterface $remoteSite) {
    $this->remoteSite = $remoteSite;
    $this->token = $this->setToken();
    return $this;
  }

  /**
   * Set the Remote Site config entity given the entity id.
   *
   * @param string $name
   *   The Entity ID.
   *
   * @return \Drupal\content_direct\RestContentPusher
   *   Return pusher service after setting the remote site.
   */
  public function setRemoteSiteByName($name) {
    $this->remoteSite = RemoteSite::load($name);
    $this->token = $this->setToken();
    return $this;
  }

  /**
   * Get the Remote Site config entity.
   *
   * @return \Drupal\content_direct\RemoteSiteInterface
   *   Return the remote site.
   */
  public function getRemoteSite() {
    return $this->remoteSite;
  }

  /**
   * Make an HTTP Request to retrieve the remote CSRF token.
   *
   * @return string
   *   Return CSRF token
   */
  public function setToken() {
    $base_uri = $this->remoteSite->get('protocol') . '://' . $this->remoteSite->get('host');
    $options = array(
      'base_uri' => $base_uri,
      'allow_redirects' => TRUE,
      'timeout' => 5,
      'connect_timeout' => 5,
    );

    $token = $this->httpClient->request('get', 'rest/session/token', $options)->getBody();
    return $token->__toString();
  }

  /**
   * Get the current CSRF token.
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * Check if an Entity references other entities via entity reference fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Entity.
   *
   * @return bool
   *   Return true if given Entity references term or file entities .
   */
  public function referencesEntities(EntityInterface $entity) {
    $references = $entity->referencedEntities();
    foreach ($references as $reference) {
      $type = $reference->getEntityTypeId();
      if ($type == 'taxonomy_term' || $type == 'file') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get request "body" data from an Entity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Entity.
   *
   * @return string
   *   Return a json string.
   */
  public function getEntityData(EntityInterface $entity) {
    if (!isset($this->remoteSite)) {
      drupal_set_message($this->remoteSiteRequiredMessage, 'error');
      throw new \Exception($this->remoteSiteRequiredMessage);
    }
    else {
      $serialized_entity = $this->serializer->serialize(
        $entity,
        $this->remoteSite->get('format')
      );

      if ($entity instanceof FileEntityInterface) {
        // Unset the data property that was set on the file object when serialized.
        unset($entity->data);
      }

      $data = json_decode($serialized_entity);
      // Remove fields which might create permissions issues on the remote.
      foreach ($data as $key => $value) {
        if (in_array($key, $this->ignoreFields)) {
          unset($data->$key);
        }
      }

      // Further changes specific to an particular Entity Type.
      if ($entity instanceof NodeInterface) {
        // Further clean the data by removing revision_uid from _embedded.
        if (property_exists($data, '_embedded')) {
          foreach ($data->_embedded as $key => $value) {
            // Test if the key ends with the string.
            if (substr_compare($key, 'revision_uid', -12, 12) === 0) {
              unset($data->_embedded->$key);
            }
          }
        }
        // Manually attach the path alias for a node.
        $alias = $this->aliasManager->getAliasByPath('/node/' . $entity->id());
        if ($alias) {
          $data->path = array(
            (object) array('alias' => $alias),
          );
        }
      }
      elseif ($entity instanceof FileEntityInterface) {
        foreach ($data as $key => $value) {
          // Remove status property from file entity.
          if ($key == 'status') {
            unset($data->$key);
          }
        }
      }
      elseif ($entity instanceof TermInterface) {
        // Manually attach the path alias for a Term.
        $alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $entity->id());
        if ($alias) {
          $data->path = array(
            (object) array('alias' => $alias),
          );
        }

      }
      // Encode and return JSON data.
      $json = json_encode($data);
      return $this->replaceHypermediaLinks($json);

    }
  }

  /**
   * Get file entities referenced in a Node's fields.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The Node.
   *
   * @return array
   *   Array of file objects.
   */
  public function getFiles(NodeInterface $node) {
    $fids = $this->connection->query("SELECT fid FROM {file_usage} WHERE type = 'node' AND id = :nid", array(':nid' => $node->id()))->fetchCol();
    return File::loadMultiple($fids);
  }

  /**
   * Get taxonomy terms referenced in a Node's fields.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The Node.
   *
   * @return array
   *   Array of taxonomy term objects.
   */
  public function getTerms(NodeInterface $node) {
    $tids = $this->connection->query('SELECT tid FROM {taxonomy_index} WHERE nid = :nid', array(':nid' => $node->id()))->fetchCol();
    return Term::loadMultiple($tids);
  }

  /**
   * Replace the hypermedia link_domain with the "remote" domain from the remote site.
   *
   * @param string $json
   *   The HAL+JSON payload.
   *
   * @return string
   *   Return a json string.
   */
  public function replaceHypermediaLinks($json) {
    // @TODO: can this be accomplished using setLinkDomain() in Drupal\rest\LinkManager\LinkManager or in link_domain in config?
    $local_protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === TRUE ? 'https' : 'http';
    $domain = $this->configFactory->get('rest.settings')->get('link_domain');
    // Get the link_domain from server variables if REST settings aren't set.
    if (empty($domain)) {
      if (!empty($_SERVER['SERVER_NAME'])) {
        $domain = $_SERVER['SERVER_NAME'];
      }
      else {
        $domain = $_SERVER['HTTP_HOST'];
      }
    }
    $find_link = $local_protocol . ':\/\/' . $domain;
    $replace_link = $this->remoteSite->get('protocol') . ':\/\/' . $this->remoteSite->get('host');
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
   *   The Entity type.
   * @param string $entity_id
   *   The Entity id.
   *
   * @return bool
   *   The Entity exists
   */
  public function remoteEntityExists($entity_type, $entity_id) {
    /* @TODO: Fix with GET request problem to be updated in core 8.2!
     * see the 8.2 fix in https://www.drupal.org/node/2730497
     * Temporary solution is to disable the default Taxonomy Term view on remote.
     */
    $uri = $this->getRemoteUri($entity_type, $entity_id);
    // @TODO: Change this to a head request. See: https://www.drupal.org/node/2752325
    if ($this->request('get', $uri)->getStatusCode() === 200) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get the Remote Entity URI.
   *
   * @param string $entity_type
   *   The Entity type.
   * @param string $entity_id
   *   The Entity id.
   * @param bool $hostname
   *   Whether to include hostname and protocol.
   *
   * @return string
   *   The remote entity uri
   */
  public function getRemoteUri($entity_type, $entity_id, $hostname = FALSE) {
    // Get the Request URI based on current Entity type.
    switch (strtolower($entity_type)) {
      case 'node':
        $uri = is_numeric($entity_id) ? 'node/' . $entity_id : NULL;
        break;

      case 'taxonomy_term':
        $uri = is_numeric($entity_id) ? 'taxonomy/term/' . $entity_id : NULL;
        break;

      case 'file':
        $uri = is_numeric($entity_id) ? 'file/' . $entity_id : NULL;
        break;

      case 'taxonomy_vocabulary':
        // Note: taxonomy_vocabulary uses a machine name rather than numeric id.
        $uri = 'entity/taxonomy_vocabulary/' . $entity_id;
        break;

      case 'menu_link_content':
        $uri = 'admin/structure/menu/item/' . $entity_id . '/edit';
        break;

      default:
        return FALSE;
    }

    if ($hostname) {
      return sprintf('%s://%s:%s/%s',
        $this->remoteSite->get('protocol'),
        $this->remoteSite->get('host'),
        $this->remoteSite->get('port'),
        $uri
      );
    }
    else {
      return $uri;
    }

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
   * @return \GuzzleHttp\Psr7\Response $response
   *   Request object
   *
   * @throws GuzzleException
   */
  public function request($method, $uri, $request_options = array()) {
    if (!isset($this->remoteSite)) {
      drupal_set_message($this->remoteSiteRequiredMessage, 'error');
      throw new \Exception($this->remoteSiteRequiredMessage);
    }
    else {
      $method = strtolower($method);
      $format = $this->remoteSite->get('format');
      $header_format = 'application/' . str_replace('_', '+', $format);
      $url_parts = array(
        'scheme' => $this->remoteSite->get('protocol'),
        'host' => $this->remoteSite->get('host'),
        'path' => $uri,
        'port' => $this->remoteSite->get('port') ? $this->remoteSite->get('port') : '80',
      );
      $options = array(
        'base_uri' => $url_parts['scheme'] . '://' . $url_parts['host'] . ':' . $url_parts['port'],
        'timeout' => 5,
        'connect_timeout' => 5,
        'auth' => array(
          $this->remoteSite->get('username'),
          $this->remoteSite->get('password'),
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
        $uri = $url_parts['path'] . '?_format=' . $format;
        $response = $this->httpClient->request($method, $uri, $options);
        // Log and output message for all Create, Update, and Delete requests.
        if ($method != 'get' && $method != 'head') {
          $status_code = $response->getStatusCode();
          // Output the response message to user.
          drupal_set_message($this->t('Content Direct: %method request sent to <i>%uri</i>. Response: %status, %phrase',
            array(
              '%method' => strtoupper($method),
              '%uri' => $uri,
              '%status' => $status_code,
              '%phrase' => $response->getReasonPhrase(),
            )
          ), 'status');
          $this->loggerFactory->get('content_direct')
            ->notice('Request via %method request to %uri with options: %options. Got a %response_code response.',
              array(
                '%method' => $method,
                '%uri' => $uri,
                '%options' => '<pre>' . Html::escape(print_r($options, TRUE)) . '</pre>',
                '%response_code' => $status_code,
              ));
        }
        return $response;
      }
      catch (RequestException $exception) {
        $this->loggerFactory->get('content_pusher')
          ->error('Content Direct Error, Code: %code, Message: %message, Body: %body',
            array(
              '%code' => $exception->getCode(),
              '%message' => $exception->getMessage(),
              '%body' => '<pre>' . Html::escape($exception->getResponse()->getBody()->getContents()) . '</pre>',
            ));
        drupal_set_message($this->t('Content Direct: %method request failed.', array('%method' => strtoupper($method))), 'error');
        return $exception->getResponse();
      }
    }
  }

}
