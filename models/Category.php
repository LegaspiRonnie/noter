<?php 


class Category {
    private int $id;
    private string $name;
    private string $description;
    private mysqli $conn;

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
    
}