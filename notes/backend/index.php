<?php
header("Content-Type: application/json");
require_once './config/Database.php';

try {
    $db = new Database();
    $conn = $db->connect();

    
} catch (\Throwable $th) {
    http_response_code($th->getCode() ?: 500);
    echo json_encode([
        "status" => "error",
        "message" => $th->getMessage(),
        "code" => $th->getCode() ?: 500
    ]);
    exit;
}

