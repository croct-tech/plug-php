<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Exception;

use Croct\Plug\Exception\ApiException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
#[TestDox('An API exception')]
final class ApiExceptionTest extends TestCase
{
    #[TestDox('Uses the problem title as the message.')]
    public function testForStatusUsesProblemTitle(): void
    {
        $exception = ApiException::fromProblem(400, ['title' => 'Invalid query']);

        self::assertSame('Invalid query', $exception->getMessage());
        self::assertSame(400, $exception->getStatusCode());
    }

    #[TestDox('Falls back to a generic message without a title.')]
    public function testForStatusWithoutTitle(): void
    {
        $exception = ApiException::fromProblem(500, null);

        self::assertStringContainsString('500', $exception->getMessage());
        self::assertSame(500, $exception->getStatusCode());
    }

    #[TestDox('Wraps the cause of a transport error.')]
    public function testForTransportError(): void
    {
        $previous = new \RuntimeException('boom');

        $exception = ApiException::fromReason('Failed to communicate.', $previous);

        self::assertSame('Failed to communicate.', $exception->getMessage());
        self::assertNull($exception->getStatusCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
