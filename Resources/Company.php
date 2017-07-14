<?php
namespace ProsperWorks\Resources;

use ProsperWorks\SubResource\Address;
use ProsperWorks\SubResource\CustomField;
use ProsperWorks\SubResource\Phone;
use ProsperWorks\SubResource\URL;

/**
 * Company resource object.
 * @package ProsperWorks
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