<?php
session_start();

// 1. Configuración
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// --- CONTRASEÑA DE ADMINISTRADOR (ESTÁTICA) ---
$PASSWORD_MAESTRA = "1234"; 
// -----------------------------------------------

// 2. Seguridad de sesión
if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

// 3. Leer datos JSON
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$password = $data['password'] ?? '';

// 4. Validaciones
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no especificado']);
    exit();
}

// 5. VERIFICAR CONTRASEÑA
if ($password !== $PASSWORD_MAESTRA) {
    // Simulamos un pequeño retraso para evitar ataques de fuerza bruta rápidos
    sleep(1); 
    echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta']);
    exit();
}

try {
    // 6. Eliminar
    $stmt = $conn->prepare("DELETE FROM reparaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontró la reparación o ya fue eliminada']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
}
?>