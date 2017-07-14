<?php namespace ProsperWorks\SubResources;

/**
 * Class Email
 * @package ProsperWorks\SubResources
 * @author igorsantos07
 */
class Email extends Categorized
{

    public $email;

    /**
     * Email constructor.
     * @param string $email
     * @param string $category
     */
    public function __construct(string $email, string $category)
    {
        parent::__construct($category);
        $this->email = $email;
    }

    /**
     * Emails aren't categorized in TM2.
     * @deprecated
     * @return string
     */
    function getModelCategory(): string
    {
        return $this->category;
    }
}