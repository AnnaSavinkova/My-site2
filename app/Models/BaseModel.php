<?php
// app/Models/BaseModel.php
require_once __DIR__ . '/../../config/db.php';
class BaseModel {
    protected $pdo;
    public function __construct(){ global $pdo; $this->pdo = $pdo; }
}
