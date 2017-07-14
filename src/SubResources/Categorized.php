<?php namespace ProsperWorks\SubResources;

/**
 * Class for simple resources that have categories.
 * @author igorsantos07
 */
abstract class Categorized
{

    public $category;

    const WORK = 'work';
    const PERSONAL = 'personal';
    const OTHER = 'other';

    /**
     * Categorized subresource constructor.
     * @param $category
     */
    public function __construct($category) {
        $this->category = $category;
    }

}