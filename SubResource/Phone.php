<?php namespace ProsperWorks\SubResource;

use ProsperWorks\TranslateResource;
use PhalconRest\Models\UserNumbers;

/**
 * Class Phone
 * @property string $simpleNumber Phone number without the extension, if any
 * @property string $extension
 * @package ProsperWorks\SubResource
 * @author igorsantos07
 */
class Phone extends Categorized
{
    use TranslateResource;

    public $number;

    const MOBILE = 'mobile';
    const HOME = 'home';

    /** @deprecated */
    const PERSONAL = 'personal';

    protected $simpleNumber;
    protected $extension;
    protected $tm2fields = ['simpleNumber','extension'];

    /**
     * Phone constructor.
     * @param string $number
     * @param string $category
     */
    public function __construct(string $number, string $category)
    {
        parent::__construct($category);
        $this->number = $number;
        if (preg_match('/(.+) x(.+)/', $number, $matches)) {
            list(, $this->simpleNumber, $this->extension) = $matches;
        } else {
            $this->simpleNumber = $number;
        }
    }

    function getModelCategory(): string
    {
        switch ($this->category) {
            case self::MOBILE:  return UserNumbers::TYPE_MOBILE;
            case self::WORK:    return UserNumbers::TYPE_WORK;
            case self::HOME:    return UserNumbers::TYPE_HOME;
            default:            return UserNumbers::TYPE_OTHER;
        }
    }
}