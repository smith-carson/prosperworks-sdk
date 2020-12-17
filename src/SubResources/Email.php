<?php

namespace ProsperWorks\SubResources;

/**
 * Class Email\SubResources
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
}
