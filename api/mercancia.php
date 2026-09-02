<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if (!file_exists('../config/conexion.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: Falta config/conexion.php']);
    exit();
}

include '../config/conexion.php';

if (isset($conn)) {
    try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {}
}

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. LISTAR / BUSCAR (FILTRANDO SOLO ACTIVOS) ---
    if ($action === 'listar') {
        $q = $_GET['q'] ?? '';
        
        // Búsqueda directa en la misma tabla (mucho más rápido sin JOIN)
        $sql = "SELECT * FROM mercancia 
                WHERE (estado = 'Activo' OR estado IS NULL) AND (
                    LOWER(tipo_repuesto) LIKE ? OR
                    LOWER(marca) LIKE ? OR 
                    LOWER(modelo) LIKE ? OR 
                    LOWER(codigo_barras) LIKE ? OR
                    LOWER(compatibilidad) LIKE ? OR
                    LOWER(ubicacion) LIKE ?
                )
                ORDER BY marca, modelo LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $term = "%" . strtolower($q) . "%";
        $stmt->execute([$term, $term, $term, $term, $term, $term]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            array_walk_recursive($data, function(&$item) {
                if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) $item = utf8_encode($item); 
            });
            echo json_encode(['success' => true, 'data' => $data]);
        }
        exit();
    }

    // --- 2. GUARDAR (CREAR O EDITAR CON TEXTO LIBRE) ---
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? '';
        
        $tipo_select = trim($_POST['tipo_repuesto_select'] ?? 'Pantalla');
        $tipo_otro = trim($_POST['tipo_repuesto_otro'] ?? '');
        $tipo_repuesto = ($tipo_select === 'Otro' && !empty($tipo_otro)) ? $tipo_otro : $tipo_select;

        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $cantidad = (int)($_POST['cantidad'] ?? 0);
        $compatibilidad = trim($_POST['compatibilidad'] ?? '');
        $costo = (float)($_POST['costo'] ?? 0);
        $precio_publico = $_POST['precio_publico'] ?? 0;
        $ubicacion = trim($_POST['ubicacion'] ?? ''); // <- TEXTO LIBRE DIRECTO
        $codigo = trim($_POST['codigo_barras'] ?? '');

        if (empty($marca) || empty($modelo) || empty($tipo_repuesto)) {
            throw new Exception('Tipo, Marca y Modelo son obligatorios');
        }

        if (empty($codigo)) {
            $codigo = 'MER' . date('ymd') . rand(100, 999);
        }

        if (empty($id)) {
            // NUEVO (Se marca como Activo por defecto)
            $sql = "INSERT INTO mercancia (tipo_repuesto, marca, modelo, compatibilidad, cantidad, costo, precio_publico, ubicacion, codigo_barras, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tipo_repuesto, $marca, $modelo, $compatibilidad, $cantidad, $costo, $precio_publico, $ubicacion, $codigo]);
        } else {
            // ACTUALIZAR
            $sql = "UPDATE mercancia SET 
                    tipo_repuesto = ?, marca = ?, modelo = ?, compatibilidad = ?, 
                    cantidad = ?, costo = ?, precio_publico = ?, ubicacion = ?, codigo_barras = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tipo_repuesto, $marca, $modelo, $compatibilidad, $cantidad, $costo, $precio_publico, $ubicacion, $codigo, $id]);
        }

        echo json_encode(['success' => true]);
        exit();
    }

    // --- 3. MODIFICAR STOCK RÁPIDO (+ / -) ---
    if ($action === 'stock') {
        $id = $_POST['id'];
        $tipo = $_POST['tipo'];

        if ($tipo === 'sumar') {
            $sql = "UPDATE mercancia SET cantidad = cantidad + 1 WHERE id = ?";
        } else {
            $sql = "UPDATE mercancia SET cantidad = cantidad - 1 WHERE id = ? AND cantidad > 0";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 4. ELIMINAR (BAJA LÓGICA / SOFT DELETE) ---
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        
        // Cambiamos el DELETE por un UPDATE de estado para proteger los cortes de caja
        $stmt = $conn->prepare("UPDATE mercancia SET estado = 'Inactivo' WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    // --- 5. OBTENER UNA (PARA EDITAR) ---
    if ($action === 'obtener') {
        $id = $_GET['id'];
        
        // Simplificado sin JOIN
        $stmt = $conn->prepare("SELECT * FROM mercancia WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
            } else {
                array_walk_recursive($data, function(&$item) {
                    if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) $item = utf8_encode($item);
                });
                echo json_encode(['success' => true, 'data' => $data]);
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