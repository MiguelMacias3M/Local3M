<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Validar que el usuario sea administrador
if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../config/conexion.php';

// Recibir datos por POST
$id = $_POST['id'] ?? null;
$llave = $_POST['llave_maestra'] ?? '';

// Verificar que venga el ID
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no especificado']);
    exit();
}

try {
    // 🚨 AQUÍ EL CAMBIO: Validar usando la variable correcta $MASTER_PASSWORD
    if (!isset($MASTER_PASSWORD)) {
        throw new Exception("Error interno: Llave Maestra no configurada en la conexión.");
    }
    
    if ($llave !== $MASTER_PASSWORD) {
        echo json_encode(['success' => false, 'error' => 'La Llave Maestra es incorrecta.']);
        exit();
    }

    // Proceder a eliminar de la base de datos
    $stmt = $conn->prepare("DELETE FROM reparaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
}
?>