<?php

declare(strict_types=1);

namespace Croct\Plug;

use Composer\InstalledVersions;
use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Content\NullContentProvider;
use Croct\Plug\Exception\ConfigurationException;
use Croct\Plug\Exception\CroctException;
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
    private const DEFAULT_CONTENT_PROVIDER_CLASS = 'Croct\\Content\\GeneratedContentProvider';

    public const DEFAULT_BASE_ENDPOINT_URL = 'https://api.croct.io';

    public const DEFAULT_TOKEN_DURATION = 86400;

    private string $appId;

    private Session $session;

    private Evaluator $evaluator;

    private ContentFetcher $contentFetcher;

    private CookieConfiguration $cookieConfiguration;

    public function __construct(
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
        string $baseEndpointUrl = self::DEFAULT_BASE_ENDPOINT_URL,
        int $tokenDuration = self::DEFAULT_TOKEN_DURATION,
        ?ContentProvider $contentProvider = null,
        ?RequestContext $context = null,
        ?HttpClient $httpClient = null,
        ?RequestFactory $requestFactory = null,
        ?StreamFactory $streamFactory = null,
        ?Logger $logger = null,
    ): self {
        $key = ApiKey::from($apiKey);
        $context ??= RequestContext::fromGlobals();

        $session = new Session($appId, $key, $storage, $tokenDuration);

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
            identity: $session,
        );

        $cookieConfiguration = $storage instanceof CookieStorage
            ? $storage->getConfiguration()
            : new CookieConfiguration();

        return new self(
            $appId,
            $session,
            new HttpEvaluator($client, $context),
            new HttpContentFetcher($client, $context, $contentProvider ?? self::discoverContentProvider()),
            $cookieConfiguration,
        );
    }

    /**
     * Creates an instance from the CROCT_* environment variables.
     *
     * @throws ConfigurationException If required variables are missing or no transport is available.
     */
    public static function fromEnvironment(IdentityStore $storage): self
    {
        $appId = self::getEnv('CROCT_APP_ID');
        $apiKey = self::getEnv('CROCT_API_KEY');

        if ($appId === null || $apiKey === null) {
            throw new ConfigurationException(
                'The CROCT_APP_ID and CROCT_API_KEY environment variables are required.',
            );
        }

        $tokenDuration = self::getEnv('CROCT_TOKEN_DURATION');

        return self::plug(
            appId: $appId,
            apiKey: $apiKey,
            storage: $storage,
            baseEndpointUrl: self::getEnv('CROCT_BASE_ENDPOINT_URL') ?? self::DEFAULT_BASE_ENDPOINT_URL,
            tokenDuration: $tokenDuration !== null ? (int) $tokenDuration : self::DEFAULT_TOKEN_DURATION,
        );
    }

    /**
     * Evaluates a CQL query against the visitor's context.
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
            'clientId' => $this->getClientId(),
            'token' => $this->getUserToken(),
            'disableCidMirroring' => true,
            'cookie' => $this->cookieConfiguration->toBrowserCookies(),
        ];
    }

    /**
     * Reads an environment variable.
     *
     * @return string|null The value, or null when it is unset or empty.
     */
    private static function getEnv(string $name): ?string
    {
        $value = \getenv($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Discovers the content provider generated by the CLI, or a null provider when none is installed.
     */
    private static function discoverContentProvider(): ContentProvider
    {
        /** @var ContentProvider|null $provider **/
        static $provider = null;

        if ($provider === null) {
            /** @var ContentProvider $provider **/
            $provider = \class_exists(self::DEFAULT_CONTENT_PROVIDER_CLASS)
                ? new (self::DEFAULT_CONTENT_PROVIDER_CLASS)()
                : new NullContentProvider();
        }

        return $provider;
    }
}
