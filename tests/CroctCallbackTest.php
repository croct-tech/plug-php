<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\CroctCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CroctCallback::class)]
#[TestDox('The Croct callback')]
final class CroctCallbackTest extends TestCase
{
    private const QUEUE = '(window.onCroctPlug=window.onCroctPlug||'
        . '(f=>(onCroctPlug.q=onCroctPlug.q||[]).push(f)))';

    #[TestDox('Registers the snippet through the onCroctPlug queue.')]
    public function testRegistersSnippetThroughQueue(): void
    {
        $snippet = "croct.track('linkOpened', {link: location.href});";

        self::assertSame(
            '<script>' . self::QUEUE . '(croct=>{' . $snippet . '})</script>',
            (string) new CroctCallback($snippet),
        );
    }

    #[TestDox('Adds the CSP nonce when provided.')]
    public function testAddsNonce(): void
    {
        $snippet = 'croct.track("x");';

        self::assertSame(
            '<script nonce="r4nd0m">' . self::QUEUE . '(croct=>{' . $snippet . '})</script>',
            (string) new CroctCallback($snippet, 'r4nd0m'),
        );
    }

    #[TestDox('Trims the indentation a template block adds around the snippet.')]
    public function testTrimsSnippet(): void
    {
        self::assertSame(
            '<script>' . self::QUEUE . '(croct=>{croct.track("x");})</script>',
            (string) new CroctCallback("\n      croct.track(\"x\");\n    "),
        );
    }
}
