<?php
namespace Transmission;

use Buzz\Browser;
use Transmission\Exception\ConnectionException;
use Transmission\Exception\InvalidResponseException;
use Transmission\Exception\UnexpectedResponseException;

/**
 * The Client is used to connect to the Transmission client
 *
 * @author Ramon Kleiss <ramon@cubilon.nl>
 */
class Client
{
    /**
     * @var string
     */
    const RPC_PATH = '/transmission/rpc';

    /**
     * @var string
     */
    const TOKEN_HEADER = 'X-Transmission-Session-Id';

    /**
     * @var string
     */
    protected $host;

    /**
     * @var integer
     */
    protected $port;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var Buzz\Browser
     */
    protected $browser;

    /**
     * Constructor
     *
     * @param string  $host
     * @param integer $port
     */
    public function __construct($host = null, $port = null)
    {
        $this->setHost($host ?: 'localhost');
        $this->setPort($port ?: 9091);
        $this->setBrowser(new Browser());
    }

    /**
     * Make an API call
     *
     * @param string $method
     * @param array  $arguments
     * @param string $tag
     * @return stdClass
     * @throws Transmission\Exception\ConnectionException
     * @throws Transmission\Exception\InvalidResponseException
     */
    public function call($method, array $arguments = array(), $tag = null)
    {
        $content = array('method' => (string) $method);

        if ($arguments) {
            $content['arguments'] = $arguments;
        }
        if ($tag) {
            $content['tag'] = (string) $tag;
        }

        try {
            $response = $this->browser->post(
                $this->getUrl(),
                array(sprintf(
                    '%s: %s', self::TOKEN_HEADER, $this->getToken()
                )),
                json_encode($content)
            );
        } catch (\Exception $e) {
            throw new ConnectionException(
                'Could not connect to Transmission', 0, $e
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 409) {
            if ($response->getHeader(self::TOKEN_HEADER) == null) {
                throw new InvalidResponseException(
                    'Invalid response received from Transmission'
                );
            }

            $this->setToken($response->getHeader(self::TOKEN_HEADER));

            return $this->call($method, $arguments, $tag);
        }

        if ($statusCode !== 200) {
            throw new UnexpectedResponseException(
                'Unexpected response received from Transmission'
            );
        }

        return $this->transformResponse($response->getContent());
    }

    /**
     * @param string $host
     * @return Transmission\Client
     */
    public function setHost($host)
    {
        $this->host = (string) $host;

        return $this;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param integer $port
     * @return Transmission\Client
     */
    public function setPort($port)
    {
        $this->port = (integer) $port;

        return $this;
    }

    /**
     * @return integer
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param Buzz\Browser $browser
     * @return Transmission\Client
     */
    public function setBrowser(Browser $browser)
    {
        $this->browser = $browser;

        return $this;
    }

    /**
     * @return Buzz\Browser
     */
    public function getBrowser()
    {
        return $this->browser;
    }

    /**
     * @param string $token
     * @return Transmission\Client
     */
    public function setToken($token)
    {
        $this->token = (string) $token;

        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return string
     */
    protected function getUrl()
    {
        return sprintf(
            'http://%s:%d%s',
            $this->getHost(),
            $this->getPort(),
            self::RPC_PATH
        );
    }

    /**
     * @param string $response
     * @return stdClass
     */
    protected function transformResponse($response)
    {
        $stdClass = json_decode($response);

        if ($stdClass === null) {
            throw new InvalidResponseException(
                'Invalid response received from Transmission'
            );
        }

        return $stdClass;
    }
}
