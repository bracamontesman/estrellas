<?php
require_once __DIR__ . '/../../config/db.php';

class Database {
    private $conexion;
    private static $instancia = null;

    private function __construct() {
        try {
            $this->conexion = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die($ERRORES['db_conexion'] . ': ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    public function getConexion() {
        return $this->conexion;
    }
}