<?php

declare(strict_types=1);

final class TestFailure extends RuntimeException {}

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo "[PASS] {$name}\n";
        } catch (Throwable $error) {
            $this->failed++;
            echo "[FAIL] {$name}\n       {$error->getMessage()}\n";
        }
    }

    public function finish(): int
    {
        $total = $this->passed + $this->failed;
        echo "\n{$this->passed}/{$total} tests passed.\n";
        return $this->failed === 0 ? 0 : 1;
    }
}

function assertTrue(bool $condition, string $message = 'Expected condition to be true'): void
{
    if (!$condition) throw new TestFailure($message);
}

function assertFalse(bool $condition, string $message = 'Expected condition to be false'): void
{
    assertTrue(!$condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $detail = 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
        throw new TestFailure($message === '' ? $detail : $message . ': ' . $detail);
    }
}

function assertContains(string $needle, string $haystack, string $message = ''): void
{
    assertTrue(str_contains($haystack, $needle), $message ?: "Expected output to contain '{$needle}'");
}

function projectPath(string $path = ''): string
{
    return dirname(__DIR__) . ($path === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
}

