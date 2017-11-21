<?php namespace ProsperWorks\Endpoints;

use Doctrine\Common\Inflector\Inflector;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;
use ProsperWorks\Config;
use ProsperWorks\CRM;
use ProsperWorks\RateLimit;
use ProsperWorks\Resources\BareResource;
use Psr\Http\Message\ResponseInterface;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileLogger;

/**
 * Provides normalization and HTTP request methods to real resources.
 * @author igorsantos07
 */
abstract class BaseEndpoint
{
    /** How many requests to send at once at {@link requestMany}? Ten sounds like a healthy middle ground. */
    const CONCURRENCY_LIMIT = 10;

    /** @var string */
    protected $uri;

    /** @var Client */
    protected $client;

    /** @var bool If set to true will translate UNIX timestamps into DateTime objects */
    public $dateObjects = true; //FIXME: this should be on Config

    /**
     * @var array Lists all resources and their allowed methods. Useful for some types of validation.
     * @todo preemptively validate methods somewhere, to avoid 404 responses on non-existent API calls allowed by base
     *       methods and __call()
     */
    protected static $specs = [
        'account' => ['fetch'],
        'users' => ['fetch', 'find'],
        'companies' => ['create', 'find', 'edit', 'delete', 'search', 'related'],
        'leads' => ['create', 'find', 'edit', 'delete', 'search', 'upsert', 'convert', 'related'],
        'opportunities' => ['create', 'find', 'edit', 'delete', 'search', 'related'],
        'people' => ['create', 'find', 'fetch_by_email', 'edit', 'delete', 'search', 'related'],
        'tasks' => ['create', 'find', 'edit', 'delete', 'search', 'related'],
        'projects' => ['create', 'find', 'edit', 'delete', 'search', 'related'],
        'activities' => ['create', 'find', 'edit', 'delete', 'search'],
        'custom_fields' => ['fetch', 'find']
    ];

    public function __construct(string $type, Client $client = null)
    {
        $this->uri = static::normalizeName($type);
        $this->client = $client ?? CRM::client();
    }

    protected static function normalizeName(string $resource)
    {
        if ($resource == 'account') {
            return 'account'; //yep, this endpoint has no plural
        } else {
            return Inflector::tableize(Inflector::pluralize($resource));
        }
    }

    /**
     * Fluent way to set {@link $timestampObjects}. Will skip converting UNIX timestamps into \DateTime objects if
     * false.
     * @param bool $bool
     * @return static
     */
    public function useUnixTimestamps(bool $bool = true)
    {
        $this->dateObjects = !$bool;
        return $this;
    }

    public function getUri() { return $this->uri; }

    /**
     * Makes a request and parses the response body accordingly.
     * @param string              $method  HTTP method
     * @param string|\Traversable $path    URI(s) to call. Will be appended to {@link uri} (thus, if empty simply
     *                                     calls the original URI). If this is an array, two formats are accepted:
     *                                     For simple requests, an array of URIs will do. For requests with options,
     *                                     the URIs are keys and the option arrays are the values.
     * @param array               $options Guzzle request options array. Not used if $path is an array.
     * @return bool|bool[]|BareResource|BareResource[] DELETE calls return booleans, otherwise, objects.
     */
    protected function request(string $method, $path = '', array $options = [])
    {
		//TODO: replace with is_iterable() at PHP 7.1
		if (is_array($path) || $path instanceof \Traversable) {
            return $this->requestMany($method, $path);
        } else {
            if (Config::debugLevel() >= Config::DEBUG_COMPLETE) {
                echo strtoupper($method) . " $this->uri/$path " . ($options ? json_encode($options, JSON_PRETTY_PRINT) : '');
            } elseif (Config::debugLevel() >= Config::DEBUG_BASIC) {
                echo strtoupper($method) . " $this->uri/$path\n";
            }
            
            try {
				$response = $this->client->$method("$this->uri/$path", $options);
			} catch (\RuntimeException $e) {
				// Log the error and the last request info
				$error = "Exception " . $e->getMessage() . "\n";
				
				$transaction = end(CRM::$container);
				$error .= ( (string) $transaction['request']->getBody() ); // Hello World
				foreach ($transaction['request']->getHeaders() as $header) {
					$error .= print_r($header, true);
				}
				echo "Sync exception: " . $error;
			}
			
            RateLimit::do()->pushRequest();
            return $this->processResponse($response);
        }
    }

