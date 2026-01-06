<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- DEBUG TEMPORAL: PONER EN 1 SI FALLA PARA VER ERROR ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Verificar sesión
if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// 2. Verificar conexión
if (!file_exists('../config/conexion.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: Falta config/conexion.php']);
    exit();
}

include '../config/conexion.php';

// 3. FORZAR UTF-8 EN LA BASE DE DATOS (CRUCIAL PARA ACENTOS)
if (isset($conn)) {
    try {
        $conn->exec("SET NAMES 'utf8'");
    } catch (Exception $e) {
        // Ignoramos si falla
    }
}

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. LISTAR / BUSCAR ---
    if ($action === 'listar') {
        $q = $_GET['q'] ?? '';
        
        // CORRECCIÓN SQL: Usamos '?' para evitar el error HY093
        // IMPORTANTE: Cambia 'productos' por el nombre de tu tabla de mercancía si es diferente
        $sql = "SELECT * FROM productos WHERE 
                LOWER(nombre_producto) LIKE ? OR codigo_barras LIKE ? 
                ORDER BY id_productos DESC LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        // Enviamos el parámetro dos veces
        $stmt->execute(["%$q%", "%$q%"]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // --- BLINDAJE JSON (PARA PHP ANTIGUO) ---
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            // Limpieza manual si no existe la constante moderna
            array_walk_recursive($data, function(&$item) {
                if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) {
                    $item = utf8_encode($item); 
                }
            });
            echo json_encode(['success' => true, 'data' => $data]);
        }
        exit();
    }

    // --- 2. GUARDAR (CREAR O EDITAR) ---
    if ($action === 'guardar') {
        // Asegúrate que estos nombres coincidan con los `name` de tu formulario HTML
        $id = $_POST['id_productos'] ?? '';
        $nombre = trim($_POST['nombre_producto']);
        $precio = $_POST['precio_producto'];
        $stock = $_POST['cantidad_piezas'];
        $codigo = trim($_POST['codigo_barras']);
        $ubicacion = $_POST['ubicacion'] ?? ''; 

        if (empty($nombre) || empty($precio)) {
            throw new Exception('Nombre y Precio son obligatorios');
        }

        if (empty($codigo)) {
            $codigo = 'MERC' . date('ymd') . rand(100, 999); // Generamos código tipo MERC...
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
        if (!$id) throw new Exception('Falta ID');
        
        // Cambia 'id_productos' por el ID de tu tabla mercancía
        $stmt = $conn->prepare("DELETE FROM productos WHERE id_productos = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 4. OBTENER (PARA EDITAR) ---
    if ($action === 'obtener') {
        $id = $_GET['id'] ?? null;
        $stmt = $conn->prepare("SELECT * FROM productos WHERE id_productos = ?");
        $stmt->execute([$id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prod) {
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                echo json_encode(['success' => true, 'data' => $prod], JSON_INVALID_UTF8_SUBSTITUTE);
            } else {
                array_walk_recursive($prod, function(&$item) {
                    if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) {
                        $item = utf8_encode($item);
                    }
                });
                echo json_encode(['success' => true, 'data' => $prod]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'No encontrado']);
        }
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>