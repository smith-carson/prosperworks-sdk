<?php namespace ProsperWorks\Resources;

use ProsperWorks\CRM;
use ProsperWorks\SubResource\Address;
use ProsperWorks\SubResource\CustomField;
use ProsperWorks\SubResource\Phone;
use ProsperWorks\SubResource\URL;

/**
 * Base class that is used to translate resources coming from the API.
 * Contains all base fields used throughout the API, as well as generic translation methods.
 * @package ProsperWorks\Resources
 * @author igorsantos07
 */
class BareResource
{
    /** @var integer */
    public $id;

    /** @var integer */
    public $assignee_id;

    /** @var string */
    public $name;

    /** @var string[] */
    public $tags = [];

    /** @var CustomField[] Associative array of custom field names + values */
    public $custom_fields = [];

    /** @var \stdClass[] Indexed array of objects with custom_field_definition_id and value */
    public $custom_fields_raw = [];

    /** @var \DateTime */
    public $date_created;

    /** @var \DateTime */
    public $date_modified;

    protected static $timestampFields = [
        'created_at', //stupid non-standard webhook field
        'date_created',
        'date_modified',
        'date_last_contacted',
        'due_date',
        'reminder_date',
        'completed_date',
        'activity_date'
    ];

    /**
     * @param \stdClass|array $entry               Raw data coming from the ProsperWorks API
     * @param bool            $translateTimestamps Should timestamps become DateTime objects?
     */
    public function __construct($entry, $translateTimestamps = true)
    {
        $entry = (array)$entry;
        foreach ($entry as $name => $value) {
            switch ($name) {
                case 'address':
                    if (is_object($value)) {
                        $this->$name = new Address($value->street, $value->city, $value->state, $value->postal_code,
                            $value->country);
                    }
                    break;

                case 'socials':
                case 'websites':
                    $this->$name = array_map(function ($url) {
                        return new URL($url->url, $url->category);
                    }, $value);
                    break;

                case 'phone_numbers':
                    $this->$name = array_map(function ($number) {
                        return new Phone($number->number, $number->category);
                    }, $value);
                    break;

                case 'custom_fields':
                    $fields = CRM::fieldList('customFieldDefinition');
                    $finalFields = [];
                    foreach ($entry['custom_fields'] as $field) {
                        $id = $field->custom_field_definition_id;
                        $finalFields[$fields[$id]] = new CustomField($id, $field->value);
                    }
                    $this->custom_fields_raw = $entry['custom_fields'];
                    $this->custom_fields = $finalFields;
                    break;

                case 'contact_type_id':
                    $contactTypes = CRM::fieldList('contactType');
                    $this->contact_type = $contactTypes[$entry['contact_type_id']];
                    break;

                default:
                    $this->$name = $value;
            }
        }

        if ($translateTimestamps) {
            foreach (static::$timestampFields as $field) {
                if (isset($this->$field) && $entry[$field] && is_numeric($entry[$field])) {
                    $this->$field = new \DateTime("@{$entry[$field]}");
                }
            }
        }
    }
}