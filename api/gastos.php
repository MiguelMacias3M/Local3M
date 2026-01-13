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

// Incluir conexión (Aquí debe estar definida $MASTER_PASSWORD)
if (!file_exists('../config/conexion.php')) {
    echo json_encode(['success' => false, 'error' => 'Falta archivo de configuración']);
    exit();
}
include '../config/conexion.php';

if (isset($conn)) { try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {} }

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    // ---------------------------------------------------------
    // 1. LISTAR MOVIMIENTOS
    // ---------------------------------------------------------
    if ($action === 'listar') {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $tipoFiltro = $_GET['tipo'] ?? ''; 

        $inicioDia = $fecha . ' 00:00:00';
        $finDia = $fecha . ' 23:59:59';

        $sql = "SELECT * FROM caja_movimientos 
                WHERE fecha >= :inicio AND fecha <= :fin";
        
        $params = [':inicio' => $inicioDia, ':fin' => $finDia];

        if ($tipoFiltro === 'INGRESO') {
            $sql .= " AND ingreso > 0";
        } 
        elseif ($tipoFiltro === 'GASTO') {
            $sql .= " AND egreso > 0";
        }

        $sql .= " ORDER BY fecha DESC, id DESC";

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
    // 2. GUARDAR MOVIMIENTO
    // ---------------------------------------------------------
    if ($action === 'guardar') {
        $tipo = $_POST['tipo']; 
        $categoria = $_POST['categoria'];
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        
        if (empty($descripcion) || $monto <= 0) {
            throw new Exception("Datos incompletos");
        }

        $nombreFoto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            
            if (!in_array($ext, $permitidos)) throw new Exception("Formato no permitido.");

            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $nombreFoto = 'evidencia_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $nombreFoto)) {
                throw new Exception("Error al guardar la imagen.");
            }
        }

        $idTx = substr($tipo, 0, 3) . date('ymdHi') . rand(10,99);
        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'INGRESO') ? 0 : $monto;
        $usuario = $_SESSION['nombre'];
        $fecha = date('Y-m-d H:i:s');

        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria, foto) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idTx, $tipo, $descripcion, $monto, $ingreso, $egreso, $usuario, $fecha, $categoria, $nombreFoto]);

        echo json_encode(['success' => true]);
        exit();
    }

    // ---------------------------------------------------------
    // 3. ELIMINAR MOVIMIENTO (CON LLAVE MAESTRA)
    // ---------------------------------------------------------
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        $llaveEnviada = $_POST['llave_maestra'] ?? '';

        // --- VALIDACIÓN DE SEGURIDAD ---
        // Usamos la variable $MASTER_PASSWORD que viene de config/conexion.php
        if (!isset($MASTER_PASSWORD)) {
            // Seguridad: Si no está definida en config, bloqueamos la acción para evitar borrados sin control
            throw new Exception("Error de servidor: Llave Maestra no configurada.");
        }

        if ($llaveEnviada !== $MASTER_PASSWORD) {
            throw new Exception("Llave maestra incorrecta");
        }
        
        // Proceder con la eliminación
        $stmtInfo = $conn->prepare("SELECT foto FROM caja_movimientos WHERE id = ?");
        $stmtInfo->execute([$id]);
        $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        $stmtDel = $conn->prepare("DELETE FROM caja_movimientos WHERE id = ?");
        $stmtDel->execute([$id]);

        if ($row && !empty($row['foto'])) {
            $rutaArchivo = '../uploads/' . $row['foto'];
            if (file_exists($rutaArchivo)) unlink($rutaArchivo);
        }

        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>