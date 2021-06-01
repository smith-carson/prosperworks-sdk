<?php namespace ProsperWorks\SubResources;

use ProsperWorks\TranslateResource;

/**
 * Class Phone
 * @property string $simpleNumber Phone number without the extension, if any
 * @property string $extension\SubResources
 * @author igorsantos07
 */
class Phone extends Categorized
{
    use TranslateResource;

    public $number;

    const MOBILE = 'mobile';
    const HOME = 'home';

    /** @deprecated */
    const PERSONAL = 'Personal';

    protected $simpleNumber;
    protected $extension;
    protected $altFields = ['simpleNumber','extension'];

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
}
