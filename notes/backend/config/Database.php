<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dotenv\Dotenv;

class Database {
    private string $hostname;
    private string $username;
    private string $password;
    private string $dbname;
    private int $port;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 3));
        $dotenv->safeLoad();

        $this->hostname = getenv('DB_HOST') ?: 'localhost';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
        $this->dbname = getenv('DB_NAME') ?: 'ronnie_legaspi';
        $this->port = (int) (getenv('DB_PORT') ?: 3306);
    }

    public function connect(): mysqli {
        // I enabled strict MySQLi error reporting so SQL failures become exceptions instead of silent runtime issues.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $conn = new mysqli(
            $this->hostname,
            $this->username,
            $this->password,
            $this->dbname,
            $this->port
        );

        if ($conn->connect_error) {
            throw new Exception($conn->connect_error, 500);
        }

        return $conn;
    }

}
