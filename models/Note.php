<?php

class Note {
    private int $id;
    private int $category_id;
    private string $word;
    private string $definition;
    private string $created_at;
    private string $table = "noter_notes";
    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }
}