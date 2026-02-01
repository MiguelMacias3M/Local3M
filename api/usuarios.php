<?php
/* API USUARIOS (Sin Fecha de Creación) */
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

session_start();
include '../config/conexion.php';

$action = $_REQUEST['action'] ?? '';

try {
    // 1. LISTAR (Quitamos fecha_creacion de la consulta)
    if ($action === 'listar') {
        $stmt = $conn->query("SELECT id, nombre, rol FROM usuarios ORDER BY id ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. GUARDAR / EDITAR
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? '';
        $nombre = $_POST['nombre'];
        $rol = $_POST['rol'];
        $password = $_POST['password'];

        if (empty($nombre) || empty($rol)) throw new Exception("Nombre y Rol obligatorios");

        if (empty($id)) {
            // NUEVO
            if (empty($password)) throw new Exception("Contraseña obligatoria");
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            // Insertamos SIN fecha
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
        if ($id == 1) throw new Exception("No puedes eliminar al Admin principal."); 
        $conn->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>