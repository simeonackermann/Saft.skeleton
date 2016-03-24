<?php

namespace Saft\Skeleton\PropertyHelper;

/*
use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;
use Nette\Caching\Storages\NewMemcachedStorage;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Caching\Storages\MongoDBStorage;
use Nette\Caching\Storages\RedisStorage;
use Nette\Caching\Storages\SQLiteStorage;
use Nette\Caching\Storages\APCStorage;
*/
use Saft\Rdf\NamedNode;
use Saft\Rdf\NamedNodeImpl;
use Saft\Store\Store;

use Stash\Driver\Apc;
use Stash\Driver\Redis;
use Stash\Driver\Memcache;
use Stash\Pool;


/**
 * Encapsulates PropertyHelper related classes, ensures correct usage and helps users that way
 * to use this stuff properly.
 */
class RequestHandler
{
    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var NamedNode
     */
    protected $graph;

    /**
     * @var AbstractIndex
     */
    protected $index;

    /**
     * @var IStorage
     */
    protected $storage;

    /**
     * @param Store $store
     * @param NamedNode $graph Instance of the graph, whose ressources will be collected for the index
     */
    public function __construct(Store $store, NamedNode $graph)
    {
        $this->graph = $graph;
        $this->store = $store;
    }

    /**
     * @return array Array of string containing available property types.
     */
    public function getAvailableCacheBackends()
    {
        return array(
            'file', 'memcached', 'memory', 'mongodb', 'redis', 'sqlite'
        );
    }

    /**
     * @return array Array of string containing available property types.
     */
    public function getAvailableTypes()
    {
        return array(
            'title'
        );
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param string $action
     * @param array $payload Neccessary configuration to execute the requested action.
     * @param string $preferedLanguage Prefered language for the fetched titles
     */
    public function handle($action, $payload = array(), $preferedLanguage = "")
    {
        if (null == $this->index) {
            throw new \Exception('Please call setType before handle to initialize the index.');
        }

        $action = strtolower($action);

        /*
         * create index for all resources of a graph
         */
        if ('createindex' == $action) {
            return $this->index->createIndex();

        } elseif('fetchvalues' == $action) {
            return $this->index->fetchValues($payload, $preferedLanguage);
        }

        throw new \Exception('Unknown $action given: '. $action);
    }

    /**
     * Initializes the cache backend and storage.
     *
     * Configuration information (besides name) for each backend:
     *
     * - file
     *   - dir - Path to the store where the data to be stored.
     *
     * - memcached
     *   - host - Host of the memcached server.
     *   - port - Port of the memcached server.
     *
     * - memory - No additional configuration needed.
     *
     * - sqlite
     *   - path - Full path to the sqlite file.
     *
     * @param array $configuration
     * @throws \Exception if parameter $configuration is empty
     * @throws \Exception if parameter $configuration does not have key "name" set
     * @throws \Exception if an unknown name was given.
     */
    public function setupCache(array $configuration)
    {
        if (0 == count(array_keys($configuration))) {
            throw new \Exception('Parameter $configuration must not be empty.');
        } elseif (false === isset($configuration['name'])) {
            throw new \Exception('Parameter $configuration does not have key "name" set.');
        }

        /*
        // Create Driver with default options
        $driver = new FileSystem(array());

        // Inject the driver into a new Pool object.
        $pool = new Pool($driver);

        // New Items will get and store their data using the same Driver.
        $item = $pool->getItem('path/to/data');
        */

        switch($configuration['name']) {
            case 'apc':
                $driver = new Apc();
                break;

            case 'memcached':
                $driver = new Memcache( 
                    array('servers' => array($configuration['host'], $configuration['port']))
                );
                break;

            case 'redis':
                // ERROR: Connection closed
                $driver = new Redis(
                    array('servers' => array(gethostbyname($configuration['host']), $configuration['port']))
                );
                break;

            default:
                throw new \Exception('Unknown name given: '. $configuration['name']);
        }

        $this->cache = new Pool($driver);
    }

    /**
     * Based on given type, according index will be setup.
     *
     * @param string $type Type of the property, e.g. title. Check getAvailableTypes for more information.
     * @throws \Exception if unknown type was given.
     */
    public function setType($type)
    {
        if (null == $this->cache) {
            throw new \Exception('Please call setupCache before setType to initialize the cache environment.');
        }

        // type recognized
        if (in_array($type, $this->getAvailableTypes())) {
            switch($type) {
                case 'title':
                    $this->index = new TitleHelperIndex($this->cache, $this->store, $this->graph);
                    return;
            }
        }

        throw new \Exception('Unknown type given: '. $type);
    }

}
