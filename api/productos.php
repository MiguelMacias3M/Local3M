<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- DEBUG TEMPORAL: ACTIVAR PARA VER ERRORES REALES ---
ini_set('display_errors', 0); // Lo ponemos en 0 para producción
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
        
        // ==========================================
        // LA MAGIA: Capturamos la señal secreta
        // ==========================================
        $todo = $_GET['todo'] ?? 0; 
        
        // Armamos la consulta BASE sin el límite
        $sql = "SELECT p.*, u.ubicacion as nombre_ubicacion 
                FROM productos p
                LEFT JOIN ubicacion_stock u ON p.id_ubicacion = u.id
                WHERE 
                LOWER(p.nombre_producto) LIKE ? OR p.codigo_barras LIKE ? 
                ORDER BY p.id_productos DESC";
        
        // Si no nos mandaron la señal, protegemos el sistema con el LÍMITE de 50
        if ($todo != 1) {
            $sql .= " LIMIT 50";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(["%$q%", "%$q%"]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // --- BLINDAJE PARA VERSIONES ANTIGUAS DE PHP ---
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
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
        // Recibimos 'ubicacion' que puede ser el texto o el ID (depende de tu frontend)
        $ubicacionInput = trim($_POST['ubicacion'] ?? ''); 

        if (empty($nombre) || empty($precio)) {
            throw new Exception('Nombre y Precio son obligatorios');
        }

        // --- MANEJO DE UBICACIÓN ---
        $idUbicacionFinal = null;

        if (!empty($ubicacionInput)) {
            // 1. Verificamos si lo que llegó es un número (ID existente)
            if (ctype_digit($ubicacionInput)) {
                $stmtCheck = $conn->prepare("SELECT id FROM ubicacion_stock WHERE id = ?");
                $stmtCheck->execute([$ubicacionInput]);
                if ($stmtCheck->fetch()) {
                    $idUbicacionFinal = $ubicacionInput;
                } else {
                    $idUbicacionFinal = null; 
                }
            } else {
                // 2. Es un TEXTO (ej: "Estante A"), buscamos su ID o lo creamos
                $stmtBusca = $conn->prepare("SELECT id FROM ubicacion_stock WHERE ubicacion = ?");
                $stmtBusca->execute([$ubicacionInput]);
                $rowUbi = $stmtBusca->fetch(PDO::FETCH_ASSOC);

                if ($rowUbi) {
                    $idUbicacionFinal = $rowUbi['id'];
                } else {
                    $stmtIns = $conn->prepare("INSERT INTO ubicacion_stock (ubicacion) VALUES (?)");
                    $stmtIns->execute([$ubicacionInput]);
                    $idUbicacionFinal = $conn->lastInsertId();
                }
            }
        }

        // Generar código si hace falta
        if (empty($codigo)) {
            $codigo = 'PROD' . date('ymd') . rand(100, 999);
        }

        if (!empty($id)) {
            // EDITAR
            $sql = "UPDATE productos SET 
                    nombre_producto = ?, precio_producto = ?, cantidad_piezas = ?, codigo_barras = ?, id_ubicacion = ?
                    WHERE id_productos = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombre, $precio, $stock, $codigo, $idUbicacionFinal, $id]);
        } else {
            // NUEVO
            $sql = "INSERT INTO productos (nombre_producto, precio_producto, cantidad_piezas, codigo_barras, id_ubicacion) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombre, $precio, $stock, $codigo, $idUbicacionFinal]);
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
        $stmt = $conn->prepare("SELECT p.*, u.ubicacion as nombre_ubicacion 
                                FROM productos p 
                                LEFT JOIN ubicacion_stock u ON p.id_ubicacion = u.id 
                                WHERE p.id_productos = ?");
        $stmt->execute([$id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prod) {
            $prod['ubicacion'] = $prod['nombre_ubicacion'] ?? ''; 

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