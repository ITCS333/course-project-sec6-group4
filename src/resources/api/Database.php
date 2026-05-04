<?php

class Database {
    public $conn;

    public function getConnection() {
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

            $this->conn->exec("
                INSERT INTO resources (title, description, link)
                SELECT 'MDN Web Docs', 'Web development documentation', 'https://developer.mozilla.org'
                WHERE NOT EXISTS (SELECT 1 FROM resources WHERE title = 'MDN Web Docs');
            ");

            $this->conn->exec("
                INSERT INTO resources (title, description, link)
                SELECT 'Course Syllabus', 'Course syllabus and weekly topics', 'https://example.com/syllabus'
                WHERE NOT EXISTS (SELECT 1 FROM resources WHERE title = 'Course Syllabus');
            ");

            $this->conn->exec("
                INSERT INTO comments_resource (resource_id, author, text)
                SELECT 1, 'Student', 'This is a helpful resource.'
                WHERE NOT EXISTS (SELECT 1 FROM comments_resource WHERE text = 'This is a helpful resource.');
            ");

        } catch(PDOException $exception) {
            echo 'Connection error: ' . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>
