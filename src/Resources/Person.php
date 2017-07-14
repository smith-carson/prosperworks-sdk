<?php
namespace ProsperWorks\Resources;

use ProsperWorks\SubResources\Address;
use ProsperWorks\SubResources\Email;
use ProsperWorks\SubResources\Phone;
use ProsperWorks\SubResources\URL;

/**
 * Person resource object.
 * @author igorsantos07
 */
class Person extends BareResource
{
    /** @var integer */
    public $company_id;

    /** @var string */
    public $company_name;

    /** @var integer */
    public $contact_type_id;

    /** @var string */
    public $contact_type;

    /** @var string */
    public $title;

    /** @var string */
    public $prefix;

    /** @var string */
    public $first_name;

    /** @var string */
    public $middle_name;

    /** @var string */
    public $last_name;

    /** @var string */
    public $suffix;

    /** @var Address */
    public $address;

    /** @var string */
    public $details;

    /** @var Email[] */
    public $emails = [];

    /** @var Phone[] */
    public $phone_numbers = [];

    /** @var URL[] */
    public $socials = [];

    /** @var URL[] */
    public $websites = [];

    /** @var integer */
    public $interaction_count;
}