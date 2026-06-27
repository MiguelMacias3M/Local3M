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
    // --- 1. LISTAR / BUSCAR ---
    if ($action === 'listar') {
        $q = $_GET['q'] ?? '';
        
        // Se agregó la búsqueda por el nuevo campo "tipo_repuesto"
        $sql = "SELECT m.*, u.ubicacion 
                FROM mercancia m
                LEFT JOIN ubicacion_stock u ON m.id_ubicacion = u.id
                WHERE 
                    LOWER(m.tipo_repuesto) LIKE ? OR
                    LOWER(m.marca) LIKE ? OR 
                    LOWER(m.modelo) LIKE ? OR 
                    LOWER(m.codigo_barras) LIKE ?
                ORDER BY m.marca, m.modelo LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $term = "%" . strtolower($q) . "%";
        // Ahora pasamos 4 parámetros porque hay 4 signos de interrogación
        $stmt->execute([$term, $term, $term, $term]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        $id = $_POST['id'] ?? '';
        $tipo_repuesto = trim($_POST['tipo_repuesto'] ?? 'Pantalla'); // NUEVO CAMPO
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $cantidad = (int)($_POST['cantidad'] ?? 0);
        $compatibilidad = trim($_POST['compatibilidad'] ?? '');
        $costo = (float)($_POST['costo'] ?? 0);
        $ubicacionTexto = trim($_POST['ubicacion'] ?? '');
        $codigo = trim($_POST['codigo_barras'] ?? '');

        if (empty($marca) || empty($modelo) || empty($tipo_repuesto)) {
            throw new Exception('Tipo, Marca y Modelo son obligatorios');
        }

        $id_ubicacion = null;
        if (!empty($ubicacionTexto)) {
            $stmtUb = $conn->prepare("SELECT id FROM ubicacion_stock WHERE ubicacion = ?");
            $stmtUb->execute([$ubicacionTexto]);
            $rowUb = $stmtUb->fetch(PDO::FETCH_ASSOC);

            if ($rowUb) {
                $id_ubicacion = $rowUb['id'];
            } else {
                $stmtInsUb = $conn->prepare("INSERT INTO ubicacion_stock (ubicacion) VALUES (?)");
                $stmtInsUb->execute([$ubicacionTexto]);
                $id_ubicacion = $conn->lastInsertId();
            }
        }

        if (empty($codigo)) {
            $codigo = 'MER' . date('ymd') . rand(100, 999);
        }

        if (!empty($id)) {
            // EDITAR (Añadido tipo_repuesto)
            $sql = "UPDATE mercancia SET 
                    tipo_repuesto = ?, marca = ?, modelo = ?, cantidad = ?, compatibilidad = ?, 
                    costo = ?, id_ubicacion = ?, codigo_barras = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tipo_repuesto, $marca, $modelo, $cantidad, $compatibilidad, $costo, $id_ubicacion, $codigo, $id]);
        } else {
            // NUEVO (Añadido tipo_repuesto)
            $sql = "INSERT INTO mercancia (tipo_repuesto, marca, modelo, cantidad, compatibilidad, costo, id_ubicacion, codigo_barras) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tipo_repuesto, $marca, $modelo, $cantidad, $compatibilidad, $costo, $id_ubicacion, $codigo]);
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

    // --- 4. ELIMINAR ---
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM mercancia WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit();
    }
    
    // --- 5. OBTENER UNA (PARA EDITAR) ---
    if ($action === 'obtener') {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT m.*, u.ubicacion FROM mercancia m LEFT JOIN ubicacion_stock u ON m.id_ubicacion = u.id WHERE m.id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
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