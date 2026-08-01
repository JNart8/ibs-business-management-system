<?php

/**
 * Database Configuration and Connection
 * This file creates a single database connection that the entire app uses
 */

// Load environment variables from .env file
function loadEnv($path)
{
    if (!file_exists($path)) {
        die('Error: .env file not found. Please create it from .env.example');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments (lines starting with #)
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            // Set as environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load the .env file (go up 2 directories from this file)
loadEnv(__DIR__ . '/../../.env');

/**
 * Database Connection Class
 * Uses PDO (PHP Data Objects) for secure database access
 */
class Database
{
    private static $instance = null;
    private $connection;

    // Database credentials from .env
    private $host;
    private $dbname;
    private $username;
    private $password;

    /**
     * Private constructor (prevents creating multiple connections)
     */
    private function __construct()
    {
        $this->host = getenv('DB_HOST');
        $this->dbname = getenv('DB_NAME');
        $this->username = getenv('DB_USER');
        $this->password = getenv('DB_PASS');

        try {
            // Create PDO connection with options for speed and security
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    // Throw exceptions on errors
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    // Return associative arrays (faster than objects)
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    // Don't emulate prepared statements (MUCH faster)
                    PDO::ATTR_EMULATE_PREPARES => false,

                    // Persistent connections (reuse connections = FAST)
                    PDO::ATTR_PERSISTENT => true,

                    // Buffer queries (faster for small results)
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,

                    // Speed optimizations
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );

            // Speed optimizations for MySQL
            //$this->connection->exec("SET SESSION query_cache_type = ON");
            $this->connection->exec("SET SESSION sql_mode = ''");

            // Set timezone to match PHP
            $this->connection->exec("SET time_zone = '+02:00'");
        } catch (PDOException $e) {
            // Show error only if in development mode
            if (getenv('APP_DEBUG') === 'true') {
                die("Database Connection Failed: " . $e->getMessage());
            } else {
                die("Database connection error. Please contact support.");
            }
        }
    }

    /**
     * Get single database instance (Singleton pattern)
     * This ensures only ONE connection exists throughout the app
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the actual PDO connection object
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Prepare and execute a query (SELECT, INSERT, UPDATE, DELETE)
     * 
     * @param string $sql - The SQL query with placeholders (?)
     * @param array $params - Array of values to bind to placeholders
     * @return PDOStatement - The executed statement
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Log error (in production, use error_log instead of die)
            if (getenv('APP_DEBUG') === 'true') {
                die("Query Error: " . $e->getMessage() . "<br>SQL: " . $sql);
            } else {
                error_log("Database Error: " . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Get single row from database
     */
    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }

    /**
     * Get multiple rows from database
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Get the ID of the last inserted record
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Start a database transaction
     * Use this when you need multiple queries to succeed together
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction (save all changes)
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction (undo all changes)
     */
    public function rollback()
    {
        return $this->connection->rollBack();
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database instance quickly
function db()
{
    return Database::getInstance();
}

// Helper function to get PDO connection directly
function getDB()
{
    return Database::getInstance()->getConnection();
}
