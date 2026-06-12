<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Decorates another plug instance to detect when the response is varying by visitor.
 *
 * It runs a callback whenever visitor-specific data is read or changed. The application ID, the plug
 * options, and static content are identical for every visitor, so they do not run it. Reading the
 * user token does not run it either: its only response effect is the session cookie, which
 * integrations write based on whether the token changed. It is useful for integrations that need to
 * keep the response out of shared caches.
 */
final class VaryingResponseObserver implements Plug
{
    private Plug $plug;

    /** @var \Closure(): void */
    private \Closure $notify;

    /**
     * @param callable(): void $callback Invoked before each call that makes the response vary by visitor.
     */
    public function __construct(Plug $plug, callable $callback)
    {
        $this->plug = $plug;
        $this->notify = \Closure::fromCallable($callback);
    }

    public function getAppId(): string
    {
        return $this->plug->getAppId();
    }

    public function getClientId(): string
    {
        ($this->notify)();

        return $this->plug->getClientId();
    }

    public function getUserToken(): string
    {
        return $this->plug->getUserToken();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlugOptions(): array
    {
        return $this->plug->getPlugOptions();
    }

    /**
     * @param EvaluationOptions<mixed>|null $options
     */
    public function evaluate(string $query, ?EvaluationOptions $options = null): mixed
    {
        ($this->notify)();

        return $this->plug->evaluate($query, $options);
    }

    /**
     * @template F = never
     * @template S of bool = false
     *
     * @param FetchOptions<F, S>|null $options
     *
     * @return FetchResponse<array<string, mixed>, F, S>
     */
    public function fetchContent(string $slotId, ?FetchOptions $options = null): FetchResponse
    {
        if (!($options?->isStaticContent() ?? false)) {
            ($this->notify)();
        }

        return $this->plug->fetchContent($slotId, $options);
    }

    public function identify(string $userId): void
    {
        ($this->notify)();

        $this->plug->identify($userId);
    }

    public function anonymize(): void
    {
        ($this->notify)();

        $this->plug->anonymize();
    }
}
