<?php 


class Category {
    private int $id;
    private string $name;
    private string $description;
    private mysqli $conn;
    public string $table = 'noter_categories';

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    public function getId(?int $id = 0): bool {
        $sql = "SELECT id FROM noter_categories WHERE id = ?";
        $stmt = $this->conn->prepare($sql); 
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();

        if (!empty($category)) {
            return true;
        } else {
            return false;
        }
    }

    public function existsByName(string $name): bool {
        $sql = "SELECT id FROM " . $this->table . " WHERE LOWER(name) = LOWER(?) LIMIT 1";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException("Failed to prepare category existence query.");
        }

        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->num_rows > 0;
    }

    public function create(array $data): int {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.', 400);
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException('Category name cannot be longer than 100 characters.', 400);
        }

        if (strlen($description) > 255) {
            throw new InvalidArgumentException('Category description cannot be longer than 255 characters.', 400);
        }

        if ($this->existsByName($name)) {
            throw new InvalidArgumentException('Category already exists.', 409);
        }

        $sql = "INSERT INTO " . $this->table . " (name, description) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException('Failed to prepare category insert query.');
        }

        $stmt->bind_param('ss', $name, $description);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to create category.');
        }

        return $stmt->insert_id;
    }

    public function all(): array {
        $sql = "SELECT id, name, description FROM " . $this->table . " ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException("Failed to prepare category query.");
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];

        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }
    
}