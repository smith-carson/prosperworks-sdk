<?php namespace ProsperWorks;

use Doctrine\Common\Inflector\Inflector;
use GuzzleHttp\Client;
use ProsperWorks\Endpoints\Endpoint;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * CRM API entry-point.
 * You can access all resources through magic static methods, and even run some calls directly through this class:
 * if you call the static resources with an ID it will run {@link Endpoint::find()}, and an array triggers
 * {@link Endpoint::search()}. Plural calls will run {@link Endpoint::all()}.
 *
 * A word of warning: the ProsperWorks API has some sort of caching/lag between WRITE requests. Thus, you may find
 * some inconsistencies if you POST or DELETE some information and then tries to GET/search the same resources quickly
 * after that. It seems that PUT requests are not affected by this issue.
 *
 * Examples:
 *
 * <code>
 *   $account = CRM::account();     //simple account request
 *   $people = CRM::people();       //irregular plural-based all() request
 *   $tasks = CRM::tasks();         //regular s plural-based all() request
 *   $companies = CRM::companies(); //regular y plural-based all() request
 *   $people = CRM::person()->create(['name' => 'xxx']);
 *   $people = CRM::person()->find(10);
 *   $people = CRM::person()->all();
 *   $people = CRM::person()->edit(25, ['name' => 'xxx']);
 *   $people = CRM::person()->delete(10);
 *   $people = CRM::person()->search();
 *   $people = CRM::person(23); //magic find call
 *   $people = CRM::person(['country' => 'US']); //magic search call
 *   $tasks = CRM::task()->related(10)->all();
 *   $task_projects = CRM::task()->related(22)->projects();
 *   $task_project = CRM::task()->related(22)->create(10, 'project');
 *   $task_project = CRM::task()->related(22)->delete(27, 'project');
 * </code>
 *
 * @method static object account() This is a special resource with only one method; it contains signed user basic data
 * @method static object[] companies()
 * @method static object[] leads()
 * @method static object[] opportunities()
 * @method static object[] people()
 * @method static object[] users()
 * @method static object[] tasks()
 * @method static object[] projects()
 * @method static object[] activities()
 * @method static object[] webhooks() You should probably use the {@link Webhooks} class instead.
 * @method static \ProsperWorks\Endpoints\Endpoint company($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint lead($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint opportunity($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint person($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint user($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint task($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint project($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint activity($idOrSearch = null)
 * @method static \ProsperWorks\Endpoints\Endpoint webhook($idOrSearch = null) You should probably use the {@link Webhooks} class instead.
 *
 * @todo   write tests to verify all operations work as expected!! many options! :O
 * @author igorsantos07
 */
abstract class CRM
{
    const MAX_TAG_LENGTH = 50;

    const RES_ACCOUNT     = 'Account';
    const RES_COMPANY     = 'Company';
    const RES_LEAD        = 'Lead';
    const RES_OPPORTUNITY = 'Opportunity';
    const RES_PERSON      = 'Person';
    const RES_USER        = 'User';
    const RES_TASK        = 'Task';
    const RES_PROJECT     = 'Project';
    const RES_ACTIVITY    = 'Activity';

	static $container;

    public static function client()
    {
        static $client;

        self::$container = [];
		$history = Middleware::history(self::$container);

		$stack = HandlerStack::create();
		// Add the history middleware to the handler stack.
		$stack->push($history);

        if (!$client) {
            $client = new Client([
                'base_uri' => 'https://api.prosperworks.com/developer_api/v1/',
                'headers' => [
                    'X-PW-Application' => 'developer_api',
                    'X-PW-UserEmail' => Config::email(),
                    'X-PW-AccessToken' => Config::token(),
                    'Content-Type' => 'application/json'
                ],
                'handler' => $stack
            ]);
        }
        return $client;
    }

    public static function __callStatic($name, $args)
    {
        return static::getResource($name, $args);
    }

    /**
     * Returns a Endpoint object or fancy results given the additional arguments list.
     * @see Resource::__invoke()
     * @param string $name The resource name; if plural gives you the list of entries, if singular, the Endpoint object.
     * @param array  $args See {@link Endpoint::__invoke()} for details on this.
     * @return Endpoints\Endpoint|object|object[]
     */
    public static function getResource($name, array $args = null)
    {
        switch ($name) {
            case 'account':
                return (new Endpoint($name, static::client()))->all();

            case 'accounts':
                throw new \BadMethodCallException('Whoops, you got only one account to find(). Did you mean "users"?');
        }

        $singular = Inflector::singularize($name);
        $resource = new Endpoint($singular, static::client());

        if ($name == $singular) {
            //if the resource called is singular, then run operations or returns the resource for further work
            return $args ? $resource(...$args) : $resource;
        } else {
            //if plural, runs Endpoint::all()
            return $resource->all();
        }
    }

    /**
     * Returns an ID-indexed list of internal fields, instead of the plain API list.
     * @param string     $resource camelCased, singular resource name, such as: customFieldDefinition, contactType,
     *                             customerSource, activityType, leadStatus, lossReason, pipeline, pipelineState, team
     * @param int|string $search   If given, will look for the said ID/Value and return the correspondant Value/ID
     * @param bool       $detailed If true will return an associative array with details instead of simply the name
     * @return object[]|object|string|int|null If no search key is given, returns the array, indexed by IDs. If search
     *                             is given could return either the int ID if a value is asked, a string/object if the
     *                             ID is given, or null if nothing is found.
     * @todo the $search argument could be removed to simplify this method, if there's only one usage for it in the end: CustomField::__construct (change docs)
     */
    public static function fieldList(string $resource, $search = null, bool $detailed = false)
    {
        $keyBase = "prosperworks::$resource::";
        $key = $keyBase . ($detailed ? 'details' : 'list');

        $lifetime = 60 * 60; //one hour at least
        $array = Config::cacheGetSet($key, function () use ($keyBase, $resource, $detailed, $lifetime) {
            $list = Config::cacheGetSet("{$keyBase}raw", function () use ($resource) {
                //it's good to cache the raw response as well as list and detailed responses are generated by demand
                return CRM::getResource($resource)->all();
            }, $lifetime);

            if ($resource == 'activityType') { //yet another API inconsistency to deal with
                $list = array_merge($list->user, $list->system);
            }

            // API does not return single element array when only one object in the response.
            // The code after this assumes array and this is not always the case.
            // This definitely requires PHP7
            if(!is_array($list)) { $list = [$list]; }

            $result = array_column($list, $detailed ? null : 'name', 'id');

            if ($detailed && $resource == 'customFieldDefinition') {
                array_walk($result, function (&$field) {
                    if (isset($field->options) && $field->options) {
                        $field->options = array_column($field->options, 'name', 'id');
                    }
                });
            }

            return $result;
        }, $lifetime);

        if ($search) {
            if (is_numeric($search)) {
                return $array[$search] ?? null;
            } else {
                if ($detailed) {
                    return array_filter($array, function ($f) use ($search) { return $f->name == $search; }) ?? null;
                } else {
                    //if the search key is a string we got to flip it first
                    return array_flip($array)[$search] ?? null;
                }
            }
        } else {
            return $array;
        }
    }

    /**
     * Formats a piece of text given the final tag results on PW (based on letter case and length).
     * @param string $str
     * @return string
     */
    public static function tagify(string $str): string
    {
        return strtolower(substr($str, 0, CRM::MAX_TAG_LENGTH));
    }
}
