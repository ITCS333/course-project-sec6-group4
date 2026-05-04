<?php
require_once __DIR__ . '/db.php';

class Database {
    public $conn;

    public function getConnection() {
        $this->conn = getDBConnection();
        return $this->conn;
    }
}
?>
