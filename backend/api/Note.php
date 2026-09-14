<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Category.php';

try {
    $db = new Database();
    $conn = $db->connect();
    $note = new Note($conn);
    $category = new Category($conn);
    $method = $_SERVER['REQUEST_METHOD'];

    switch (strtoupper($method)) {
        case 'GET':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            $action = $_GET['action'] ?? null;
            $search = trim((string) ($_GET['search'] ?? ''));
            $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
            $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

            if ($action === 'categories') {
                $categories = $category->all();
                echo json_encode([
                    "status" => "success",
                    "data" => $categories
                ]);
                break;
            }

            if ($id !== false && $id !== null && $id <= 0) {
                throw new InvalidArgumentException("Invalid ID.", 400);
            }

            $page = $page === false || $page === null || $page < 1 ? 1 : $page;
            $limit = $limit === false || $limit === null || $limit < 1 ? 10 : min($limit, 100);
            $categoryFilter = ($categoryId !== false && $categoryId !== null && $categoryId > 0) ? (int) $categoryId : null;

            $data = $note->get($id ?? null, $search !== '' ? $search : null, $categoryFilter, $page, $limit);
            if ($id !== null && empty($data)) {
                throw new Exception("Note not found.", 404);
            }

            if ($id === null) {
                $items = is_array($data) && isset($data['items']) ? $data['items'] : ($data ?? []);
                $pagination = is_array($data) && isset($data['pagination']) ? $data['pagination'] : [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => count($items),
                    'total_pages' => 1
                ];

                echo json_encode([
                    "status" => "success",
                    "data" => $items,
                    "pagination" => $pagination
                ]);
                break;
            }

            echo json_encode([
                "status" => "success",
                "data" => $data
            ]);
            break;

        case 'POST':
            $action = $_GET['action'] ?? null;

            if ($action === 'categories') {
                $data = json_decode(file_get_contents("php://input"), true);

                if (empty($data)) {
                    throw new Exception("No data provided", 400);
                }

                $name = trim((string) ($data['name'] ?? ''));
                $description = trim((string) ($data['description'] ?? ''));

                if ($name === '') {
                    throw new Exception("Category name is required.", 400);
                }

                if (strlen($name) > 100) {
                    throw new Exception("Category name cannot be longer than 100 characters.", 400);
                }

                if (strlen($description) > 255) {
                    throw new Exception("Category description cannot be longer than 255 characters.", 400);
                }

                $newCategoryId = $category->create([
                    'name' => $name,
                    'description' => $description,
                ]);

                echo json_encode([
                    "status" => "success",
                    "message" => "Category created successfully.",
                    "category_id" => $newCategoryId,
                    "data" => [
                        "id" => $newCategoryId,
                        "name" => $name,
                        "description" => $description,
                    ]
                ]);
                break;
            }

            $data = json_decode(file_get_contents("php://input"), true);
            if (empty($data)) {
                throw new Exception("No data provided", 400);
            }

            $category_id = $data['category_id'] ?? null;
            $title = trim((string) ($data['title'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));
            $pinned = array_key_exists('pinned', $data) ? filter_var($data['pinned'], FILTER_VALIDATE_BOOLEAN) : false;

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

            if ($title === '') {
                throw new Exception("Title is required.", 400);
            }

            if (strlen($title) > 255) {
                throw new Exception("Title cannot be longer than 255 characters.", 400);
            }

            $newNoteId = $note->create([
                'category_id' => $category_id,
                'title' => $title,
                'description' => $description,
                'pinned' => $pinned,
            ]);

            echo json_encode([
                "status" => "success",
                "message" => "Note created successfully.",
                "note_id" => $newNoteId
            ]);
            break;

        case 'PUT':
            $requestData = json_decode(file_get_contents("php://input"), true);

            if (empty($requestData)) {
                throw new Exception("No data provided", 400);
            }

            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($id === false || $id === null || $id <= 0) {
                $id = filter_var($requestData['id'] ?? null, FILTER_VALIDATE_INT);
            }

            if ($id === false || $id === null || $id <= 0) {
                throw new InvalidArgumentException("Invalid ID.", 400);
            }

            $existingNote = $note->get($id);
            if (empty($existingNote)) {
                throw new Exception("Note not found.", 404);
            }

            $category_id = array_key_exists('category_id', $requestData)
                ? $requestData['category_id']
                : ($existingNote['category_id'] ?? null);
            $title = trim((string) ($requestData['title'] ?? ($existingNote['title'] ?? '')));
            $description = trim((string) ($requestData['description'] ?? ($existingNote['description'] ?? '')));
            $pinned = array_key_exists('pinned', $requestData) ? filter_var($requestData['pinned'], FILTER_VALIDATE_BOOLEAN) : null;

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

            if ($title === '') {
                throw new Exception("Title is required.", 400);
            }

            if (strlen($title) > 255) {
                throw new Exception("Title cannot be longer than 255 characters.", 400);
            }

            $updateData = [
                'category_id' => $category_id,
                'title' => $title,
                'description' => $description,
                'pinned' => $pinned,
            ];

            $result = $note->update($id, $updateData);

            echo json_encode([
                "status" => "success",
                "message" => "Note updated successfully.",
                "note_id" => $result
            ]);
            break;

        case 'DELETE':
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
            throw new Exception('Unsupported request method.', 405);
    }
} catch (\Throwable $th) {
    $isSqlError = $th instanceof \PDOException || (
        $th instanceof \RuntimeException && (
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