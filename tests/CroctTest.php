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
use Croct\Plug\Plug;
use Croct\Plug\RequestContext;
use Croct\Plug\Tests\Fixtures\VirtualFilesystem;
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

    private const CROCT_VARIABLES = [
        'CROCT_APP_ID',
        'CROCT_API_KEY',
        'CROCT_BASE_ENDPOINT_URL',
        'CROCT_TOKEN_DURATION',
    ];

    /** @var array<array-key, mixed> */
    private array $env;

    /** @var array<array-key, mixed> */
    private array $server;

    /** @var array<array-key, mixed> */
    private array $cookie;

    private string $cwd;

    protected function setUp(): void
    {
        VirtualFilesystem::setUp();

        $this->env = $_ENV;
        $this->server = $_SERVER;
        $this->cookie = $_COOKIE;

        $cwd = \getcwd();
        $this->cwd = $cwd === false ? '.' : $cwd;

        // Start each test from a clean slate so reads are deterministic regardless of the shell.
        foreach (self::CROCT_VARIABLES as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            \putenv($name);
        }

        CookieStorage::reset();
    }

    protected function tearDown(): void
    {
        \chdir($this->cwd);

        $_ENV = $this->env;
        $_SERVER = $this->server;
        $_COOKIE = $this->cookie;

        foreach (self::CROCT_VARIABLES as $name) {
            \putenv($name);
        }

        VirtualFilesystem::tearDown();
        CookieStorage::reset();
    }

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

    #[TestDox('Builds from the environment using the global cookie storage by default.')]
    public function testFromEnvironmentDefaultsToGlobalStorage(): void
    {
        \putenv('CROCT_APP_ID=' . self::APP_ID);
        \putenv('CROCT_API_KEY=' . EcKeyFactory::IDENTIFIER);
        $_COOKIE = ['ct_client_id' => self::CLIENT_ID];

        $croct = Croct::fromEnvironment();

        self::assertSame(self::CLIENT_ID, $croct->getClientId());
        self::assertSame(self::CLIENT_ID, CookieStorage::global()->getClientId()?->toString());
    }

    #[TestDox('Reads the required variables from the $_SERVER superglobal.')]
    public function testReadsEnvironmentFromServerSuperglobal(): void
    {
        $_SERVER['CROCT_APP_ID'] = self::APP_ID;
        $_SERVER['CROCT_API_KEY'] = EcKeyFactory::IDENTIFIER;

        $croct = Croct::fromEnvironment(new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID)));

        self::assertSame(self::APP_ID, $croct->getAppId());
    }

    #[TestDox('Can be built from a .env file.')]
    public function testCreatesFromDotenv(): void
    {
        VirtualFilesystem::write(
            VirtualFilesystem::path('.env'),
            'CROCT_APP_ID=' . self::APP_ID . "\n"
            . 'CROCT_API_KEY=' . EcKeyFactory::IDENTIFIER . "\n",
        );

        $croct = Croct::fromDotenv(
            VirtualFilesystem::path(),
            new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID)),
        );

        self::assertSame(self::APP_ID, $croct->getAppId());
        self::assertSame(self::CLIENT_ID, $croct->getClientId());
    }

    #[TestDox('Reads the .env file without modifying the process environment.')]
    public function testDotenvDoesNotModifyEnvironment(): void
    {
        VirtualFilesystem::write(
            VirtualFilesystem::path('.env'),
            'CROCT_APP_ID=' . self::APP_ID . "\n"
            . 'CROCT_API_KEY=' . EcKeyFactory::IDENTIFIER . "\n"
            . "UNRELATED_VARIABLE=should-not-leak\n",
        );

        Croct::fromDotenv(VirtualFilesystem::path(), new InMemoryIdentityStore());

        self::assertFalse(\getenv('CROCT_APP_ID'));
        self::assertArrayNotHasKey('CROCT_APP_ID', $_ENV);
        self::assertArrayNotHasKey('CROCT_APP_ID', $_SERVER);
        self::assertFalse(\getenv('UNRELATED_VARIABLE'));
        self::assertArrayNotHasKey('UNRELATED_VARIABLE', $_ENV);
        self::assertArrayNotHasKey('UNRELATED_VARIABLE', $_SERVER);
    }

    #[TestDox('Falls back to the process environment for variables absent from the .env file.')]
    public function testDotenvFallsBackToProcessEnvironment(): void
    {
        VirtualFilesystem::write(VirtualFilesystem::path('.env'), 'CROCT_APP_ID=' . self::APP_ID . "\n");

        // The API key lives only in the process environment, not in the file.
        $_SERVER['CROCT_API_KEY'] = EcKeyFactory::IDENTIFIER;

        $croct = Croct::fromDotenv(
            VirtualFilesystem::path(),
            new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID)),
        );

        self::assertSame(self::APP_ID, $croct->getAppId());
    }

    #[TestDox('Defaults to the current working directory and the global cookie storage.')]
    public function testDotenvDefaultsToCurrentDirectoryAndGlobalStorage(): void
    {
        // The tests directory has no .env, so the values come from the process environment.
        \chdir(__DIR__);
        $_SERVER['CROCT_APP_ID'] = self::APP_ID;
        $_SERVER['CROCT_API_KEY'] = EcKeyFactory::IDENTIFIER;
        $_COOKIE = ['ct_client_id' => self::CLIENT_ID];

        $croct = Croct::fromDotenv();

        self::assertSame(self::APP_ID, $croct->getAppId());
        self::assertSame(self::CLIENT_ID, $croct->getClientId());
        self::assertSame(self::CLIENT_ID, CookieStorage::global()->getClientId()?->toString());
    }

    #[TestDox('Emits the global cookie storage cookies through the given emitter.')]
    public function testEmitsCookiesFromGlobalStorage(): void
    {
        $_COOKIE = ['ct_client_id' => self::CLIENT_ID];

        // Prime the process-wide storage from the request cookies.
        CookieStorage::global();

        $emitted = [];
        Croct::emitCookies(static function (string $name, string $value, array $options) use (&$emitted): bool {
            $emitted[$name] = $value;

            return true;
        });

        self::assertSame(self::CLIENT_ID, $emitted['ct_client_id'] ?? null);
    }

    private function createCroct(MockClient $client, ?IdentityStore $storage = null): Plug
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
