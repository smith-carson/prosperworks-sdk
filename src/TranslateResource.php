<?php
namespace ProsperWorks;

/**
 * Simple class used to translate some custom fields.
 * PW fields should be left public - thus, when casting into array/json they're shown.
 * Custom fields (such as used by your application) should be left as protected and listed in
 * {@link $altFields}, so they can be used by {@link __get()} to generate getters to be used around.
 * @author igorsantos07
 */
trait TranslateResource
{
    public function __get($name)
    {
        if (isset($this->altFields) && in_array($name, $this->altFields)) {
            return $this->$name;
        } else {
            trigger_error('Undefined property: '.static::class."::\$$name", E_USER_NOTICE);
            return null;
        }
    }
}