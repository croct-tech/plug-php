<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\CroctScript;
use Croct\Plug\LoadMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CroctScript::class)]
#[TestDox('The Croct script')]
final class CroctScriptTest extends TestCase
{
    private const QUEUE = '<script>window.onCroctPlug=window.onCroctPlug||'
        . '(f=>(onCroctPlug.q=onCroctPlug.q||[]).push(f))</script>';

    private const BOOTSTRAP = '<script>(function(s){function b(){croct.plug(%s);var q=onCroctPlug.q||[];'
        . 'window.onCroctPlug=function(f){f(croct)};for(var i=0;i<q.length;i++)q[i](croct)}'
        . 'window.croct?b():s.onload=b})(document.currentScript.previousElementSibling)</script>';

    #[TestDox('Defers the loader by default and runs the bootstrap on its load event.')]
    public function testRendersDeferredLoaderByDefault(): void
    {
        $html = (string) new CroctScript(
            'https://cdn.example/plug.js',
            [
                'appId' => 'app-1',
                'disableCidMirroring' => true,
            ],
        );

        self::assertSame(
            self::QUEUE
            . '<script src="https://cdn.example/plug.js" defer></script>'
            . \sprintf(self::BOOTSTRAP, '{"appId":"app-1","disableCidMirroring":true}'),
            $html,
        );
    }

    #[TestDox('Loads the SDK synchronously when the sync mode is selected.')]
    public function testRendersSynchronousLoader(): void
    {
        $html = (string) new CroctScript('https://cdn.example/plug.js', ['appId' => 'app-1'], mode: LoadMode::SYNC);

        self::assertSame(
            self::QUEUE
            . '<script src="https://cdn.example/plug.js"></script>'
            . \sprintf(self::BOOTSTRAP, '{"appId":"app-1"}'),
            $html,
        );
    }

    #[TestDox('Loads the SDK asynchronously when the async mode is selected.')]
    public function testRendersAsynchronousLoader(): void
    {
        $html = (string) new CroctScript('https://cdn.example/plug.js', ['appId' => 'app-1'], mode: LoadMode::ASYNC);

        self::assertStringContainsString('<script src="https://cdn.example/plug.js" async></script>', $html);
    }

    #[TestDox('Adds the CSP nonce to every tag when provided.')]
    public function testAddsNonceToEveryTag(): void
    {
        $html = (string) new CroctScript('https://cdn.example/plug.js', ['appId' => 'app-1'], 'r4nd0m');

        self::assertSame(3, \substr_count($html, 'nonce="r4nd0m"'));
        self::assertStringContainsString(
            '<script src="https://cdn.example/plug.js" defer nonce="r4nd0m"></script>',
            $html,
        );
    }

    #[TestDox('Escapes the options and the source so they cannot break out of the tag.')]
    public function testEscapesUnsafeOptions(): void
    {
        $html = (string) new CroctScript('https://cdn.example/plug.js?a=1&b=2', ['appId' => '</script>']);

        self::assertStringContainsString('?a=1&amp;b=2', $html);
        self::assertStringNotContainsString('"</script>"', $html);
    }
}
