<?php namespace ProsperWorks;

/**
 * Singleton class that defines Rate Limit rules, usable across Resources.
 * The default limits are specified at {@link DEFAULT_LIMITS} and used as default constructor argument.
 * Access it through {@link do}.
 */
class RateLimit
{
    /** @var array Lists the total of requests per second. Indexed by timestamp. */
    protected $reqNumber;

    protected $limits = [];

    protected $longestFrame;

    /** Information gathered from the support team, during a huge import script implementation. */
    const DEFAULT_LIMITS = [
        6 => 90,
        36 => 200
    ];

    /**
     * Creates a new RateLimit rule.
     * @param array $limits a list of limits: $frame => $maxRequests
     */
    protected function __construct(array $limits)
    {
        $this->reqNumber = [time() => 0];
        $this->limits = $limits;
        $this->longestFrame = max(array_keys($limits));
    }

    /**
     * Returns the RateLimit instance so you can do stuff with it.
     * @param array $limits If given, will be used if a new instance has to be created first.
     * @return RateLimit
     */
    public static function do(array $limits = self::DEFAULT_LIMITS)
    {
        static $instance;
        if (!$instance) {
            $instance = new self($limits);
        }
        return $instance;
    }

    public function getLimits() { return $this->limits; }

    public function totalRequests(int $frame):int
    {
        $frameRequests = $frame == $this->longestFrame? $this->reqNumber : array_slice($this->reqNumber, -$frame);
        return array_sum($frameRequests);
    }

    /**
     * Pushes one or more new requests into the seconds stack, and verifies if we'll be
     * limited through {@link rateLimit}.
     * @param int $qty
     */
    public function pushRequest(int $qty = 1)
    {
        $stamp = time();

        //adding filler entries if there's a gap between the most recent and the new one
        $top = max(array_keys($this->reqNumber));
        if ($top != $stamp && ($top + 1) != $stamp) {
            for ($key = $top + 1; $key < $stamp; $key++) $this->reqNumber[$key] = 0;
        }

        //adding the new entry
        $this->reqNumber[$stamp] = ($this->reqNumber[$stamp] ?? 0) + $qty; //new value or increment

        //removing older entries if limit was reached
        if (sizeof($this->reqNumber) >= $this->longestFrame) {
            $this->reqNumber = array_slice($this->reqNumber, -$this->longestFrame, null, true);
        }

        $this->rateLimit();
    }

    /**
     * Tells if we reached the rate limit.
     * @return bool
     */
    public function willBeRateLimited():bool
    {
        foreach ($this->limits as $frame => $max) {
            if ($this->totalRequests($frame) > $max) {
                return true;
            }
        }
        return false;
    }

    /**
     * When the limit of requests is reached it sleeps for 1/3 sec and pushes a new, empty second into the list,
     * until the limit is cleared.
     */
    protected function rateLimit()
    {
        while ($this->willBeRateLimited()) {
            if (Config::debugLevel() >= Config::DEBUG_BASIC) {
                echo ' [Rate limit reached. Waiting...] ';
            }
            sleep(1);
            $this->pushRequest(0);
        }
    }
}