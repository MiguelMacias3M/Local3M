<?php
/*
 * API: ENCARGOS
 * Gestiona la creación, listado y completado de encargos.
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../config/conexion.php';

// Validar sesión
if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    // --- 1. LISTAR ENCARGOS PENDIENTES ---
    if ($action === 'listar') {
        // Obtenemos los pendientes primero, y luego los completados recientes (últimos 5)
        $stmt = $conn->query("SELECT * FROM encargos WHERE estado = 'pendiente' ORDER BY fecha_registro DESC");
        $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query("SELECT * FROM encargos WHERE estado = 'completado' ORDER BY fecha_completado DESC LIMIT 5");
        $completados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'pendientes' => $pendientes, 'completados' => $completados]);
        exit;
    }

    // --- 2. CREAR NUEVO ENCARGO ---
    if ($action === 'crear') {
        $data = json_decode(file_get_contents('php://input'), true);
        $descripcion = trim($data['descripcion'] ?? '');

        if (empty($descripcion)) {
            echo json_encode(['success' => false, 'error' => 'La descripción no puede estar vacía']);
            exit;
        }

        $sql = "INSERT INTO encargos (descripcion, usuario, fecha_registro) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$descripcion, $_SESSION['nombre']]);

        echo json_encode(['success' => true]);
        exit;
    }

    // --- 3. MARCAR COMO COMPLETADO (PALOMEAR) ---
    if ($action === 'completar') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }

        $sql = "UPDATE encargos SET estado = 'completado', fecha_completado = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    }

    // --- 4. ELIMINAR (OPCIONAL) ---
    if ($action === 'eliminar') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        $sql = "DELETE FROM encargos WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>