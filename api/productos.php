<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- DEBUG TEMPORAL: ACTIVAR PARA VER ERRORES REALES ---
ini_set('display_errors', 0); // Lo ponemos en 0 para producción, cámbialo a 1 si necesitas ver errores en pantalla
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

// 3. FORZAR UTF-8 EN LA BASE DE DATOS
if (isset($conn)) {
    try {
        $conn->exec("SET NAMES 'utf8'");
    } catch (Exception $e) {
        // Ignoramos si falla
    }
}

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. LISTAR / BUSCAR (CORREGIDO ERROR HY093) ---
    if ($action === 'listar') {
        $q = $_GET['q'] ?? '';
        
        // CAMBIO: Usamos '?' en lugar de ':q' repetido para máxima compatibilidad
        $sql = "SELECT * FROM productos WHERE 
                LOWER(nombre_producto) LIKE ? OR codigo_barras LIKE ? 
                ORDER BY id_productos DESC LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        // Enviamos el parámetro dos veces: una para nombre y otra para código
        $stmt->execute(["%$q%", "%$q%"]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // --- BLINDAJE PARA VERSIONES ANTIGUAS DE PHP ---
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            // FALLBACK: Limpieza manual de acentos
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
        if (!$id) throw new Exception('Falta ID');
        
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