    /**
     * Runs many concurrent requests through the API at once.
     * @internal This method is not intended for open usage, it's just a sub-routine of {@link request()}.
     * @see      http://docs.guzzlephp.org/en/latest/quickstart.html#concurrent-requests
     * @param string       $method HTTP Method
     * @param \Traversable $paths  List of paths, or list of payloads indexed by paths.
     * @return bool[]|object[] DELETE calls return booleans, otherwise, objects.
     */
    protected function requestMany(string $method, $paths)
    {
		$requestGenerator = function ($uriList) use ($method) {
            foreach ($uriList as $path => $options) {
                //verifying if the array is just a simple list of paths
                if (is_integer($path) && !is_array($options)) {
                    $path = $options;
                    $options = [];
                }
                yield function () use ($method, $path, $options) {
					if ($method == 'post' && is_integer($path)) {
                        //if this is a post call, $path being an integer means plain array keys instead of IDs
                        $path = '';
                    }
                    
                    if (Config::debugLevel() >= Config::DEBUG_COMPLETE) {
                        echo strtoupper($method) . " $this->uri/$path " . ($options ? json_encode($options, JSON_PRETTY_PRINT) : '') . "\n";
                    } elseif (Config::debugLevel() >= Config::DEBUG_BASIC) {
                        echo strtoupper($method) . " $this->uri/$path\n";
                    }

                    return $this->client->{"{$method}Async"}("$this->uri/$path", $options);
                };
            }
        };

        $results = [];
        (new Pool($this->client, $requestGenerator($paths), [
            'concurrency' => static::CONCURRENCY_LIMIT,
            'fulfilled' => function (Response $response, $index) use (&$results) {
                RateLimit::do()->pushRequest();
                $results[$index] = $this->processResponse($response);
            },
            'rejected' => function (ServerException $error, $index) use (&$results) {
                RateLimit::do()->pushRequest();
                try {
                    $results[$index] = $this->processError($error);
                } catch (\Throwable $e) {
                    $results[$index] = "[{$e->getCode()}] {$e->getMessage()}";
                }
            }
        ]))->promise()->wait();

        return $results;
    }

    /**
     * Processes a response given its status code, including error codes.
     * @param ResponseInterface $response
     * @return bool|BareResource[]
     * @throws \RuntimeException With unknown or error response codes
     */
    protected function processResponse(ResponseInterface $response)
    {
        switch ($status = $response->getStatusCode()) {
            case 200:
            case 201:
                $body = $response->getBody()->getContents();
                //TODO: would be cool to have classes for each Endpoint response type
                $result = json_decode($body);

                //(array)-casting objects converts them into arrays, instead of doing like [$scalar]
                $iterator = is_array($result) ? $result : [$result];
                $entries = array_map(function($entry) { return new BareResource($entry, $this->dateObjects); }, $iterator);
                return sizeof($entries) == 1? $entries[0] : $entries;

            case 204: //haha. Their delete responses are fancier than this, they return the id deleted (duh?!).
                return true;

            default:
                $reason = $response->getReasonPhrase();
                switch (substr($status, 0, 1)) {
                    case 2:
                        throw new \RuntimeException("Unknown success behavior: $reason", $status);
                    case 4:
                        $msg = json_decode($response->getBody()->getContents())->message ?? 'no message';
                        throw new \RuntimeException("Client error: $reason ($msg)", $status);
                    case 5:
                        throw new \RuntimeException("Server error: $reason", $status);
                }
                throw new \RuntimeException("Unknown response [$status] $reason", $status);
        }
    }

    /**
     * Wrapper for simple Guzzle exceptions, passing the response through {@link processResponse}.
     * @param ClientException $error
     * @return bool|mixed
     */
    protected function processError(ClientException $error)
    {
        return $this->processResponse($error->getResponse());
    }
}
