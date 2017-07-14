<?php
namespace ProsperWorks;

/**
 * Simple class used to translate information back and forth between TM2 and PW.
 * PW fields should be left public - thus, when casting into array/json they're shown.
 * TM2 fields should be left as protected and listed in {@link $tm2Fields}, so they can be
 * used by {@link __get()} to generate getters to be used around.
 * @package ProsperWorks
 * @author igorsantos07
 */
trait TranslateResource
{
    public function __get($name)
    {
        if (isset($this->tm2fields) && in_array($name, $this->tm2fields)) {
            return $this->$name;
        } else {
            trigger_error('Undefined property: '.static::class."::\$$name", E_USER_NOTICE);
            return null;
        }
    }
}