<?php

class Category {
    private int $id;
    private string $name;
    private string $description;
    private PDO $conn;
    public string $table = 'noter_categories';

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function getId(?int $id = 0): bool {
        $stmt = $this->conn->prepare('SELECT id FROM ' . $this->table . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    public function existsByName(string $name): bool {
        $stmt = $this->conn->prepare('SELECT id FROM ' . $this->table . ' WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $stmt->execute([':name' => $name]);

        return (bool) $stmt->fetchColumn();
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

        $stmt = $this->conn->prepare('INSERT INTO ' . $this->table . ' (name, description) VALUES (:name, :description) RETURNING id');
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function all(): array {
        $stmt = $this->conn->query('SELECT id, name, description FROM ' . $this->table . ' ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}