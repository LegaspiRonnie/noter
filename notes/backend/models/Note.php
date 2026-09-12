<?php

class Note {
    private int $id;
    private ?int $category_id = null;
    private string $title;
    private string $description;
    private bool $pinned = false;
    private string $table = "noter_notes";
    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    public function get(?int $id = null, ?string $search = null, ?int $category_id = null, int $page = 1, int $limit = 10): array|null {
        if ($id !== null && $id > 0) {
            $sql = "SELECT n.*, c.name AS category_name
                    FROM " . $this->table . " n
                    LEFT JOIN noter_categories c ON c.id = n.category_id
                    WHERE n.id = ?
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);

            if ($stmt === false) {
                throw new RuntimeException("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param('i', $id);

            if(!$stmt->execute()) {
                 throw new RuntimeException("Fetch failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $note = $result->fetch_assoc();
            return $note;
        }

        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        $types = '';

        if ($search !== null && trim($search) !== '') {
            $searchTerm = '%' . trim($search) . '%';
            $where[] = '(n.title LIKE ? OR n.description LIKE ? OR c.name LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }

        if ($category_id !== null && $category_id > 0) {
            $where[] = 'n.category_id = ?';
            $params[] = $category_id;
            $types .= 'i';
        }

        $countSql = "SELECT COUNT(*) AS total
                     FROM " . $this->table . " n
                     LEFT JOIN noter_categories c ON c.id = n.category_id";
        if (!empty($where)) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }

        $countStmt = $this->conn->prepare($countSql);
        if ($countStmt === false) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error);
        }

        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }

        if (!$countStmt->execute()) {
            throw new RuntimeException("Fetch failed: " . $countStmt->error);
        }

        $countResult = $countStmt->get_result()->fetch_assoc();
        $total = (int) ($countResult['total'] ?? 0);

        $sql = "SELECT n.*, c.name AS category_name
                FROM " . $this->table . " n
                LEFT JOIN noter_categories c ON c.id = n.category_id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY c.name ASC, n.pinned DESC, n.created_at DESC LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error);
        }

        $bindParams = $params;
        $bindParams[] = $limit;
        $bindParams[] = $offset;
        $bindTypes = $types . 'ii';

        $stmt->bind_param($bindTypes, ...$bindParams);

        if(!$stmt->execute()) {
            throw new RuntimeException("Fetch failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $notes = $result->fetch_all(MYSQLI_ASSOC);

        return [
            'items' => $notes,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $limit))
            ]
        ];
    }

    public function create(array $data): int {
        $category_id = $data['category_id'] ?? null;
        $title       = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $pinned      = isset($data['pinned']) ? (bool) $data['pinned'] : false;

        $this->category_id = $category_id !== null && $category_id !== '' ? (int) $category_id : null;
        $this->title = $title;
        $this->description = $description;
        $this->pinned = $pinned;

        if ($this->category_id !== null) {
            $sql = "INSERT INTO " . $this->table . " (category_id, title, description, pinned) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException("Prepare failed: " . $this->conn->error);
            }
            $pinnedValue = $this->pinned ? 1 : 0;
            $stmt->bind_param('issi', $this->category_id, $this->title, $this->description, $pinnedValue);
        } else {
            $sql = "INSERT INTO " . $this->table . " (title, description, pinned) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException("Prepare failed: " . $this->conn->error);
            }
            $pinnedValue = $this->pinned ? 1 : 0;
            $stmt->bind_param('ssi', $this->title, $this->description, $pinnedValue);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException("Create failed: " . $stmt->error);
        }

        return $stmt->insert_id;
    }

    public function update(int $id, array $data): int {
        $this->id = $id;

        $currentNote = $this->get($id);
        $existingTitle = $currentNote['title'] ?? '';
        $existingDescription = $currentNote['description'] ?? '';
        $existingCategoryId = $currentNote['category_id'] ?? null;
        $existingPinned = isset($currentNote['pinned']) ? (bool) $currentNote['pinned'] : false;

        $category_id = array_key_exists('category_id', $data) ? $data['category_id'] : $existingCategoryId;
        $title       = trim((string) (array_key_exists('title', $data) ? $data['title'] : $existingTitle));
        $description = trim((string) (array_key_exists('description', $data) ? $data['description'] : $existingDescription));
        $pinned      = array_key_exists('pinned', $data) ? (bool) $data['pinned'] : $existingPinned;

        $this->category_id = $category_id !== null && $category_id !== '' ? (int) $category_id : null;
        $this->title = $title;
        $this->description = $description;
        $this->pinned = $pinned;

        if ($this->category_id !== null) {
            $sql = "UPDATE " . $this->table . " SET category_id = ?, title = ?, description = ?, pinned = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException("Prepare failed: " . $this->conn->error);
            }
            $pinnedValue = $this->pinned ? 1 : 0;
            $stmt->bind_param('issii', $this->category_id, $this->title, $this->description, $pinnedValue, $this->id);
        } else {
            $sql = "UPDATE " . $this->table . " SET title = ?, description = ?, pinned = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException("Prepare failed: " . $this->conn->error);
            }
            $pinnedValue = $this->pinned ? 1 : 0;
            $stmt->bind_param('ssii', $this->title, $this->description, $pinnedValue, $this->id);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException("Update failed: " . $stmt->error);
        }
        return $this->id;
    }

    public function togglePin(int $id): bool {
        $this->id = $id;
        $row = $this->get($id);
        if (!$row) {
            return false;
        }

        $nextPinned = ((int) ($row['pinned'] ?? 0)) === 1 ? 0 : 1;
        $sql = "UPDATE " . $this->table . " SET pinned = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error);
        }

        $stmt->bind_param('ii', $nextPinned, $this->id);
        if (!$stmt->execute()) {
            throw new RuntimeException("Update failed: " . $stmt->error);
        }

        return $stmt->affected_rows > 0;
    }

    public function delete(int $id): bool {
        $this->id = $id;

        $sql = "DELETE FROM " . $this->table . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error, 500);
        }

        $stmt->bind_param('i', $this->id);

        if (!$stmt->execute()) {
            throw new RuntimeException("Delete failed: " . $stmt->error);
        }

        return $stmt->affected_rows > 0;
    }
}