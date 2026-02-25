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

if (!file_exists('../config/conexion.php')) {
    echo json_encode(['success' => false, 'error' => 'Falta archivo de configuración']);
    exit();
}
include '../config/conexion.php';

if (isset($conn)) {
    try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {}
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    // ==========================================
    // 1. LISTAR (VISOR GLOBAL: MUESTRA TODO SIN FILTRO DE ORIGEN)
    // ==========================================
    if ($action === 'listar') {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $tipoFiltro = $_GET['tipo'] ?? ''; 

        $inicioDia = $fecha . ' 00:00:00';
        $finDia = $fecha . ' 23:59:59';

        // AQUÍ SE QUITÓ EL FILTRO: Ahora consulta toda la tabla sin importar si es CAJA o GASTOS
        $sql = "SELECT * FROM caja_movimientos WHERE fecha >= :inicio AND fecha <= :fin";
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

        foreach($data as &$d) {
            // Le decimos al frontend de dónde viene para que ponga la etiqueta correcta
            $origen = $d['origen'] ?? 'CAJA';
            $d['es_caja'] = ($origen === 'CAJA'); 
            $tipo = strtoupper($d['tipo']);
            $d['es_retiro_cierre'] = ($tipo === 'RETIRO' || $tipo === 'CIERRE');
        }

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

    // ==========================================
    // 2. OBTENER PARA EDITAR 
    // ==========================================
    if ($action === 'obtener') {
        $id = $_POST['id'];
        $llave = $_POST['llave_maestra'] ?? '';

        if (!isset($MASTER_PASSWORD)) throw new Exception("Error: Llave Maestra no configurada.");
        if ($llave !== $MASTER_PASSWORD) throw new Exception("Llave maestra incorrecta");

        $stmt = $conn->prepare("SELECT * FROM caja_movimientos WHERE id = ?");
        $stmt->execute([$id]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) throw new Exception("Registro no encontrado");
        
        $mov['monto_real'] = ($mov['ingreso'] > 0) ? $mov['ingreso'] : $mov['egreso'];
        $mov['foto_url'] = !empty($mov['foto']) ? 'uploads/' . $mov['foto'] : null;
        
        echo json_encode(['success' => true, 'data' => $mov]);
        exit();
    }

    // ==========================================
    // 3. GUARDAR (MANTIENE LA LÓGICA DE GUARDAR COMO 'GASTOS' SI ES NUEVO)
    // ==========================================
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? '';
        $tipo = $_POST['tipo']; 
        $categoria = $_POST['categoria'];
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        
        if (empty($descripcion) || $monto <= 0) throw new Exception("Datos incompletos");

        $nombreFoto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            
            if (!in_array($ext, $permitidos)) throw new Exception("Formato de archivo no permitido.");

            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $nombreFoto = 'evidencia_' . time() . '_' . rand(100,999) . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $nombreFoto);
        }

        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'INGRESO') ? 0 : $monto;

        if (!empty($id)) {
            if ($nombreFoto) {
                $stmtOld = $conn->prepare("SELECT foto FROM caja_movimientos WHERE id = ?");
                $stmtOld->execute([$id]);
                $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
                if ($old && !empty($old['foto']) && file_exists('../uploads/' . $old['foto'])) {
                    unlink('../uploads/' . $old['foto']);
                }
            }

            $sql = "UPDATE caja_movimientos SET tipo=?, descripcion=?, monto_unitario=?, ingreso=?, egreso=?, categoria=?";
            $params = [$tipo, $descripcion, $monto, $ingreso, $egreso, $categoria];

            if ($nombreFoto) {
                $sql .= ", foto=?";
                $params[] = $nombreFoto;
            }

            $sql .= " WHERE id=?";
            $params[] = $id;

            $conn->prepare($sql)->execute($params);

        } else {
            $idTx = substr($tipo, 0, 3) . date('ymdHi') . rand(10,99);
            $usuario = $_SESSION['nombre'];
            $fecha = date('Y-m-d H:i:s');

            $sql = "INSERT INTO caja_movimientos 
                    (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria, foto, origen) 
                    VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, 'GASTOS')";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$idTx, $tipo, $descripcion, $monto, $ingreso, $egreso, $usuario, $fecha, $categoria, $nombreFoto]);
        }

        echo json_encode(['success' => true]);
        exit();
    }

    // ==========================================
    // 4. ELIMINAR 
    // ==========================================
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        $llave = $_POST['llave_maestra'] ?? '';

        if (!isset($MASTER_PASSWORD)) throw new Exception("Error: Llave Maestra no configurada.");
        if ($llave !== $MASTER_PASSWORD) throw new Exception("Llave maestra incorrecta");
        
        $stmtInfo = $conn->prepare("SELECT foto FROM caja_movimientos WHERE id = ?");
        $stmtInfo->execute([$id]);
        $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        
        $conn->prepare("DELETE FROM caja_movimientos WHERE id = ?")->execute([$id]);
        
        if ($row && !empty($row['foto'])) {
            $ruta = '../uploads/' . $row['foto'];
            if (file_exists($ruta)) unlink($ruta);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>