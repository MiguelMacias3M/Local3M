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

// Incluir conexión y MASTER_PASSWORD
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

        $sql = "SELECT * FROM caja_movimientos WHERE fecha >= :inicio AND fecha <= :fin";
        $params = [':inicio' => $inicioDia, ':fin' => $finDia];

        if ($tipoFiltro === 'INGRESO') $sql .= " AND ingreso > 0";
        elseif ($tipoFiltro === 'GASTO') $sql .= " AND egreso > 0";

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
    // 2. OBTENER UN MOVIMIENTO (PROTEGIDO)
    // ---------------------------------------------------------
    if ($action === 'obtener') {
        $id = $_POST['id'];
        $llaveEnviada = $_POST['llave_maestra'] ?? '';

        if (!isset($MASTER_PASSWORD)) throw new Exception("Error de configuración: Llave Maestra no definida.");
        if ($llaveEnviada !== $MASTER_PASSWORD) throw new Exception("Llave maestra incorrecta");

        $stmt = $conn->prepare("SELECT * FROM caja_movimientos WHERE id = ?");
        $stmt->execute([$id]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) throw new Exception("Registro no encontrado");

        // Normalizamos datos para el frontend
        // Determinamos el monto único para mostrar en el input
        $mov['monto_real'] = ($mov['ingreso'] > 0) ? $mov['ingreso'] : $mov['egreso'];
        // Si hay foto, mandamos la URL completa
        $mov['foto_url'] = !empty($mov['foto']) ? 'uploads/' . $mov['foto'] : null;

        echo json_encode(['success' => true, 'data' => $mov]);
        exit();
    }

    // ---------------------------------------------------------
    // 3. GUARDAR (CREAR O EDITAR)
    // ---------------------------------------------------------
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? ''; // Si viene ID, es edición
        $tipo = $_POST['tipo']; 
        $categoria = $_POST['categoria'];
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        
        if (empty($descripcion) || $monto <= 0) throw new Exception("Datos incompletos");

        // --- MANEJO DE IMAGEN ---
        $nombreFoto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            
            if (!in_array($ext, $permitidos)) throw new Exception("Formato de imagen no permitido.");

            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $nombreFoto = 'evidencia_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $nombreFoto)) {
                throw new Exception("Error al subir la imagen.");
            }
        }

        // Preparar valores financieros
        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'INGRESO') ? 0 : $monto;

        if (!empty($id)) {
            // === MODO EDICIÓN ===
            // Validar llave maestra nuevamente por seguridad (opcional pero recomendado)
            // $llaveEnviada = $_POST['llave_maestra'] ?? '';
            // if ($llaveEnviada !== $MASTER_PASSWORD) throw new Exception("Sesión de edición expirada o llave incorrecta.");

            // Si subió foto nueva, borramos la anterior si existe
            if ($nombreFoto) {
                $stmtFoto = $conn->prepare("SELECT foto FROM caja_movimientos WHERE id = ?");
                $stmtFoto->execute([$id]);
                $old = $stmtFoto->fetch(PDO::FETCH_ASSOC);
                if ($old && !empty($old['foto']) && file_exists('../uploads/'.$old['foto'])) {
                    unlink('../uploads/'.$old['foto']);
                }
            }

            // Construir SQL dinámico (si no hay foto nueva, no tocamos la columna foto)
            $sql = "UPDATE caja_movimientos SET 
                    tipo=?, descripcion=?, monto_unitario=?, ingreso=?, egreso=?, categoria=?";
            $params = [$tipo, $descripcion, $monto, $ingreso, $egreso, $categoria];

            if ($nombreFoto) {
                $sql .= ", foto=?";
                $params[] = $nombreFoto;
            }

            $sql .= " WHERE id=?";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

        } else {
            // === MODO CREACIÓN ===
            $idTx = substr($tipo, 0, 3) . date('ymdHi') . rand(10,99);
            $usuario = $_SESSION['nombre'];
            $fecha = date('Y-m-d H:i:s');

            $sql = "INSERT INTO caja_movimientos 
                    (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria, foto) 
                    VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$idTx, $tipo, $descripcion, $monto, $ingreso, $egreso, $usuario, $fecha, $categoria, $nombreFoto]);
        }

        echo json_encode(['success' => true]);
        exit();
    }

    // ---------------------------------------------------------
    // 4. ELIMINAR MOVIMIENTO
    // ---------------------------------------------------------
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        $llaveEnviada = $_POST['llave_maestra'] ?? '';

        if (!isset($MASTER_PASSWORD)) throw new Exception("Error de servidor: Llave Maestra no configurada.");
        if ($llaveEnviada !== $MASTER_PASSWORD) throw new Exception("Llave maestra incorrecta");
        
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