<?php
/* API USUARIOS (PROTEGIDA CON $MASTER_PASSWORD) */
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

session_start();
// Al incluir esto, la variable $MASTER_PASSWORD queda disponible aquí
include '../config/conexion.php';

$action = $_REQUEST['action'] ?? '';

try {
    // 1. LISTAR (Público para el admin, no pide clave)
    if ($action === 'listar') {
        $stmt = $conn->query("SELECT id, nombre, rol FROM usuarios ORDER BY id ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- ZONA DE SEGURIDAD ---
    // Si la acción es guardar o eliminar, exigimos la contraseña maestra
    if ($action === 'guardar' || $action === 'eliminar') {
        $clave_enviada = $_POST['master_key'] ?? '';
        
        // 1. Verificamos que tu variable exista (cargada desde conexion.php)
        if (!isset($MASTER_PASSWORD)) {
            throw new Exception("Error de seguridad: Variable MASTER_PASSWORD no encontrada en el servidor.");
        }

        // 2. Comparamos la clave enviada con la tuya ("1234")
        // Nota: Usamos (string) para asegurar que se comparen como texto
        if ((string)$clave_enviada !== (string)$MASTER_PASSWORD) {
            throw new Exception("⛔ Contraseña Maestra INCORRECTA. Acción denegada.");
        }
    }
    // -------------------------

    // 2. GUARDAR / EDITAR
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? '';
        $nombre = $_POST['nombre'];
        $rol = $_POST['rol'];
        $password = $_POST['password'];

        if (empty($nombre) || empty($rol)) throw new Exception("Faltan datos obligatorios.");

        if (empty($id)) {
            // NUEVO
            if (empty($password)) throw new Exception("La contraseña es obligatoria para nuevos usuarios");
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nombre, password, rol) VALUES (?, ?, ?)";
            $conn->prepare($sql)->execute([$nombre, $passHash, $rol]);
        } else {
            // EDITAR
            if (!empty($password)) {
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nombre=?, rol=?, password=? WHERE id=?";
                $conn->prepare($sql)->execute([$nombre, $rol, $passHash, $id]);
            } else {
                $sql = "UPDATE usuarios SET nombre=?, rol=? WHERE id=?";
                $conn->prepare($sql)->execute([$nombre, $rol, $id]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 3. ELIMINAR
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        if ($id == 1) throw new Exception("No puedes eliminar al usuario principal."); 
        $conn->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>