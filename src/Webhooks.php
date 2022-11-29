<?php namespace ProsperWorks;

use GuzzleHttp\Exception\ClientException;
use ProsperWorks\Endpoints\BaseEndpoint;

/**
 * Webhooks subscription in the ProsperWorks API. Basically, a syntatic sugar for the "/webhooks" endpoint.
 *
 * There's no such a thing as an UPDATE; it's not possible to have more than one address at the same combination of
 * method/resource, thus when you send different data for the same pair, it replaces what was there instead of adding
 * a second one.
 *
 * @see     https://prosperworks.zendesk.com/hc/en-us/articles/217214766-ProsperWorks-Webhooks
 * @author igorsantos07
 */
class Webhooks extends BaseEndpoint
{
    const EV_NEW    = 'new';
    const EV_UPDATE = 'update';
    const EV_DELETE = 'delete';
    const EV_ALL    = [self::EV_NEW, self::EV_UPDATE, self::EV_DELETE];

    /** @var array Only those resources are available as notification emitters */
    const ENDPOINTS = [
        CRM::RES_COMPANY,
        CRM::RES_LEAD,
        CRM::RES_OPPORTUNITY,
        CRM::RES_PERSON,
        CRM::RES_PROJECT,
        CRM::RES_TASK,
    ];

    /** A recognizable, fixed (and thus not encrypted) key, so we can find the secret on the payload */
    const SECRET_FIELD = 'password';

    protected $apiRoot;
    protected $secret;

    /**
     * Creates the basic Webhooks object.
     * @param string|null $root If empty, will use what's available in the {@link Config} class.
     *                          It must be HTTPS, or given without the protocol (what will prepend https:// to it).
     */
    public function __construct(string $root = null)
    {
        parent::__construct('webhook');

        if ($root) {
            $this->setRequestData($root);
        }
    }

    /**
     * Verifies if the payload secret is valid.
     * @param array $body
     * @return bool|null Returns null if the secret is not present, otherwise true/false if it matches or not.
     */
    public function validateSecret(array $body)
    {
        if (!key_exists(self::SECRET_FIELD, $body)) {
            return null;
        }
        return Config::crypt()->decryptBase64($body[self::SECRET_FIELD]) == Config::secret();
    }

    /**
     * Configures the request data to be sent with Webhook settings.
     * @param string|null $root If not given, defaults to {@link $defaultRoot}
     */
    public function setRequestData(string $root = null)
    {
        $root = $root ?? Config::defaultRoot();

        if (strpos($root, 'http:') === 0) {
            throw new \InvalidArgumentException("Webhook API root must be HTTPS: $root");
        } elseif (strpos($root, 'http') === false) {
            $root = "https://$root";
        }

        //TODO: verify the correct production path
        $this->apiRoot = "$root/v1";

        //we have to send a fixed secret key, as it's not encapsulated in a fixed key on the received payload
        $this->secret = ['password' => Config::crypt()->encryptBase64(Config::secret())];
    }

    /**
     * Subscribes to a new notification $event, for the given $resource type.
     * Webhooks will ping at $apiRoot/api/prosperworks_hooks/$resource
     * @param string       $endpoint What resource should we subscribe? Use one of the CRM::RES_* constants.
     * @param string|array $event    One ore more events to subscribe to. Use one of the EV_* constants.
     * @return int|int[] Id of the created event(s).
     */
    public function create(string $endpoint, $event = self::EV_ALL)
    {
        if (!in_array($endpoint, self::ENDPOINTS)) {
            throw new \InvalidArgumentException("Endpoint $endpoint is not valid for webhook notifications");
        }

        if (!$this->apiRoot) {
            $this->setRequestData();
        }

        $response = [];
        foreach ((array)$event as $ev) {
            $result = $this->request('post', '', ['json' => [
                'target' => "$this->apiRoot/prosperworks_hooks/$endpoint/$ev",
                'type' => strtolower($endpoint),
                'event' => $ev,
                'secret' => $this->secret,
            ]]);
            $response[] = $result->id;
        }

        return (is_array($response) && sizeof($response) == 1) ? $response[0] : $response;
    }

    public function list(int $id = null): array
    {
        try {
            $list = $this->request('get', $id);
            $list = is_array($list)? $list : [$list]; //if only one entry is returned, turn into a one-entry array
            return array_column($list, null, 'id');
        } catch (ClientException $e) {
            if ($e->getCode() == 404 && $id) {
                return [];
            } else {
                throw $e;
            }
        }
    }

    /**
     * Unsubscribes from one or more notifications.
     * @param int|int[] ...$id One ore more notification IDs.
     * @return bool|bool[]
     */
    public function delete(int ...$id)
    {
        return array_column($this->request('delete', $id), 'id');
    }
}
