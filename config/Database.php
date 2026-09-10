<?php
class Database {
    private string $hostname = "localhost";
    private string $username = "root";
    private string $password = "";
    private string $dbname = "ronnie_legaspi";
    private int $port = 3306;

    public function connect(): mysqli {
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
