<?php

class Note {
    private int $id;
    private ?int $category_id = null;
    private string $title;
    private string $description;
    private bool $pinned = false;
    private string $table = "noter_notes";
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function get(?int $id = null, ?string $search = null, ?int $category_id = null, int $page = 1, int $limit = 10): array|null {
        if ($id !== null && $id > 0) {
            $sql = "SELECT n.*, c.name AS category_name
                    FROM " . $this->table . " n
                    LEFT JOIN noter_categories c ON c.id = n.category_id
                    WHERE n.id = :id
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $searchTerm = '%' . trim($search) . '%';
            $where[] = '(n.title ILIKE :search_title OR n.description ILIKE :search_description OR c.name ILIKE :search_category)';
            $params[':search_title'] = $searchTerm;
            $params[':search_description'] = $searchTerm;
            $params[':search_category'] = $searchTerm;
        }

        if ($category_id !== null && $category_id > 0) {
            $where[] = 'n.category_id = :category_id';
            $params[':category_id'] = $category_id;
        }

        $countSql = "SELECT COUNT(*) AS total
                     FROM " . $this->table . " n
                     LEFT JOIN noter_categories c ON c.id = n.category_id";
        if (!empty($where)) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }

        $countStmt = $this->conn->prepare($countSql);
        $countStmt->execute($params);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($countRow['total'] ?? 0);

        $sql = "SELECT n.*, c.name AS category_name
                FROM " . $this->table . " n
                LEFT JOIN noter_categories c ON c.id = n.category_id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY c.name ASC, n.pinned DESC, n.created_at DESC LIMIT :limit OFFSET :offset';

        $statement = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $notes = $statement->fetchAll(PDO::FETCH_ASSOC);

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
            $sql = 'INSERT INTO ' . $this->table . ' (category_id, title, description, pinned) VALUES (:category_id, :title, :description, :pinned) RETURNING id';
            $params = [
                ':category_id' => $this->category_id,
                ':title' => $this->title,
                ':description' => $this->description,
                ':pinned' => $this->pinned,
            ];
        } else {
            $sql = 'INSERT INTO ' . $this->table . ' (title, description, pinned) VALUES (:title, :description, :pinned) RETURNING id';
            $params = [
                ':title' => $this->title,
                ':description' => $this->description,
                ':pinned' => $this->pinned,
            ];
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
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
            $sql = 'UPDATE ' . $this->table . ' SET category_id = :category_id, title = :title, description = :description, pinned = :pinned WHERE id = :id';
            $params = [
                ':category_id' => $this->category_id,
                ':title' => $this->title,
                ':description' => $this->description,
                ':pinned' => $this->pinned,
                ':id' => $this->id,
            ];
        } else {
            $sql = 'UPDATE ' . $this->table . ' SET title = :title, description = :description, pinned = :pinned WHERE id = :id';
            $params = [
                ':title' => $this->title,
                ':description' => $this->description,
                ':pinned' => $this->pinned,
                ':id' => $this->id,
            ];
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $this->id;
    }

    public function togglePin(int $id): bool {
        $this->id = $id;
        $row = $this->get($id);
        if (!$row) {
            return false;
        }

        $currentPinned = (bool) ($row['pinned'] ?? false);
        $nextPinned = !$currentPinned;

        $stmt = $this->conn->prepare('UPDATE ' . $this->table . ' SET pinned = :pinned WHERE id = :id');
        $stmt->execute([
            ':pinned' => $nextPinned,
            ':id' => $this->id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $this->id = $id;
        $stmt = $this->conn->prepare('DELETE FROM ' . $this->table . ' WHERE id = :id');
        $stmt->execute([':id' => $this->id]);

        return $stmt->rowCount() > 0;
    }
}