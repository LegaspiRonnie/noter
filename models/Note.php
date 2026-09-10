<?php

class Note {
    private int $id;
    private int $category_id;
    private string $title;
    private string $description;
    private string $created_at;
    private string $table = "noter_notes";
    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    public function get(?int $id = null): array|null {

        if ($id !== null && $id > 0) {

            $sql = "SELECT * FROM " . $this->table . " WHERE id = ? LIMIT 1";
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

        $sql = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error);
        }

        if(!$stmt->execute()) {
                throw new RuntimeException("Fetch failed: " . $stmt->error); 
        }

        $result = $stmt->get_result();
        $notes = $result->fetch_all(MYSQLI_ASSOC);
        return $notes;
    }
    public function create(array $data): int {
        // I normalized the incoming values so create() is safe for optional category_id and consistent text payloads.
        $category_id = $data['category_id'] ?? null;
        $title       = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        $sql = "INSERT INTO " . $this->table . " (category_id, title, description) " . 
               "VALUES (?,?,?)";

        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error);
        }

        $this->category_id = $category_id !== null && $category_id !== '' ? (int) $category_id : 0;
        $this->title       = $title;
        $this->description = $description;

        $stmt->bind_param('iss', $this->category_id, 
                                 $this->title, 
                                 $this->description);

        if (!$stmt->execute()) {
            throw new RuntimeException("Create failed: " . $stmt->error);
        }

        return $stmt->insert_id;
    }
    public function update(int $id, array $data): int {
        // I kept the update payload consistent with the API validation so the method updates the actual request values instead of stale data.
        $this->id = $id;

        $category_id = $data['category_id'] ?? null;
        $title       = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        $sql = "UPDATE " . $this->table .
               " SET category_id = ?, title = ?, description = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error);
        }

        $this->category_id = $category_id !== null && $category_id !== '' ? (int) $category_id : 0;
        $this->title       = $title;
        $this->description = $description;

        $stmt->bind_param('issi',
                            $this->category_id,
                            $this->title,
                            $this->description,
                            $this->id);

        if (!$stmt->execute()) {
            throw new RuntimeException("Update failed: " . $stmt->error);
        }
        return $this->id;
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