<?php
require_once __DIR__ . '/../includes/db.php';

abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }
}
