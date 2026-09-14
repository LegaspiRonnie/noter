<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dotenv\Dotenv;

class Database {
    private string $hostname;
    private string $username;
    private string $password;
    private string $dbname;
    private int $port;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->safeLoad();

        $this->hostname = getenv('DB_HOST') ?: 'localhost';
        $this->username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? $_ENV['DB_USERNAME'] ?? 'postgres');
        $this->password = getenv('DB_PASSWORD') ?: 'Ronnie@23';
        $this->dbname = getenv('DB_NAME') ?: 'ronnie_legaspi';
        $this->port = (int) (getenv('DB_PORT') ?: 5432);
    }

    public function connect(): PDO {
        if (!class_exists('PDO')) {
            throw new RuntimeException('PDO is not available in this PHP runtime.', 500);
        }

        $drivers = PDO::getAvailableDrivers();
        if (!in_array('pgsql', $drivers, true)) {
            throw new RuntimeException('PostgreSQL PDO driver is not enabled. Uncomment extension=pdo_pgsql and restart PHP.', 500);
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $this->hostname,
            $this->port,
            $this->dbname
        );

        $pdo = new PDO($dsn, $this->username, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}
