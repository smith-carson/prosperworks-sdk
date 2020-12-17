<?php

namespace ProsperWorks\SubResources;

class Relation
{
    /** @var int */
    public $id;
    /** @var string */
    public $type;

    /**
     * Relation constructor.
     * @param int $id
     * @param string $type
     */
    public function __construct(int $id, string $type)
    {
        $this->id = $id;
        $this->type = $type;
    }

}
