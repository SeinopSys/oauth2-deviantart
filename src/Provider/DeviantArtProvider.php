<?php

namespace SeinopSys\OAuth2\Client\Provider;

use GuzzleHttp\Exception\BadResponseException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class DeviantArtProvider extends AbstractProvider {
	use BearerAuthorizationTrait;

	/**
	 * Returns the base URL for authorizing a client.
	 *
	 * @return string
	 */
	public function getBaseAuthorizationUrl(){
		return 'https://www.deviantart.com/oauth2/authorize';
	}

	/**
	 * Returns the base URL for requesting an access token.
	 * Eg. https://oauth.service.com/token
	 *
	 * @param array $params
	 *
	 * @return string
	 */
	public function getBaseAccessTokenUrl(array $params){
		return 'https://www.deviantart.com/oauth2/token';
	}

	/**
	 * Returns the URL for requesting the resource owner's details.
	 *
	 * @param AccessToken $token
	 *
	 * @return string
	 */
	public function getResourceOwnerDetailsUrl(AccessToken $token){
		return 'https://www.deviantart.com/api/v1/oauth2/user/whoami';
	}

	/**
	 * Returns the default scopes used by this provider.
	 * This should only be the scopes that are required to request the details
	 * of the resource owner, rather than all the available scopes.
	 *
	 * @return string[]
	 */
	protected function getDefaultScopes(){
		return ['user'];
	}

	/**
	 * Checks a provider response for errors.
	 *
	 * @throws IdentityProviderException
	 *
	 * @param  ResponseInterface $response
	 * @param  array|string      $data Parsed response data
	 *
	 * @return void
	 */
	protected function checkResponse(ResponseInterface $response, $data){
		if (isset($data['error'])){
			throw new IdentityProviderException(
				$data['error'] ? : $response->getReasonPhrase(),
				$response->getStatusCode(),
				$response->getBody()
			);
		}
	}

	/**
	 * Generates a resource owner object from a successful resource owner
	 * details request.
	 *
	 * @param  array       $response
	 * @param  AccessToken $token
	 *
	 * @return ResourceOwnerInterface
	 */
	protected function createResourceOwner(array $response, AccessToken $token){
		return new DeviantArtResourceOwner($response);
	}

	/**
	 * Returns the string that should be used to separate scopes when building
	 * the URL for requesting an access token.
	 *
	 * @return string Scope separator
	 */
	protected function getScopeSeparator(){
		return ' ';
	}

	/**
	 * Parses the response according to its content-type header.
	 *
	 * @param  ResponseInterface $response
	 *
	 * @return array
	 * @throws IdentityProviderException
	 * @throws UnexpectedValueException
	 */
	protected function parseResponse(ResponseInterface $response){
		$statusCode = $response->getStatusCode();
		if ($statusCode > 500){
            throw new IdentityProviderException(
                'The OAuth server returned an unexpected response',
                $statusCode,
                $response->getBody()
            );
		}

		return parent::parseResponse($response);
	}
}
