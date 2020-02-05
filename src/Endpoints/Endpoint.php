<?php namespace ProsperWorks\Endpoints;

use ProsperWorks\Resources\BareResource;

/**
 * Represents a CRM API Endpoint.
 * @author igorsantos07
 */
class Endpoint extends BaseEndpoint
{
    /**
     * Creates a new entry at this Endpoint.
     * @param array $data
     * @return object The data as processed by the CRM.
     */
    public function create(array $data)
    {
		$relations = !empty($data['relations']) ? $data['relations'] : [];
					
		unset($data['relations']);
		
		$result = $this->request('post', '', ['json' => $data]);
		
		foreach ($relations as $relation) {
			if (!empty($relation['id'])) {
				$this->related($result->id)->create($relation['id'], $relation['type']);
			}
		}
		
        return $result;
    }

    /** @noinspection PhpInconsistentReturnPointsInspection
     * Creates a generator wrapper based on a list of entries, setting each as value of a "json" key in an array.
     * @param array|\Traversable $entries
     * @return \Generator
     */
    private function entriesJsonifier($entries)
    {
        return (function ($entries) {
            foreach ($entries as $entry) {
                yield ['json' => $entry];
            }
        })($entries);
    }

    /**
     * Creates many entries at once.
     * @param array[]|\Traversable $entries List of arrays, each defining data for one entry.
     * @return BareResource[] All entries, as processed by the CRM.
     */
    public function createMany($entries)
    {
		// first extract the relations wich should be managed through a side request
		$relations = [];
		$entriesGenerator = function($entries) use (&$relations) {
			foreach ($entries as $entry) {
				//$tm2id = null;
				
				/*foreach ($entry['custom_fields'] as $customfield) {
					if (is_object($customfield)) {
						if ($customfield->name == "TM2 ID") {
							$tm2id = $customfield->getValue();
							break;
						}
					}
				}*/
				
				if (isset($entry['relations'])) {
					//$relations[$tm2id] = $entry['relations'];
					unset($entry['relations']);
				}
				
				yield $entry;
			}
		};
		
		$results = $this->request('post', $this->entriesJsonifier($entriesGenerator($entries)));
		
		/*$pwlinkedRelations = [];
		
		foreach ($results as $result) {
			if ( !empty($result->custom_fields['TM2 ID']) && !empty($relations[$result->custom_fields['TM2 ID']->getValue()]) ) {
				$pwlinkedRelations[$result->id] = & $relations[$result->custom_fields['TM2 ID']->getValue()];
			}
		}
		
		$this->relatedBatch()->create($pwlinkedRelations);*/
		
        return $results;
    }

    /**
     * Finds one entry, per ID.
     * @param int|int[] $id
     * @return BareResource|BareResource[]
     */
    public function find(int ...$id)
    {
        return $this->request('get', $id);
    }

    /**
     * Returns all entries at this resource.
     * HACKY ALERT: Calls {@link search()} on resources that do not support plain GET calls like we do here.
     * @return BareResource[]
     */
    public function all()
    {
        try {
            return $this->request('get');
        } catch (\RuntimeException $e) {
            if ($e->getCode() == 404) {
                //aha! we may try to get all resources anyway with a sneaky empty /search call
                return $this->search();
            } else {
                throw $e;
            }
        }
    }

    /**
     * Runs /search on the resource.
     * @param array    $params Search fields to query for.
     * @param int      $size   The amount of items to return at a single query. The maximum number is 200.
     * @param int|null $page   The page to be returned. If null, loops and gets all possible pages.
     * @todo if we could get the total amount of entries we could infer the number of pages and run concurrent calls
     * @return BareResource[]
     */
    public function search(array $params = [], int $size = 200, int $page = null)
    {
        $allPages = !$page;
        $params['page_number'] = $page ?? 1;
        $params['page_size'] = ($size > 200)? 200 : $size;

        //FIXME there's some bug on the PW API that returns fewer entries than the requested...
        //so we're creating a margin to define when it should be safe to stop requesting pages
        $safeMargin = ($size > 20)? 0.9 : 0.5; //trial-and-error-based guess
        $safeLimit = floor($params['page_size'] * $safeMargin);

        $entries = [];
        do {
            $results = $this->request('post', 'search', ['json' => $params]);
            $entries = array_merge($entries, is_object($results)? [$results] : $results);
        } while ($allPages && is_array($results) && sizeof($results) >= $safeLimit && ++$params['page_number']);

        return $entries;
    }

