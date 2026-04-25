<?php
class DatabaseConfig {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        $this->loadEnvironment();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadEnvironment() {
        $envFile = __DIR__ . "/../.env";
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, "=") !== false && !str_starts_with(trim($line), "#")) {
                    list($key, $value) = explode("=", $line, 2);
                    $_ENV[trim($key)] = trim($value, "\"");
                }
            }
        }
    }
    
    public function getConnection() {
        if ($this->connection === null) {
            $host = $_ENV["DB_HOST"] ?? "localhost";
            $port = $_ENV["DB_PORT"] ?? "3306";
            $dbname = $_ENV["DB_NAME"] ?? "wdb_membership";
            $username = $_ENV["DB_USER"] ?? "root";
            $password = $_ENV["DB_PASSWORD"] ?? "";
            $charset = $_ENV["DB_CHARSET"] ?? "utf8mb4";
            
            try {
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
                $this->connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset"
                ]);
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        return $this->connection;
    }
    
    public function testConnection() {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>