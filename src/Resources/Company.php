<?php

namespace ProsperWorks\Resources;

use ProsperWorks\SubResources\Address;
use ProsperWorks\SubResources\CustomField;
use ProsperWorks\SubResources\Phone;
use ProsperWorks\SubResources\URL;

/**
 * Company resource object.
 * @author igorsantos07
 */
class Company extends BareResource
{
    /** @var integer */
    public $contact_type_id;

    /** @var string */
    public $contact_type;

    /** @var string */
    public $details;

    /** @var string */
    public $email_domain;

    /** @var Address */
    public $address;

    /** @var Phone[] */
    public $phone_numbers = [];

    /** @var URL[] */
    public $socials = [];

    /** @var URL[] */
    public $websites = [];

    /** @var integer */
    public $interaciton_count;
}
