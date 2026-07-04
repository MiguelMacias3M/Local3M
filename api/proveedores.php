<?php
// Evitar que imprima cualquier error de PHP en formato HTML
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validación de seguridad (opcional, ajusta según tu sistema)
if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit();
}

// Conexión a la base de datos (asegúrate de que la ruta sea correcta)
require_once '../config/conexion.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // 1. LISTAR PROVEEDORES
    if ($action === 'listar') {
        $stmt = $conn->prepare("SELECT id, empresa FROM proveedores ORDER BY empresa ASC");
        $stmt->execute();
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $proveedores]);
        exit();
    }

    // 2. GUARDAR NUEVO PROVEEDOR (Alta Rápida)
    if ($action === 'guardar') {
        $empresa = trim($_POST['empresa'] ?? '');
        $contacto = trim($_POST['contacto'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        if (empty($empresa)) {
            echo json_encode(['success' => false, 'error' => 'El nombre de la empresa es requerido']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO proveedores (empresa, contacto, telefono) VALUES (:empresa, :contacto, :telefono)");
        $stmt->execute([
            ':empresa' => $empresa,
            ':contacto' => $contacto,
            ':telefono' => $telefono
        ]);

        $nuevoId = $conn->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $nuevoId]);
        exit();
    }

    // Si mandan una acción rara
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>