    /**
     * Updates information in one entry.
     * @param int   $id
     * @param array $data
     * @return BareResource
     */
    public function edit(int $id, array $data)
    {
		$relations = !empty($data['relations']) ? $data['relations'] : [];
					
		unset($data['relations']);
		
		$result = $this->request('put', $id, ['json' => $data]);
		
		foreach ($relations as $relation) {
			if (!empty($relation['id'])) {
				$this->related($id)->create($relation['id'], $relation['type']);
			}
		}
		
        return $result;
    }

    /**
     * Allows to update many entries at once.
     * @param array|\Traversable $entries An array of fields to update, indexed by the entry ID
     * @return BareResource[]
     */
    public function editMany($entries)
    {
		$relations = [];
		foreach ($entries as $entry) {
			$tm2id = null;
			
			foreach ($entry['custom_fields'] as $customfield) {
				if ($customfield->name == "TM2 ID") {
					$tm2id = $customfield->getValue();
					break;
				}
			}
			
			if (isset($entry['relations'])) {
				$relations[$tm2id] = $entry['relations'];
				unset($entry['relations']);
			}
		}
		
        $results = $this->request('put', $this->entriesJsonifier($entries));
        
        foreach ($results as $result) {
			if (!empty($relations[$result->custom_fields['TM2 ID']->getValue()])) {
				foreach ($relations[$result->custom_fields['TM2 ID']->getValue()] as $relation) {
					$this->related($result->id)->create($relation['id'], $relation['type']);
				}
			}
		}
		
        return $results;
    }

    /**
     * Removes an entry.
     * @param int|int[] $id
     * @return bool|bool[]
     */
    public function delete(int ...$id)
    {
        $raw = $this->request('delete', $id);
        /** @noinspection PhpUndefinedFieldInspection */
        return is_array($raw)?
            array_map(function ($r) { return $r->is_deleted ?? false; }, $raw) :
            $raw->is_deleted;
    }

    /**
     * Returns a sub-resource, related to the current one.
     * @example CRM::tasks->related(99)->fetch(); // GET tasks/99/related
     * @example CRM::lead->relatedTasks(88)->fetch(); // GET leads/88/related/tasks
     * @see     https://www.prosperworks.com/developer_api/related_items
     *
     * @param int $id This resource's id
     * @return RelatedEndpoint
     */
    public function related(int $id)
    {
        return new RelatedEndpoint($this->uri, $id, $this->client);
    }

    public function relatedBatch()
    {
        return new BatchRelatedEndpoint($this->uri, $this->client);
    }

    /**
     * Allows for fancy fetch calls.
     * @example $leads = CRM::lead(); // GET leads
     * @example $leads = CRM::lead(int $id); // GET leads/{id}
     * @example $leads = CRM::lead(array $searchParams); // GET leads/search {$searchParams}
     *
     * @param int|array $param Runs {@link find()} if int and {@link search()} if array.
     * @return BareResource|BareResource[]
     */
    public function __invoke($param = null)
    {
        switch (gettype($param)) {
            case 'integer':
                return $this->find($param);
            case 'array':
                return $this->search($param);
            //so fancy it gets weird: "CRM::task()()". Got removed in favor of plural calls
            //case 'NULL':
            //    return $this->all();
            default:
                throw new \BadMethodCallException("Unknown desired operation with magic Endpoint $this->uri($param) invocation");
        }
    }

    /**
     * Magic call of specific Endpoint methods.
     * An optional first parameter as integer will be converted into an ID in the requested path.
     * @example CRM::lead()->convert(10, [...]); // POST leads/10/convert {...}
     * @example CRM::person()->search([...]); // POST people/search {...}
     * @param $method
     * @param $params
     * @return bool|object
     */
    public function __call($method, $params)
    {
        $method = strtolower($method);

        if (isset($params[0]) && is_int($params[0])) {
            $method = "$params[0]/$method";
            $options = $params[1] ?? [];
        } else {
            $options = $params[0] ?? [];
        }

        //forcing encoding of POST/PUT bodies and simplifying their structure
        if ($options) {
            if (!isset($options['json']) && !isset($options['body'])) {
                $options = ['json' => $options];
            }
        }

        return $this->request('post', $method, $options);
    }
}
