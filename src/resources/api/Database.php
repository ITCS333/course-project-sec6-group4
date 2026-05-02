<?php

class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("sqlite:database.db");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS resources (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT,
                    description TEXT,
                    link TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
            ");

            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS comments_resource (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    resource_id INTEGER,
                    author TEXT,
                    text TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
            ");

        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>

