<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// 1. Seguridad de sesión
if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// 2. Incluir configuración
// ALERTA DE SEGURIDAD: La variable $MASTER_PASSWORD ahora viene de este archivo
// y NO está escrita aquí para que no sea visible en GitHub.
include '../config/conexion.php';

// Verificación de seguridad por si olvidaste poner la clave en conexion.php
if (!isset($MASTER_PASSWORD)) {
    echo json_encode(['success' => false, 'error' => 'Error de configuración: Clave Maestra no definida en el servidor.']);
    exit();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    
    // --- REGISTRAR NUEVO USUARIO ---
    if ($action === 'registrar') {
        $nuevo_usuario = trim($_POST['nuevo_usuario']);
        $nueva_pass = $_POST['nueva_password'];
        $confirm_pass = $_POST['confirm_password'];
        $admin_pass = $_POST['admin_password'];

        if (empty($nuevo_usuario) || empty($nueva_pass)) {
            echo json_encode(['success' => false, 'error' => 'Todos los campos son obligatorios']);
            exit();
        }

        if ($nueva_pass !== $confirm_pass) {
            echo json_encode(['success' => false, 'error' => 'Las contraseñas no coinciden']);
            exit();
        }

        // VERIFICAR CLAVE MAESTRA
        if ($admin_pass !== $MASTER_PASSWORD) {
            sleep(1); // Pausa anti-fuerza bruta
            echo json_encode(['success' => false, 'error' => 'Contraseña Maestra incorrecta. No tienes permiso.']);
            exit();
        }

        // Verificar si existe
        $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE nombre = :nombre");
        $stmtCheck->execute([':nombre' => $nuevo_usuario]);
        if ($stmtCheck->rowCount() > 0) {
            echo json_encode(['success' => false, 'error' => 'El usuario ya existe']);
            exit();
        }

        // Encriptar e insertar
        $hash = password_hash($nueva_pass, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (nombre, password) VALUES (:nombre, :pass)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':nombre' => $nuevo_usuario, ':pass' => $hash]);

        echo json_encode(['success' => true]);
        exit();
    }

    // --- LISTAR USUARIOS ---
    if ($action === 'listar') {
        $stmt = $conn->query("SELECT id, nombre FROM usuarios ORDER BY id ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

    // --- ELIMINAR USUARIO ---
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        $admin_pass = $_POST['admin_password'];

        if ($admin_pass !== $MASTER_PASSWORD) {
            echo json_encode(['success' => false, 'error' => 'Contraseña Maestra incorrecta']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>