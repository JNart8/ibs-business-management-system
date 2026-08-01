<?php
define('APP_START', true);
require_once __DIR__ . '/../app/config/database.php';

echo "<h1>Database Connection Test</h1>";

try {
    $db = Database::getInstance();
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM users");

    echo "<p style='color: green;'>✓ Connection successful!</p>";
    echo "<p>Users in database: " . $result['count'] . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Connection failed: " . $e->getMessage() . "</p>";
}
