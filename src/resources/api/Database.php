<?php

class Database {
    private $host = "localhost";
    private $db_name = "test_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "sqlite::memory:"
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // create tables
            $this->conn->exec("
                CREATE TABLE resources (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT,
                    description TEXT,
                    link TEXT
                );
            ");

            $this->conn->exec("
                CREATE TABLE comments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    resource_id INTEGER,
                    author TEXT,
                    text TEXT
                );
            ");

        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
