<?php

declare(strict_types=1);

if (!defined('CURRENCY_HOLDER')) define('CURRENCY_HOLDER', 'GHS');
if (!defined('CURRENCY_FORMAT')) define('CURRENCY_FORMAT', 'before');

require_once projectPath('app/helpers/functions.php');

final class FakeDatabase
{
    public array $accounts = [];
    public array $queries = [];

    public function fetchOne(string $sql, array $params = []): ?array
    {
        if (str_contains($sql, 'id = ?')) {
            $account = $this->accounts[(int) $params[0]] ?? null;
            return $account && (int) $account['is_active'] === 1 ? $account : null;
        }
        if (str_contains($sql, "type = 'cash'")) {
            return $this->firstDefault('cash');
        }
        if (str_contains($sql, 'type = ?')) {
            return $this->firstDefault((string) $params[0]);
        }
        return null;
    }

    public function query(string $sql, array $params = []): object
    {
        $this->queries[] = compact('sql', 'params');
        if (str_contains($sql, 'UPDATE accounts SET balance')) {
            $this->accounts[(int) $params[1]]['balance'] = $params[0];
        }
        return new stdClass();
    }

    private function firstDefault(string $type): ?array
    {
        foreach ($this->accounts as $account) {
            if ($account['type'] === $type && (int) $account['is_default'] === 1 && (int) $account['is_active'] === 1) return $account;
        }
        return null;
    }
}

function registerHelperTests(TestRunner $runner): void
{
    $runner->test('money and number formatting are null-safe', function (): void {
        assertSame('GHS 0.00', formatMoney(null));
        assertSame('GHS 1,234.50', formatMoney('1234.5'));
        assertSame('1,234.57', formatNumber(1234.567, 2));
    });

    $runner->test('safe numeric conversions handle null and numeric strings', function (): void {
        assertSame(0, safeInt(null));
        assertSame(42, safeInt('42 units'));
        assertSame(12.5, safeFloat('12.5'));
    });

    $runner->test('HTML escaping blocks attribute and tag injection', function (): void {
        assertSame('&lt;script&gt;&quot;x&quot;&lt;/script&gt;', e('<script>"x"</script>'));
        assertSame('', e(null));
    });

    $runner->test('old input and CSRF helpers read session state', function (): void {
        $_SESSION = ['old_input' => ['name' => 'Alice & Bob'], 'csrf_token' => 'token-123'];
        assertSame('Alice & Bob', old('name'));
        assertSame('fallback', old('missing', 'fallback'));
        assertContains("value='token-123'", csrfField());
        clearOldInput();
        assertFalse(isset($_SESSION['old_input']));
    });

    $runner->test('flash messages render once and are then cleared', function (): void {
        $_SESSION = ['flash_type' => 'success', 'flash_message' => 'Saved'];
        $html = flashMessage();
        assertContains('green', $html);
        assertContains('Saved', $html);
        assertSame('', flashMessage());
    });

    $runner->test('authentication helper reflects session identity', function (): void {
        $_SESSION = [];
        assertFalse(isLoggedIn());
        $_SESSION['user_id'] = 7;
        assertTrue(isLoggedIn());
    });

    $runner->test('date, identifier, and SKU utilities produce valid values', function (): void {
        assertSame('', formatDate(null));
        assertSame('2026-07-18', formatDate('2026-07-18 12:30:00', 'Y-m-d'));
        assertSame(123, extractId('SALE-00123'));
        assertSame(0, extractId('SALE-NONE'));
        assertTrue((bool) preg_match('/^TEST-\d{8}-[A-F0-9]{6}$/', generateSKU('TEST')));
    });

    $runner->test('unit aliases normalize to the app default unit', function (): void {
        assertSame('pcs', normalizeUnit('pcs'));
        assertSame('pcs', normalizeUnit('pieces'));
        assertSame('pcs', normalizeUnit('piece'));
        assertSame('pcs', normalizeUnit('  PCS  '));
    });

    $runner->test('account transactions reject non-positive amounts', function (): void {
        $db = new FakeDatabase();
        assertFalse(recordAccountTransaction($db, 'cash', 0, 'deposit', 'sale', 1, 'none'));
        assertSame([], $db->queries);
    });

    $runner->test('deposit posts to explicit active account and records audit data', function (): void {
        $_SESSION = ['user_id' => 9];
        $db = new FakeDatabase();
        $db->accounts[4] = ['id' => 4, 'type' => 'bank', 'balance' => 100.0, 'is_default' => 0, 'is_active' => 1];
        assertTrue(recordAccountTransaction($db, 'cash', 25, 'deposit', 'sale', 12, 'receipt', 4));
        assertSame(125.0, $db->accounts[4]['balance']);
        assertSame(2, count($db->queries));
        assertSame([4, 'deposit', 25.0, 100.0, 125.0, 'sale', 12, 'receipt', 9], $db->queries[1]['params']);
    });

    $runner->test('withdrawal resolves payment-method default and cash fallback', function (): void {
        $db = new FakeDatabase();
        $db->accounts[1] = ['id' => 1, 'type' => 'cash', 'balance' => 80.0, 'is_default' => 1, 'is_active' => 1];
        assertTrue(recordAccountTransaction($db, 'unknown', 30, 'withdrawal', 'expense', 3, 'expense'));
        assertSame(50.0, $db->accounts[1]['balance']);
    });

    $runner->test('account transaction fails when no usable account exists', function (): void {
        $db = new FakeDatabase();
        assertFalse(recordAccountTransaction($db, 'cash', 10, 'deposit', 'sale', 1, 'none'));
        assertSame([], $db->queries);
    });
}
