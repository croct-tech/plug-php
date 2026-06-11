<?php

declare(strict_types=1);

namespace Croct\Plug;

use Composer\InstalledVersions;
use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Content\DefaultContentProvider;
use Croct\Plug\Content\NullContentProvider;
use Croct\Plug\Exception\ConfigurationException;
use Croct\Plug\Exception\CroctException;
use Dotenv\Dotenv;
use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface as RequestFactory;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;
use Psr\Log\LoggerInterface as Logger;

/**
 * Entry point for server-side personalization with Croct.
 *
 * Resolves the visitor session, evaluates queries, fetches slot content, and reflects the
 * application's own authentication state.
 *
 * It is persistence-agnostic: where the client ID and user token are stored is decided by the
 * identity store it is given.
 */
final class Croct implements Plug
{
    public const DEFAULT_BASE_ENDPOINT_URL = 'https://api.croct.io';

    public const DEFAULT_TOKEN_DURATION = 86400;

    private string $appId;

    private Session $session;

    private Evaluator $evaluator;

    private ContentFetcher $contentFetcher;

    private CookieConfiguration $cookieConfiguration;

    private function __construct(
        string $appId,
        Session $session,
        Evaluator $evaluator,
        ContentFetcher $contentFetcher,
        CookieConfiguration $cookieConfiguration,
    ) {
        $this->appId = $appId;
        $this->session = $session;
        $this->evaluator = $evaluator;
        $this->contentFetcher = $contentFetcher;
        $this->cookieConfiguration = $cookieConfiguration;
    }

    /**
     * Creates an instance wired with sensible defaults.
     *
     * The HTTP client and PSR-17 factories are auto-discovered when not provided.
     *
     * @throws ConfigurationException If no PSR-18 client or PSR-17 factory is available.
     */
    public static function plug(
        string $appId,
        #[\SensitiveParameter]
        ApiKey|string $apiKey,
        IdentityStore $storage,
        ?IdentityResolver $identity = null,
        ?string $baseEndpointUrl = null,
        int $tokenDuration = self::DEFAULT_TOKEN_DURATION,
        ?ContentProvider $contentProvider = null,
        ?RequestContext $context = null,
        ?HttpClient $httpClient = null,
        ?RequestFactory $requestFactory = null,
        ?StreamFactory $streamFactory = null,
        ?Logger $logger = null,
    ): Plug {
        $key = ApiKey::from($apiKey);
        $context ??= RequestContext::fromGlobals();
        $baseEndpointUrl ??= self::DEFAULT_BASE_ENDPOINT_URL;

        $session = new Session($appId, $key, $storage, $tokenDuration, identity: $identity);

        try {
            $httpClient ??= Psr18ClientDiscovery::find();
            $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
            $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();
        } catch (NotFoundException $exception) {
            throw new ConfigurationException(
                'No PSR-18 HTTP client or PSR-17 factory was found. Install one '
                . '(e.g. "composer require guzzlehttp/guzzle nyholm/psr7") or pass it explicitly.',
                0,
                $exception,
            );
        }

        $version = InstalledVersions::getPrettyVersion('croct/plug-php');

        $client = new PsrApiClient(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            apiKey: $key,
            logger: $logger,
            baseEndpointUrl: $baseEndpointUrl,
            version: $version,
        );

        $cookieConfiguration = $storage instanceof CookieStorage
            ? $storage->getConfiguration()
            : new CookieConfiguration();

        return new self(
            $appId,
            $session,
            new HttpEvaluator($client, $context, $session),
            new HttpContentFetcher(
                $client,
                $context,
                $session,
                $contentProvider ?? self::discoverContentProvider(),
            ),
            $cookieConfiguration,
        );
    }

    /**
     * Creates an instance from the CROCT_* environment variables.
     *
     * Reads from the process environment ($_ENV, $_SERVER, and getenv()), defaulting to the
     * process-wide cookie storage when none is given.
     *
     * @throws ConfigurationException If required variables are missing or no transport is available.
     */
    public static function fromEnvironment(?IdentityStore $storage = null): Plug
    {
        return self::build(self::getEnv(...), $storage ?? CookieStorage::global());
    }

