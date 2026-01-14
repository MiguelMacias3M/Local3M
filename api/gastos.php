<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';
if (isset($conn)) { try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {} }

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    // ---------------------------------------------------------
    // 1. LISTAR MOVIMIENTOS (FILTRO INTELIGENTE)
    // ---------------------------------------------------------
    if ($action === 'listar') {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $tipoFiltro = $_GET['tipo'] ?? ''; // 'INGRESO', 'GASTO', o vacío

        // Definir rango del día completo (00:00:00 a 23:59:59)
        $inicioDia = $fecha . ' 00:00:00';
        $finDia = $fecha . ' 23:59:59';

        // Consulta base usando rango de fechas
        $sql = "SELECT * FROM caja_movimientos 
                WHERE fecha >= :inicio AND fecha <= :fin";
        
        $params = [':inicio' => $inicioDia, ':fin' => $finDia];

        // Lógica de filtrado basada en DINERO
        if ($tipoFiltro === 'INGRESO') {
            $sql .= " AND ingreso > 0";
        } 
        elseif ($tipoFiltro === 'GASTO') {
            $sql .= " AND egreso > 0";
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
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

    // ---------------------------------------------------------
    // 2. GUARDAR MOVIMIENTO (Manual)
    // ---------------------------------------------------------
    if ($action === 'guardar') {
        $tipo = $_POST['tipo']; // 'INGRESO' o 'GASTO'
        $categoria = $_POST['categoria'];
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        
        if (empty($descripcion) || $monto <= 0) {
            throw new Exception("Datos incompletos");
        }

        // Subida de imagen
        $nombreFoto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            
            if (!in_array($ext, $permitidos)) {
                throw new Exception("Formato no permitido. Use JPG, PNG o PDF.");
            }

            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $nombreFoto = 'evidencia_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $nombreFoto)) {
                throw new Exception("Error al guardar la imagen.");
            }
        }

        // Preparar datos para DB
        $idTx = substr($tipo, 0, 3) . date('ymdHi') . rand(10,99);
        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'INGRESO') ? 0 : $monto;
        $usuario = $_SESSION['nombre'];
        $fecha = date('Y-m-d H:i:s');

        // Insertar
        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria, foto) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idTx, $tipo, $descripcion, $monto, $ingreso, $egreso, $usuario, $fecha, $categoria, $nombreFoto]);

        echo json_encode(['success' => true]);
        exit();
    }

    // ---------------------------------------------------------
    // 3. ELIMINAR MOVIMIENTO
    // ---------------------------------------------------------
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        
        $stmtInfo = $conn->prepare("SELECT foto FROM caja_movimientos WHERE id = ?");
        $stmtInfo->execute([$id]);
        $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        $stmtDel = $conn->prepare("DELETE FROM caja_movimientos WHERE id = ?");
        $stmtDel->execute([$id]);

        if ($row && !empty($row['foto'])) {
            $rutaArchivo = '../uploads/' . $row['foto'];
            if (file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }
        }

        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>