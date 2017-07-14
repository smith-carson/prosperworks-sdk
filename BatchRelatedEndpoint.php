<?php namespace ProsperWorks;

use GuzzleHttp\Client;
use ProsperWorks\SubResource\Relation;

class BatchRelatedEndpoint extends BaseEndpoint {

    public function __construct(string $uri, Client $client)
    {
        $this->uri = $uri;
        $this->client = $client;
    }

    /**
     * Prepares a generator given a list of Relations, indexed by the correct path to be requested.
     * @param \Traversable|Relation[] $relations List of Relation objects, indexed by the main resource's id
     * @return \Generator
     */
    private function listGenerator($relations)
    {
        return (function ($relations) {
            foreach ($relations as $owner => $related) {
                yield "$owner/related" => ['json' => ['resource' => (array)$related]];
            }
        })($relations);
    }

    /**
     * Creates many relations at once.
     * @param \Traversable|Relation[] $relations List of Relation objects, indexed by the main resource's id
     * @return object[]
     */
    public function create($relations)
    {
        return $this->requestMany('post', $this->listGenerator($relations));
    }

    /**
     * Creates many relations at once.
     * @param \Traversable|Relation[] $relations List of Relation objects, indexed by the main resource's id
     * @return bool[]
     */
    public function delete($relations)
    {
        return $this->requestMany('delete', $this->listGenerator($relations));
    }
}