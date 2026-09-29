<?php

namespace SeinopSys\OAuth2\Client\Test\Provider;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SeinopSys\OAuth2\Client\Provider\DeviantArtProvider;
use Ramsey\Uuid\Uuid;
use SeinopSys\OAuth2\Client\Provider\DeviantArtResourceOwner;

class DeviantArtTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var DeviantArtProvider
     */
    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new DeviantArtProvider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'mock_redirect_uri',
        ]);
    }

    private static function authorizationUrlQuery(DeviantArtProvider $provider): array
    {
        $uri = parse_url($provider->getAuthorizationUrl());
        parse_str($uri['query'], $query);

        return $query;
    }

    private static function jsonResponse(string $body, int $status = 200): ResponseInterface
    {
        return new Response($status, ['content-type' => 'application/json'], $body);
    }

    public function testAuthorizationUrl()
    {
        $query = self::authorizationUrlQuery($this->provider);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testPkceIsOffByDefault()
    {
        $query = self::authorizationUrlQuery($this->provider);

        $this->assertArrayNotHasKey('code_challenge', $query);
        $this->assertArrayNotHasKey('code_challenge_method', $query);
        $this->assertNull($this->provider->getPkceCode());
    }

    public function testPkceS256AddsCodeChallengeToAuthorizationUrl()
    {
        $provider = new DeviantArtProvider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'mock_redirect_uri',
            'pkceMethod' => DeviantArtProvider::PKCE_METHOD_S256,
        ]);
        $query = self::authorizationUrlQuery($provider);

        $verifier = $provider->getPkceCode();
        $this->assertNotEmpty($verifier);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            $query['code_challenge']
        );
    }

    public function testPkceVerifierIsSentWithTheTokenRequest()
    {
        $provider = new DeviantArtProvider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'mock_redirect_uri',
            'pkceMethod' => DeviantArtProvider::PKCE_METHOD_S256,
        ]);
        // As when restoring the verifier stored when the authorization URL was generated
        $provider->setPkceCode('mock_code_verifier');

        $sentBody = null;
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')->once()->andReturnUsing(function (RequestInterface $request) use (&$sentBody) {
            $sentBody = (string)$request->getBody();

            return self::jsonResponse('{"access_token":"mock_access_token", "token_type":"bearer"}');
        });
        $provider->setHttpClient($client);
        $provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        parse_str($sentBody, $params);
        $this->assertSame('mock_code_verifier', $params['code_verifier']);
    }

    public function testResourceOwnerDetailsUrl()
    {
        $token = Mockery::mock(AccessToken::class);
        $url = $this->provider->getResourceOwnerDetailsUrl($token);
        $uri = parse_url($url);
        $this->assertEquals('/api/v1/oauth2/user/whoami', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')->once()->andReturn(
            self::jsonResponse('{"access_token":"mock_access_token", "token_type":"bearer"}')
        );
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());

        $response = new Response(
            504,
            ['content-type' => 'text/html'],
            'Gateway Timeout Error: CloudFront was not able to find a server to handle this request'
        );
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')->once()->andReturn($response);
        $this->provider->setHttpClient($client);
        $this->expectException(IdentityProviderException::class);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $message = uniqid();
        $status = rand(400, 600);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')->once()->andReturn(self::jsonResponse(' {"error":"' . $message . '"}', $status));
        $this->provider->setHttpClient($client);

        $this->expectException(IdentityProviderException::class);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    private static $_mock_user_types = ['regular', 'premium', 'beta', 'admin', 'senior'];

    public function testUserData()
    {
        $userId = Uuid::uuid4()->toString();
        $userName = strtoupper(substr(md5(time()), 0, 12));
        $lowerUserName = strtolower($userName);
        $userIcon = "http://a.deviantart.net/avatars/{$lowerUserName[0]}/{$lowerUserName[1]}/$lowerUserName.png?" . rand(1, 10);
        $userType = self::$_mock_user_types[array_rand(self::$_mock_user_types)];
        $postResponse = new Response(
            200,
            ['content-type' => 'application/x-www-form-urlencoded'],
            'access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token'
        );
        $accountResponse = self::jsonResponse(
            '{"userid":"' . $userId . '","username":"' . $userName . '","usericon":"' . $userIcon . '","type":"' . $userType . '"}'
        );
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
