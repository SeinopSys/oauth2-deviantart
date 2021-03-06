# DeviantArt Provider for OAuth 2.0 Client
[![Build Status](https://travis-ci.org/SeinopSys/oauth2-deviantart.svg?branch=master)](https://travis-ci.org/seinopsys/oauth2-deviantart)
[![Latest Stable Version](https://poser.pugx.org/seinopsys/oauth2-deviantart/v/stable.png)](https://packagist.org/packages/seinopsys/oauth2-deviantart)

[DeviantArt](https://deviantart.com/) OAuth 2.0 support for the PHP League’s [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

```
$ composer require seinopsys/oauth2-deviantart
```

## Usage

You can get your OAuth client credentials [here](https://www.deviantart.com/developers/apps).

```php
$provider = new SeinopSys\OAuth2\Client\Provider\DeviantArtProvider([
	'clientId' => 'client_id',
	'clientSecret' => 'client_secret',
	'redirectUri' => 'http://example.com/auth',
]);

$accessToken = $provider->getAccessToken('authorization_code', [
	'code' => $_GET['code'],
	'scope' => ['user','browse'] // optional, defaults to ['user']
]);
$actualToken = $accessToken->getToken();
$refreshToken = $accessToken->getRefresh();

// Once it expires

$newAccessToken = $provider->getAccessToken('refresh_token', [
	'refresh_token' => $refreshToken
]);
```
