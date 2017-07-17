<?php namespace ProsperWorks\SubResources;

use InvalidArgumentException as InvalidArg;
use ProsperWorks\CRM;
use ProsperWorks\TranslateResource;

/**
 * Easy way to include custom fields in requests. Provides some validation as well.
 *
 * @property string $name      The field name
 * @property string $valueName The value name
 *\SubResources
 * @author igorsantos07
 */
class CustomField
{
    use TranslateResource;

    //those two are used by the PW API
    public $custom_field_definition_id;
    public $value;

    //those two are just for consulting
    protected $name;
    protected $valueName;
    protected $altFields = ['name','valueName'];

    //and those, used for internal checks
    protected $type;
    protected $resources = [];
    protected $options = [];

    /**
     * CustomField constructor.
     * @param string|int $idOrName
     * @param string     $value
     * @param string     $resource (singular) Needed to distinguish between same-name fields available in different
     *                             resources, when the key given is the field name.
     */
    public function __construct(string $idOrName, string $value = null, $resource = null)
    {
        $field = CRM::fieldList('customFieldDefinition', $idOrName, true);

        switch (sizeof($field)) {
            case 1:
                if (is_array($field)) {
                    $field = current($field); //gets the first entry directly
                }
                break;

            case 0:
                throw new InvalidArg("Custom Field not found: $idOrName");

            default: //will only happen on string identifiers (name)
                if ($resource) {
                    //returns the first item found with the resource name in the available list
                    $field = array_filter($field, function ($f) use ($resource) {
                        return in_array($resource, $f->available_on);
                    });
                } else {
                    throw new InvalidArg("There's more than one '$idOrName' field. To distinguish we need the resource name as well.");
                }
        }
        $this->custom_field_definition_id = $field->id;
        $this->name = $field->name;
        $this->type = $field->data_type;
        $this->resources = $field->available_on;
        $this->options = $field->options ?? [];

        //validating $resource and options, if available
        if ($resource && !in_array($resource, $this->resources)) {
            throw new InvalidArg("You requested a field ($idOrName) that is not available for ($resource).");
        }
        if ($this->options && $value) {
            if (is_numeric($value)) {
                $this->value = $value;
                $this->valueName = $this->options[$value] ?? false;
            } else {
                $this->valueName = $value;
                $this->value = array_flip($this->options)[$value] ?? false;
            }
            
            if (!$this->valueName || !$this->value) {
                $name = ($resource ? "$resource." : '') . $idOrName;
                $options = implode(', ', $this->options);
                throw new InvalidArg("Invalid value ($value) for field $name. Valid options are: $options");
            }
        } else {
            $this->value = $value;
        }
    }

}