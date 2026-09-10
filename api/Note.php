<?php
header("Content-Type: application/json");
require_once '../config/Database.php';
require_once '../models/Note.php';

try {
    $db = new Database();
    $conn = $db->connect();
    $note = new Note($conn);
    $method = $_SERVER['REQUEST_METHOD'];


    switch (strtoupper($method)) {
        case 'GET':
            $id = $_GET['id'] ?? 0;
            if ($id > 0 || $id != 0) {
                
            }
            
            break;
        case 'POST':
            break;
        case 'PUT':
            break;
        case 'DELETE':
            break;
        default:

    }

    
} catch (\Throwable $th) {
    http_response_code($th->getCode() ?: 500);
    echo json_encode([
        "status" => "error",
        "message" => $th->getMessage(),
        "code" => $th->getCode() ?: 500
    ]);
    exit;
}