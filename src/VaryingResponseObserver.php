<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Decorates another plug instance to detect when the response is varying by visitor.
 *
 * It runs a callback whenever visitor-specific data is read or changed. Operations that return the
 * same result for every visitor — reading the application ID or the plug options, and static content
 * fetches — do not run it. It is useful for integrations that need to keep the response out of shared
 * caches.
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
        ($this->notify)();

        return $this->plug->getUserToken();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlugOptions(): array
    {
        return $this->plug->getPlugOptions();
    }

    public function evaluate(string $query, ?EvaluationOptions $options = null): mixed
    {
        ($this->notify)();

        return $this->plug->evaluate($query, $options);
    }

    public function fetchContent(string $slotId, ?FetchOptions $options = null): FetchResponse
    {
        if (!($options?->isStatic() ?? false)) {
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
