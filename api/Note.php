<?php
header("Content-Type: application/json");
require_once '../config/Database.php';
require_once '../models/Note.php';
require_once '../models/Category.php';

try {
    $db = new Database();
    $conn = $db->connect();
    $note = new Note($conn);
    $category = new Category($conn);
    $method = $_SERVER['REQUEST_METHOD'];


    switch (strtoupper($method)) {
        case 'GET':
            // I unified GET responses so the API returns one consistent JSON object instead of mixing two different payloads.
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if ($id !== false && $id !== null && $id <= 0) {
                throw new InvalidArgumentException("Invalid ID.", 400);
            }

            $data = $note->get($id ?? null);

            if ($id !== null && empty($data)) {
                throw new Exception("Note not found.", 404);
            }

            if ($id === null && empty($data)) {
                echo json_encode([
                    "status" => "success",
                    "message" => "No notes found.",
                    "data" => []
                ]);
                break;
            }

            echo json_encode([
                "status" => "success",
                "data" => $data
            ]);
            break;
        case 'POST':
            // I fixed the optional category validation so null or empty category_id is no longer rejected incorrectly.
            $data = json_decode(file_get_contents("php://input"), true);
            if (empty($data)) {
                throw new Exception("No data provided", 400);
            }
            $category_id = $data['category_id'] ?? null;
            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');

            if ($category_id !== null && $category_id !== '' && !filter_var($category_id, FILTER_VALIDATE_INT)) {
                throw new Exception("Invalid category ID.", 400);
            }

            if ($category_id !== null && $category_id !== '' && (int) $category_id <= 0) {
                throw new Exception("Category must be a positive whole number", 400);
            }

            if ($category_id !== null && $category_id !== '' && (int) $category_id > 0) {
                $categoryExists = $category->getId((int) $category_id);
                if (!$categoryExists) {
                    throw new Exception("Category not found.", 404);
                }
            }
            
            if ($title == '') {
                throw new Exception("Title is required.", 400);
            }
            if (strlen($title) > 255 ) {
                throw new Exception("Title cannot be longer than 255 characters.", 400);
            }

            $newNoteId = $note->create([
                'category_id' => $category_id,
                'title' => $title,
                'description' => $description,
            ]);

            echo json_encode([
                "status" => "success",
                "message" => "Note created successfully.",
                "note_id" => $newNoteId
            ]);

            break;
        case 'PUT':
            // I kept the PUT flow consistent with the other endpoints by validating request data first and returning one success object.
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            $requestData = json_decode(file_get_contents("php://input"), true);

            if (empty($requestData)) {
                throw new Exception("No data provided", 400);
            }

            $category_id = $requestData['category_id'] ?? null;
            $title       = trim($requestData['title'] ?? '');
            $description = trim($requestData['description'] ?? '');

            if ($id === false || $id === null || $id <= 0) {
                throw new InvalidArgumentException("Invalid ID.", 400);
            }

            $existingNote = $note->get($id);
            if (empty($existingNote)) {
                throw new Exception("Note not found.", 404);
            }

            if ($category_id !== null && $category_id !== '' && !filter_var($category_id, FILTER_VALIDATE_INT)) {
                throw new Exception("Invalid category ID.", 400);
            }

            if ($category_id !== null && $category_id !== '' && (int) $category_id <= 0) {
                throw new Exception("Category must be a positive whole number", 400);
            }

            if ($category_id !== null && $category_id !== '' && (int) $category_id > 0) {
                $categoryExists = $category->getId((int) $category_id);
                if (!$categoryExists) {
                    throw new Exception("Category not found.", 404);
                }
            }

            if ($title == '') {
                throw new Exception("Title is required.", 400);
            }

            if (strlen($title) > 255) {
                throw new Exception("Title cannot be longer than 255 characters.", 400);
            }

            $updateData = [
                'category_id' => $category_id,
                'title' => $title,
                'description' => $description,
            ];

            $result = $note->update($id, $updateData);

            echo json_encode([
                "status" => "success",
                "message" => "Note updated successfully.",
                "note_id" => $result
            ]);

            break;
        case 'DELETE':
            // I tightened the DELETE validation so invalid IDs are rejected early and the API returns a single clear success response.
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id == null || $id <= 0 || $id == '') {
                throw new Exception("Invalid ID", 400);
            }
            $getNote = $note->get($id);

            if (empty($getNote)) {
                throw new Exception("Note not found.", 404);
            }

            $deleteNote = $note->delete($id);

            echo json_encode([
                "status" => "success",
                "message" => "Note deleted successfully.",
                "isDeleted" => $deleteNote
            ]);

            break;
        default:

    }

    
} catch (\Throwable $th) {
    // I separate SQL/runtime exceptions from client validation errors so Postman can still read the JSON message instead of treating the response as an invalid HTTP status.
    $isSqlError = $th instanceof \mysqli_sql_exception || (
        $th instanceof \RuntimeException && (
            str_contains($th->getMessage(), "Prepare failed") ||
            str_contains($th->getMessage(), "Fetch failed") ||
            str_contains($th->getMessage(), "Create failed") ||
            str_contains($th->getMessage(), "Update failed") ||
            str_contains($th->getMessage(), "Delete failed")
        )
    );

    if (!$isSqlError) {
        http_response_code($th->getCode() ?: 500);
    }

    echo json_encode([
        "status" => "error",
        "message" => $th->getMessage(),
        "code" => $isSqlError ? 500 : ($th->getCode() ?: 500)
    ]);
    exit;
}