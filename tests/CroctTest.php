<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\CookieConfiguration;
use Croct\Plug\CookieStorage;
use Croct\Plug\Croct;
use Croct\Plug\Exception\ConfigurationException;
use Croct\Plug\IdentityStore;
use Croct\Plug\InMemoryIdentityStore;
use Croct\Plug\RequestContext;
use Croct\Plug\Token;
use Croct\Plug\Uuid;
use Http\Discovery\Psr18ClientDiscovery;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(Croct::class)]
#[TestDox('The Croct client')]
final class CroctTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const CLIENT_ID = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Evaluates queries with the resolved client ID and token.')]
    public function testEvaluateUsesResolvedSession(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)
                ->withBody($factory->createStream((string) \json_encode(true))),
        );

        $croct = $this->createCroct($mock, new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID)));

        self::assertTrue($croct->evaluate('user is returning'));

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame(self::CLIENT_ID, $request->getHeaderLine('X-Client-Id'));
        self::assertSame($croct->getUserToken(), $request->getHeaderLine('X-Token'));
    }

    #[TestDox('Persists the resolved session to the storage.')]
    public function testPersistsResolvedSession(): void
    {
        $storage = new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID));

        $croct = $this->createCroct(new MockClient(), $storage);

        self::assertSame($croct->getClientId(), $storage->getClientId()?->toString());
        self::assertSame($croct->getUserToken(), $storage->getUserToken()?->toString());

        $croct->identify('user-77');

        self::assertSame($croct->getUserToken(), $storage->getUserToken()->toString());
    }

    #[TestDox('Reflects identification and anonymization in the user token.')]
    public function testIdentifyChangesUserToken(): void
    {
        $croct = $this->createCroct(new MockClient());

        $croct->identify('user-77');

        self::assertSame('user-77', Token::parse($croct->getUserToken())->getSubject());

        $croct->anonymize();

        self::assertTrue(Token::parse($croct->getUserToken())->isAnonymous());
    }

    #[TestDox('Exposes the application ID, client ID, and user token.')]
    public function testExposesIdentityValues(): void
    {
        $croct = $this->createCroct(new MockClient(), new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID)));

        self::assertSame(self::APP_ID, $croct->getAppId());
        self::assertSame(self::CLIENT_ID, $croct->getClientId());
        self::assertNotSame('', $croct->getUserToken());
    }

    #[TestDox('Exposes the browser plug options, including the cookie settings.')]
    public function testExposesPlugOptions(): void
    {
        $storage = new CookieStorage(
            clientId: Uuid::parse(self::CLIENT_ID),
            configuration: new CookieConfiguration(
                clientIdName: 'cid',
                userTokenName: 'tok',
                domain: 'example.com',
            ),
        );

        $croct = $this->createCroct(new MockClient(), $storage);

        self::assertSame(
            [
                'appId' => self::APP_ID,
                'disableCidMirroring' => true,
                'cookie' => [
                    'clientId' => [
                        'name' => 'cid',
                        'maxAge' => 31536000,
                        'path' => '/',
                        'secure' => true,
                        'sameSite' => 'none',
                        'domain' => 'example.com',
                    ],
                    'userToken' => [
                        'name' => 'tok',
                        'maxAge' => 604800,
                        'path' => '/',
                        'secure' => true,
                        'sameSite' => 'none',
                        'domain' => 'example.com',
                    ],
                ],
            ],
            $croct->getPlugOptions(),
        );
    }

    #[TestDox('Fetches slot content with the resolved session.')]
    public function testFetchContentUsesResolvedSession(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody(
                $factory->createStream((string) \json_encode(['content' => ['title' => 'Hello']])),
            ),
        );

        $croct = $this->createCroct($mock, new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID)));

        $response = $croct->fetchContent('home-hero');

        self::assertSame(['title' => 'Hello'], $response->getContent());

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame(self::CLIENT_ID, $request->getHeaderLine('X-Client-Id'));
        self::assertSame($croct->getUserToken(), $request->getHeaderLine('X-Token'));
    }

    #[TestDox('Can be built from the environment variables.')]
    public function testCreatesFromEnvironment(): void
    {
        \putenv('CROCT_APP_ID=' . self::APP_ID);
        \putenv('CROCT_API_KEY=' . EcKeyFactory::IDENTIFIER);

        $croct = Croct::fromEnvironment(new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID)));

        self::assertSame(self::APP_ID, $croct->getAppId());
        self::assertSame(self::CLIENT_ID, $croct->getClientId());

        $token = Token::parse($croct->getUserToken());

        self::assertSame(self::APP_ID, $token->getApplicationId());
        self::assertTrue($token->isAnonymous());
    }

    #[TestDox('Rejects building from the environment when required variables are missing.')]
    public function testRejectsMissingEnvironment(): void
    {
        \putenv('CROCT_APP_ID');

        $this->expectException(ConfigurationException::class);

        Croct::fromEnvironment(new InMemoryIdentityStore());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    #[TestDox('Reports a missing transport when no HTTP client can be discovered.')]
    public function testReportsMissingTransport(): void
    {
        Psr18ClientDiscovery::setStrategies([]);

        $this->expectException(ConfigurationException::class);

        $storage = new InMemoryIdentityStore();

        Croct::plug(
            appId: self::APP_ID,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
            storage: $storage,
            context: new RequestContext(),
        );
    }

    private function createCroct(MockClient $client, ?IdentityStore $storage = null): Croct
    {
        $factory = new Psr17Factory();
        $storage ??= new InMemoryIdentityStore();

        return Croct::plug(
            appId: self::APP_ID,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
            storage: $storage,
            context: new RequestContext(),
            httpClient: $client,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }
}
