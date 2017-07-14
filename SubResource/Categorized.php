<?php namespace ProsperWorks\SubResource;

/**
 * Class for simple resources that have categories.
 * @package ProsperWorks\SubResource
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

    /**
     * Should return the correspondent BaseModel category constant.
     * @return string
     */
    abstract function getModelCategory(): string;

}