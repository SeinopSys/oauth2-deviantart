<?php

namespace SeinopSys\OAuth2\Client\Tests\Provider;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SeinopSys\OAuth2\Client\Provider\DeviantArtProvider;
use Ramsey\Uuid\Uuid;
use SeinopSys\OAuth2\Client\Provider\DeviantArtResourceOwner;

class DeviantArtTest extends TestCase {
	/**
	 * @var DeviantArtProvider
	 */
	protected $provider;

	protected function setUp(){
		$this->provider = new DeviantArtProvider([
			'clientId' => 'mock_client_id',
			'clientSecret' => 'mock_secret',
			'redirectUri' => 'mock_redirect_uri',
		]);
	}

	public function testAuthorizationUrl(){
		$url = $this->provider->getAuthorizationUrl();
		$uri = parse_url($url);
		parse_str($uri['query'], $query);

		$this->assertArrayHasKey('client_id', $query);
		$this->assertArrayHasKey('redirect_uri', $query);
		$this->assertArrayHasKey('state', $query);
		$this->assertArrayHasKey('scope', $query);
		$this->assertArrayHasKey('response_type', $query);
		$this->assertArrayHasKey('approval_prompt', $query);
		$this->assertNotNull($this->provider->getState());
	}

	public function testResourceOwnerDetailsUrl(){
		$token = Mockery::mock(AccessToken::class);
		$url = $this->provider->getResourceOwnerDetailsUrl($token);
		$uri = parse_url($url);
		$this->assertEquals('/api/v1/oauth2/user/whoami', $uri['path']);
	}

	public function testGetAccessToken(){
		$response = Mockery::mock(ResponseInterface::class);
		$response->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token", "token_type":"bearer"}');
		$response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
		$response->shouldReceive('getStatusCode')->andReturn(200);
		$client = Mockery::mock(ClientInterface::class);
		$client->shouldReceive('send')->once()->andReturn($response);
		$this->provider->setHttpClient($client);
		$token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
		$this->assertEquals('mock_access_token', $token->getToken());
		$this->assertNull($token->getExpires());
		$this->assertNull($token->getRefreshToken());
		$this->assertNull($token->getResourceOwnerId());

		$response = Mockery::mock(ResponseInterface::class);
		$response->shouldReceive('getBody')->andReturn('Gateway Timeout Error: CloudFront was not able to find a server to handle this request');
		$response->shouldReceive('getHeader')->andReturn(['content-type' => 'text/html']);
		$response->shouldReceive('getStatusCode')->andReturn(504);
		$client = Mockery::mock(ClientInterface::class);
		$client->shouldReceive('send')->once()->andReturn($response);
		$this->provider->setHttpClient($client);
		$this->expectException(BadResponseException::class);
		$token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
	}

	/**
	 * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
	 */
	public function testExceptionThrownWhenErrorObjectReceived(){
		$message = uniqid();
		$status = rand(400, 600);
		$postResponse = Mockery::mock(ResponseInterface::class);
		$postResponse->shouldReceive('getBody')->andReturn(' {"error":"'.$message.'"}');
		$postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
		$postResponse->shouldReceive('getStatusCode')->andReturn($status);
		$client = Mockery::mock(ClientInterface::class);
		$client->shouldReceive('send')->once()->andReturn($postResponse);
		$this->provider->setHttpClient($client);
		$this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
	}

	private static $_mock_user_types = ['regular','premium','beta','admin','senior'];

	public function testUserData(){
		$userId = Uuid::uuid4();
		$userName = strtoupper(substr(md5(time()), 0, 12));
		$lowerUserName = strtolower($userName);
		$userIcon = "http://a.deviantart.net/avatars/{$lowerUserName[0]}/{$lowerUserName[1]}/$lowerUserName.png?".rand(1, 10);
		$userType = self::$_mock_user_types[array_rand(self::$_mock_user_types)];
		$postResponse = Mockery::mock(ResponseInterface::class);
		$postResponse->shouldReceive('getBody')->andReturn('access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token');
		$postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
		$postResponse->shouldReceive('getStatusCode')->andReturn(200);
		$accountResponse = Mockery::mock(ResponseInterface::class);
		$accountResponse->shouldReceive('getBody')->andReturn('{"userid":"'.$userId.'","username":"'.$userName.'","usericon":"'.$userIcon.'","type":"'.$userType.'"}');
		$accountResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
		$accountResponse->shouldReceive('getStatusCode')->andReturn(200);
		$client = Mockery::mock(ClientInterface::class);
		$client->shouldReceive('send')
			->times(2)
			->andReturn($postResponse, $accountResponse);
		$this->provider->setHttpClient($client);
		$token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

		/** @var $account DeviantArtResourceOwner */
		$account = $this->provider->getResourceOwner($token);

		$this->assertEquals($userId, $account->getId());
		$this->assertEquals($userName, $account->getName());
		$this->assertEquals($userIcon, $account->getIcon());
		$this->assertEquals($userType, $account->getType());

		$arr = $account->toArray();
		$this->assertEquals($userId, $arr['userid']);
		$this->assertEquals($userName, $arr['username']);
		$this->assertEquals($userIcon, $arr['usericon']);
		$this->assertEquals($userType, $arr['type']);
	}
}
