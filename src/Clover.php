<?php

namespace Stevelipinski\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Grant\AbstractGrant;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Clover extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /*
    @var bool
    */
    protected $sandbox = true;

    /*
    @var string
    */
    protected $apiUrl;
    protected $authUrl;

    public function __construct(array $options = [], array $collaborators = [])
    {
        if (isset($options['useSandbox'])) {
            $this->sandbox = $options['useSandbox'];
        }
        $this->apiUrl = $this->sandbox ? 'https://sandbox.dev.clover.com' : 'https://api.clover.com';
        $this->authUrl = $this->sandbox ? 'https://sandbox.dev.clover.com' : 'https://www.clover.com';
        parent::__construct($options, $collaborators);
    }

    /**
     * Get Clover API URLs, depending on path.
     *
     * @param  string $path
     * @return string
     */
    public function getApiUrl($path = '')
    {
        return $this->apiUrl . '/' . $path;
    }
    protected function getAuthUrl($path = '')
    {
        return $this->authUrl . '/' . $path;
    }

    public function getBaseAuthorizationUrl()
    {
        return $this->getAuthUrl('oauth/v2/authorize');
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        if (isset($params['grant_type']) && $params['grant_type'] == 'refresh_token') {
            return $this->getAuthUrl('oauth/v2/refresh');
        } else {
            return $this->getAuthUrl('oauth/v2/token');
        }
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->getApiUrl('merchants/current/employees/current');
    }

    protected function getAccessTokenOptions(array $params)
    {
        $options = ['headers' => ['content-type' => 'application/json']];

        if ($this->getAccessTokenMethod() === self::METHOD_POST) {
            $options['body'] = $this->getAccessTokenBody($params);
        }

        return $options;
    }

    // Clover uses JSON body instead of urlencoded form
    protected function getAccessTokenBody(array $params)
    {
        return json_encode($params);
    }

    protected function getDefaultScopes()
    {
        return [];
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            $data = (is_array($data)) ? $data : json_decode($data, true);
            throw new IdentityProviderException($data['error'], $response->getStatusCode(), $data);
        }
    }

    // Clover sends access_token_expiration instead of expires.
    protected function prepareAccessTokenResponse(array $result)
    {
        if (isset($result['access_token_expiration']) && !isset($result['expires'])) {
            $result['expires'] = $result['access_token_expiration'];
        }
        return parent::prepareAccessTokenResponse($result);
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new CloverEmployee($response);
    }
}
