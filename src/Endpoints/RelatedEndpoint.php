<?php

namespace ProsperWorks\Endpoints;

use GuzzleHttp\Client;
use ProsperWorks\Resources\BareResource;
use GuzzleHttp\Exception\ClientException;

/**
 * References related resource calls.
 *
 * All relationships are bidirectional, i.e. relating Endpoint A to Endpoint B is functionally equivalent to relating
 * Endpoint B to Endpoint A. Relationships can only exist between certain types of entities, and additional restrictions
 * may apply. The following are the allowed relationships between ProsperWorks resources:
 * Companies: Opportunities, People, Tasks, Projects
 * - People: Companies (limit 1), Opportunities, Tasks, Projects
 * - Opportunities: Companies, People, Tasks, Projects
 * - Leads: Tasks
 * - Tasks: Companies, People, Opportunities, Leads, Projects (limit 1 total)
 * - Projects: Companies, People, Opportunities, Tasks
 *
 * Examples:
 * <code>
 *   CRM::leads()->related($id)->all() // GET leads/$id/related
 *   CRM::leads()->related($id)->tasks() // GET leads/{id}/related/tasks
 *   CRM::leads()->related($id)->create($subId, $type) // POST leads/{id}/related { resource: { $id, $type } }
 *   CRM::leads()->related($id)->delete($subId, $type) // DELETE leads/{id}/related { resource: { $id, $type } }
 * </code>
 *
 * @method BareResource|BareResource[] companies()
 * @method BareResource|BareResource[] leads()
 * @method BareResource|BareResource[] opportunities()
 * @method BareResource|BareResource[] people()
 * @method BareResource|BareResource[] users()
 * @method BareResource|BareResource[] tasks()
 * @method BareResource|BareResource[] projects()
 * @method BareResource|BareResource[] activities()
 *
 * @see https://www.prosperworks.com/developer_api/related_items
 *
 * @todo see if it's possible to hide this inside Endpoint by abstracting it + anonymous class sorcery
 * @author igorsantos07
 */
class RelatedEndpoint extends BaseEndpoint
{
    /** @noinspection PhpMissingParentConstructorInspection
     * @param string $uri
     * @param int    $id
     * @param Client $client
     */
    public function __construct(string $uri, int $id, Client $client)
    {
        $this->uri = "$uri/$id/related";
        $this->client = $client;
    }

    /**
     * Retrieves all relationships for the current model.
     * To list only a specific type, use one of the magic methods, calling the resource type you want (i.e. <code>CRM::leads()->related($id)->tasks()</code>).
     * @return BareResource[]
     */
    public function all()
    {
        return $this->request('get');
    }

    /**
     * Creates a new relation.
     * @param int    $id   Related object ID.
     * @param string $type Related object type. One of the CRM::RES_* constants.
     * @return BareResource
     */
    public function create(int $id, string $type)
    {
        return $this->request('post', null, ['json' => ['resource' => compact('id', 'type')]]);
    }

    /**
     * Creates a new relation.
     * @param int    $id   Related object ID.
     * @param string $type Related object type. One of the CRM::RES_* constants.
     * @return bool
     */
    public function delete(int $id, string $type)
    {
		try {
			return $this->request('delete', null, ['json' => ['resource' => compact('id', 'type')]]);
		} catch ( GuzzleHttp\Exception\ClientException $e ) {
			$message = $e->getMessage();
			
			if (strpos($message, "Resources are not related") !== false) {
				return 1;
			}
		}
    }

    /**
     * Supports the magic method calls to retrieve all entries from a specific type.
     * @example CRM::leads()->related($id)->tasks()
     * @param $relatedType
     * @param $params
     * @return BareResource[]
     */
    public function __call($relatedType, $params)
    {
        return $this->request('get', $relatedType);
    }
}
