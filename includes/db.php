<?php
require_once 'config.php';

class Database {
    private $conn;
    private static $instance;
    
    // Constructor privado para patrón Singleton
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    // Método para obtener la instancia (patrón Singleton)
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Obtener conexión
    public function getConnection() {
        return $this->conn;
    }
    
    // Preparar consulta
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    // Ejecutar consulta simple
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    // Ejecutar consulta con parámetros
    public function execute($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    // Obtener un solo registro
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    // Obtener todos los registros
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Obtener el ID del último registro insertado
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    // Iniciar transacción
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    // Confirmar transacción
    public function commit() {
        return $this->conn->commit();
    }
    
    // Revertir transacción
    public function rollBack() {
        return $this->conn->rollBack();
    }
}