    /**
     * Creates an instance from the CROCT_* variables in a .env file.
     *
     * Reads the .env file in the given directory (defaulting to the current working directory)
     * without modifying the process environment, falling back to the process environment for any
     * variable absent from the file. Defaults to the process-wide cookie storage when none is given.
     *
     * @throws ConfigurationException If required variables are missing or no transport is available.
     */
    public static function fromDotenv(?string $directory = null, ?IdentityStore $storage = null): Plug
    {
        if ($directory === null) {
            $cwd = \getcwd();
            $directory = $cwd === false ? '.' : $cwd;
        }

        $values = Dotenv::createArrayBacked($directory)->safeLoad();

        $resolve = static function (string $name) use ($values): ?string {
            $value = $values[$name] ?? null;

            return \is_string($value) && $value !== '' ? $value : self::getEnv($name);
        };

        return self::build($resolve, $storage ?? CookieStorage::global());
    }

    /**
     * Emits the session cookies of the process-wide cookie storage.
     *
     * Convenience for CookieStorage::global()->emit(); must be called before any output is sent.
     *
     * @param (callable(string, string, array<string, mixed>): bool)|null $emitter
     *     The function used to send each cookie. Defaults to PHP's setcookie().
     */
    public static function emitCookies(?callable $emitter = null): void
    {
        CookieStorage::global()->emit($emitter);
    }

    /**
     * Evaluates a CQL query against the visitor's context.
     *
     * @param EvaluationOptions<mixed>|null $options
     *
     * @throws CroctException If the query is invalid or the request fails without a fallback.
     */
    public function evaluate(string $query, ?EvaluationOptions $options = null): mixed
    {
        return $this->evaluator->evaluate($query, $options);
    }

    /**
     * Fetches the personalized content of a slot.
     *
     * @template F = never
     *
     * @param string               $slotId  The slot ID, optionally versioned as `slot-id@version`
     *                                       (e.g. `home-banner@2`).
     * @param FetchOptions<F>|null $options
     *
     * @return FetchResponse<array<string, mixed>, F>
     *
     * @throws \InvalidArgumentException If the slot ID is malformed.
     * @throws CroctException If the request fails without a fallback.
     */
    public function fetchContent(string $slotId, ?FetchOptions $options = null): FetchResponse
    {
        return $this->contentFetcher->fetch($slotId, $options);
    }

    /**
     * Marks the visitor as a known user.
     */
    public function identify(string $userId): void
    {
        $this->session->identify($userId);
    }

    /**
     * Resets the visitor to anonymous.
     */
    public function anonymize(): void
    {
        $this->session->anonymize();
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getClientId(): string
    {
        return $this->session->getClientId()->toString();
    }

    public function getUserToken(): string
    {
        return $this->session->getUserToken()->toString();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlugOptions(): array
    {
        return [
            'appId' => $this->appId,
            'disableCidMirroring' => true,
            'cookie' => $this->cookieConfiguration->toBrowserCookies(),
        ];
    }

    /**
     * Builds an instance from a resolver of CROCT_* configuration variables.
     *
     * @param callable(string): (string|null) $source Resolves a variable by name, or null if unset.
     *
     * @throws ConfigurationException If required variables are missing or no transport is available.
     */
    private static function build(callable $source, IdentityStore $storage): self
    {
        $appId = $source('CROCT_APP_ID');
        $apiKey = $source('CROCT_API_KEY');

        if ($appId === null || $apiKey === null) {
            throw new ConfigurationException(
                'The CROCT_APP_ID and CROCT_API_KEY variables are required. Set them in the '
                . 'environment, load them from a .env file with Croct::fromDotenv(), or pass them '
                . 'directly to Croct::plug().',
            );
        }

        $tokenDuration = $source('CROCT_TOKEN_DURATION');

        return self::plug(
            appId: $appId,
            apiKey: $apiKey,
            storage: $storage,
            baseEndpointUrl: $source('CROCT_BASE_ENDPOINT_URL') ?? self::DEFAULT_BASE_ENDPOINT_URL,
            tokenDuration: $tokenDuration !== null ? (int) $tokenDuration : self::DEFAULT_TOKEN_DURATION,
        );
    }

    /**
     * Reads a variable from the process environment.
     *
     * Checks $_ENV and $_SERVER before getenv() so variables set by the SAPI (e.g. Apache SetEnv
     * or PHP-FPM env) are seen even when they are not exported to getenv().
     *
     * @return string|null The value, or null when it is unset or empty.
     */
    private static function getEnv(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? \getenv($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Discovers the content provider for the project.
     *
     * Prefers the content committed by the CLI as `slots.json`, falling back to a
     * null provider when none is available.
     */
    private static function discoverContentProvider(): ContentProvider
    {
        /** @var ContentProvider|null $provider **/
        static $provider = null;

        if ($provider === null) {
            $provider = DefaultContentProvider::fromProject() ?? new NullContentProvider();
        }

        return $provider;
    }
}
