<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. LISTAR / BUSCAR ---
    if ($action === 'listar') {
        $q = $_GET['q'] ?? '';
        // CAMBIO: Tabla 'mercancia'
        $sql = "SELECT m.*, u.ubicacion 
                FROM mercancia m
                LEFT JOIN ubicacion_stock u ON m.id_ubicacion = u.id
                WHERE 
                    LOWER(m.marca) LIKE :q OR 
                    LOWER(m.modelo) LIKE :q OR 
                    LOWER(m.codigo_barras) LIKE :q
                ORDER BY m.marca, m.modelo LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $term = "%" . strtolower($q) . "%";
        $stmt->execute([':q' => $term]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    // --- 2. GUARDAR (CREAR O EDITAR) ---
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? '';
        $marca = trim($_POST['marca']);
        $modelo = trim($_POST['modelo']);
        $cantidad = (int)$_POST['cantidad'];
        $compatibilidad = trim($_POST['compatibilidad']);
        $costo = (float)$_POST['costo'];
        $ubicacionTexto = trim($_POST['ubicacion']);
        $codigo = trim($_POST['codigo_barras']);

        if (empty($marca) || empty($modelo)) {
            echo json_encode(['success' => false, 'error' => 'Marca y Modelo son obligatorios']);
            exit();
        }

        // Manejo de Ubicación
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

        // Generar código si está vacío (Prefijo MER)
        if (empty($codigo)) {
            $codigo = 'MER' . date('ymd') . rand(100, 999);
        }

        if (!empty($id)) {
            // EDITAR (Tabla mercancia)
            $sql = "UPDATE mercancia SET 
                    marca = ?, modelo = ?, cantidad = ?, compatibilidad = ?, 
                    costo = ?, id_ubicacion = ?, codigo_barras = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$marca, $modelo, $cantidad, $compatibilidad, $costo, $id_ubicacion, $codigo, $id]);
        } else {
            // NUEVO (Tabla mercancia)
            $sql = "INSERT INTO mercancia (marca, modelo, cantidad, compatibilidad, costo, id_ubicacion, codigo_barras) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$marca, $modelo, $cantidad, $compatibilidad, $costo, $id_ubicacion, $codigo]);
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
    
    // --- 5. OBTENER UNA ---
    if ($action === 'obtener') {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT m.*, u.ubicacion FROM mercancia m LEFT JOIN ubicacion_stock u ON m.id_ubicacion = u.id WHERE m.id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>