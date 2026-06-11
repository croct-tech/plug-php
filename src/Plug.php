<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\CroctException;

/**
 * Public API for server-side applications integrating with Croct.
 */
interface Plug
{
    /**
     * Returns the configured application ID.
     */
    public function getAppId(): string;

    /**
     * Returns the visitor's client ID.
     */
    public function getClientId(): string;

    /**
     * Returns the visitor's serialized user token.
     */
    public function getUserToken(): string;

    /**
     * Returns the options for bootstrapping the client-side SDK.
     *
     * @return array<string, mixed>
     */
    public function getPlugOptions(): array;

    /**
     * Marks the visitor as a known user.
     */
    public function identify(string $userId): void;

    /**
     * Resets the visitor to anonymous.
     */
    public function anonymize(): void;

    /**
     * Evaluates a CQL query against the visitor's context.
     *
     * @param EvaluationOptions<mixed>|null $options
     *
     * @throws CroctException If the query is invalid or the request fails without a fallback.
     */
    public function evaluate(string $query, ?EvaluationOptions $options = null): mixed;

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
    public function fetchContent(string $slotId, ?FetchOptions $options = null): FetchResponse;
}
