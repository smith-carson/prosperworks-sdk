<?php namespace ProsperWorks\SubResource;

use ProsperWorks\TranslateResource;

/**
 * Address translator class.
 * @property string $address TM2 field; AKA Address line 1
 * @property string $suite   TM2 field; AKA Address line 2
 * @package ProsperWorks\SubResource
 * @author  igorsantos07
 */
class Address
{
    use TranslateResource;

    /** @var string Complete street address, including Suite, if any. */
    public $street;
    public $city;
    public $state;
    public $postal_code;
    public $country = 'US';

    /** @var string First part of the street address, without the Suite part. */
    protected $address;
    protected $suite;
    protected $tm2fields = ['address', 'suite'];

    /**
     * Address constructor.
     * @param string|array $streetOrAddress If this is a string, denotes a single $street line; if it's an array, the
     *                                      first key is taken as $address and the second as $suite number. Both cases
     *                                      will fill all three fields, using "suite" as separator when needed.
     * @param string       $city
     * @param string       $state
     * @param string       $postal_code
     * @param string       $country         The only argument with a default value: US
     */
    public function __construct(
        $streetOrAddress,
        string $city = null,
        string $state = null,
        string $postal_code = null,
        string $country = null
    ) {
        if ($streetOrAddress) {
            if (is_array($streetOrAddress)) {
                $this->address = trim($streetOrAddress[0]) ?? null;
                $this->suite = trim($streetOrAddress[1] ?? '') ?? null;
                $this->street = $this->address . ($this->suite ? " Suite $this->suite" : '');
                //in case "suite" is already present on the suite string, let's remove the duplicate
                $this->street = preg_replace('/suite suite/i', 'Suite', $this->street);
            } else {
                $parts = preg_split('/\s+suite\s+/i', $streetOrAddress, 2, PREG_SPLIT_NO_EMPTY);
                $this->street = trim($streetOrAddress) ?: null;
                $this->address = trim($parts[0]) ?: null;
                $this->suite = trim($parts[1]?? '') ?: null;
            }
        }
        $this->city = trim($city) ?: null;
        $this->state = trim($state) ?: null;
        $this->postal_code = trim($postal_code) ?: null;
        $this->country = trim($country) ?: $this->country; //receives nulls, keeps signature valid, and has a default
    }
}