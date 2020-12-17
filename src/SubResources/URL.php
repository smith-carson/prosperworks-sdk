<?php

namespace ProsperWorks\SubResources;

/**
 * URL subresource. Is able to guess the category for Social URLs.\SubResources
 * @author igorsantos07
 */
class URL extends Categorized
{

    public $url;

    const SOCIAL_LINKEDIN    = 'linkedin';
    const SOCIAL_TWITTER     = 'twitter';
    const SOCIAL_GOOGLE_PLUS = 'google+';
    const SOCIAL_FACEBOOK    = 'facebook';
    const SOCIAL_YOUTUBE     = 'youtube';
    const SOCIAL_QUORA       = 'quora';
    const SOCIAL_FOURSQUARE  = 'foursquare';
    const SOCIAL_KLOUT       = 'klout';
    const SOCIAL_GRAVATAR    = 'gravatar';

    /** @var array List of regular expressions for each social category */
    const ASSOCIATIONS = [
        self::SOCIAL_LINKEDIN    => '/linkedin\.com/',
        self::SOCIAL_TWITTER     => '/twitter\.com/',
        self::SOCIAL_GOOGLE_PLUS => '/plus\.google\.com/',
        self::SOCIAL_FACEBOOK    => '/(facebook|fb)\.com/',
        self::SOCIAL_YOUTUBE     => '/youtube\.com/',
        self::SOCIAL_QUORA       => '/quora\.com/',
        self::SOCIAL_FOURSQUARE  => '/foursquare\.com/',
        self::SOCIAL_KLOUT       => '/klout\.com/',
        self::SOCIAL_GRAVATAR    => '/gravatar\.com/',
    ];

    /**
     * URL constructor. Is able to guess the category for Social URLs.
     * @param string $url
     * @param string $category If empty, tries to guess the category given the domain.
     */
    public function __construct(string $url, string $category = null)
    {
        if (!$category) {
            $category = self::OTHER; //default if no better option is found

            foreach (self::ASSOCIATIONS as $cat => $domain) {
                if (preg_match($domain, $url)) {
                    $category = $cat;
                    break;
                }
            }
        }

        parent::__construct($category);
        $this->url = $url;
    }
}
