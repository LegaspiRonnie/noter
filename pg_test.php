<?php

$host = 'localhost';
$port = 5432;
$dbname = 'ronnie_legaspi';
$user = 'postgres';
$password = 'Ronnie@23';

if (!class_exists('PDO')) {
    echo 'error: PDO is not available in this PHP install.';
    exit;
}

$drivers = PDO::getAvailableDrivers();
if (!in_array('pgsql', $drivers, true)) {
    echo 'error: PostgreSQL PDO driver is not enabled. Uncomment extension=pdo_pgsql and restart PHP.';
    exit;
}

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};user={$user};password={$password}";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo 'connected';
} catch (Throwable $e) {
    echo 'error: ' . $e->getMessage();
}
