<?php
session_start();
// 1. CORRECCIÓN IMPORTANTE: Definir charset UTF-8 en la cabecera
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Verificar que existe el archivo de conexión
if (!file_exists('../config/conexion.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: Falta config/conexion.php']);
    exit();
}

include '../config/conexion.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. LISTAR / BUSCAR ---
    if ($action === 'listar') {
        $q = $_GET['q'] ?? '';
        $sql = "SELECT * FROM productos WHERE 
                LOWER(nombre_producto) LIKE :q OR codigo_barras LIKE :q 
                ORDER BY id_productos DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':q' => "%$q%"]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. CORRECCIÓN IMPORTANTE: Usar JSON_INVALID_UTF8_SUBSTITUTE
        // Esto evita que el JSON se rompa si hay acentos o ñ
        echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    // --- 2. GUARDAR (CREAR O EDITAR) ---
    if ($action === 'guardar') {
        $id = $_POST['id_productos'] ?? ''; // Si viene ID, es editar. Si no, es nuevo.
        $nombre = trim($_POST['nombre_producto']);
        $precio = $_POST['precio_producto'];
        $stock = $_POST['cantidad_piezas'];
        $codigo = trim($_POST['codigo_barras']);
        $ubicacion = $_POST['ubicacion'] ?? ''; 

        // Validación básica
        if (empty($nombre) || empty($precio)) {
            echo json_encode(['success' => false, 'error' => 'Nombre y Precio son obligatorios']);
            exit();
        }

        // Si no hay código, generamos uno aleatorio
        if (empty($codigo)) {
            $codigo = 'PROD' . date('ymd') . rand(100, 999);
        }

        if (!empty($id)) {
            // EDITAR
            $sql = "UPDATE productos SET 
                    nombre_producto = ?, precio_producto = ?, cantidad_piezas = ?, codigo_barras = ?, id_ubicacion = ?
                    WHERE id_productos = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombre, $precio, $stock, $codigo, $ubicacion, $id]);
        } else {
            // NUEVO
            $sql = "INSERT INTO productos (nombre_producto, precio_producto, cantidad_piezas, codigo_barras, id_ubicacion) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombre, $precio, $stock, $codigo, $ubicacion]);
        }

        echo json_encode(['success' => true]);
        exit();
    }

    // --- 3. ELIMINAR ---
    if ($action === 'eliminar') {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Falta ID']);
            exit();
        }
        
        $stmt = $conn->prepare("DELETE FROM productos WHERE id_productos = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 4. OBTENER UN PRODUCTO (Para editar) ---
    if ($action === 'obtener') {
        $id = $_GET['id'] ?? null;
        $stmt = $conn->prepare("SELECT * FROM productos WHERE id_productos = ?");
        $stmt->execute([$id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $prod], JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
}
?>