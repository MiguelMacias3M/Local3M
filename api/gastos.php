<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Verificar sesión
if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// 2. Verificar conexión y configuración
// Asegúrate de que $MASTER_PASSWORD esté definida en conexion.php
if (!file_exists('../config/conexion.php')) {
    echo json_encode(['success' => false, 'error' => 'Falta archivo de configuración']);
    exit();
}
include '../config/conexion.php';

// 3. Forzar UTF-8
if (isset($conn)) {
    try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {}
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    // ==========================================
    // 1. LISTAR (VISOR GLOBAL: CAJA + GASTOS)
    // ==========================================
    if ($action === 'listar') {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $tipoFiltro = $_GET['tipo'] ?? ''; 

        // Rango de fecha completo del día seleccionado
        $inicioDia = $fecha . ' 00:00:00';
        $finDia = $fecha . ' 23:59:59';

        $sql = "SELECT * FROM caja_movimientos WHERE fecha >= :inicio AND fecha <= :fin";
        $params = [':inicio' => $inicioDia, ':fin' => $finDia];

        // Filtros inteligentes por flujo de dinero
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

        // Agregamos bandera para que el frontend sepa el origen visualmente
        foreach($data as &$d) {
            // Si no existe la columna origen en registros viejos, asumimos CAJA
            $origen = $d['origen'] ?? 'CAJA';
            $d['es_caja'] = ($origen === 'CAJA');
            
            // Bandera para el frontend: ¿Es un retiro/cierre (neutro)?
            $tipo = strtoupper($d['tipo']);
            $d['es_retiro_cierre'] = ($tipo === 'RETIRO' || $tipo === 'CIERRE');
        }

        // Respuesta blindada UTF-8
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
    // 2. OBTENER PARA EDITAR (PROTEGIDO)
    // ==========================================
    if ($action === 'obtener') {
        $id = $_POST['id'];
        $llave = $_POST['llave_maestra'] ?? '';

        // Validación de seguridad con la variable de config/conexion.php
        if (!isset($MASTER_PASSWORD)) throw new Exception("Error: Llave Maestra no configurada en el servidor.");
        if ($llave !== $MASTER_PASSWORD) throw new Exception("Llave maestra incorrecta");

        $stmt = $conn->prepare("SELECT * FROM caja_movimientos WHERE id = ?");
        $stmt->execute([$id]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) throw new Exception("Registro no encontrado");
        
        // Preparar datos para el formulario
        $mov['monto_real'] = ($mov['ingreso'] > 0) ? $mov['ingreso'] : $mov['egreso'];
        $mov['foto_url'] = !empty($mov['foto']) ? 'uploads/' . $mov['foto'] : null;
        
        echo json_encode(['success' => true, 'data' => $mov]);
        exit();
    }

    // ==========================================
    // 3. GUARDAR (CREAR = 'GASTOS' / EDITAR = MANTIENE ORIGEN)
    // ==========================================
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? '';
        $tipo = $_POST['tipo']; 
        $categoria = $_POST['categoria'];
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        
        if (empty($descripcion) || $monto <= 0) throw new Exception("Datos incompletos");

        // Subida de imagen
        $nombreFoto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            
            if (!in_array($ext, $permitidos)) throw new Exception("Formato de archivo no permitido.");

            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $nombreFoto = 'evidencia_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $nombreFoto)) {
                throw new Exception("Error al guardar la imagen en el servidor.");
            }
        }

        // Asignar columnas de dinero según el tipo seleccionado
        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'INGRESO') ? 0 : $monto;

        if (!empty($id)) {
            // --- EDICIÓN ---
            // Recuperar foto anterior para borrarla si se sube una nueva
            if ($nombreFoto) {
                $stmtOld = $conn->prepare("SELECT foto FROM caja_movimientos WHERE id = ?");
                $stmtOld->execute([$id]);
                $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
                if ($old && !empty($old['foto']) && file_exists('../uploads/' . $old['foto'])) {
                    unlink('../uploads/' . $old['foto']);
                }
            }

            // Construcción dinámica del UPDATE
            // Nota: NO actualizamos la columna 'origen' para respetar si vino de CAJA o GASTOS
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
            // --- NUEVO REGISTRO ---
            // Forzamos el origen 'GASTOS' para diferenciarlo de la operación de mostrador
            $idTx = substr($tipo, 0, 3) . date('ymdHi') . rand(10,99);
            $usuario = $_SESSION['nombre'];
            $fecha = date('Y-m-d H:i:s');

            // Asegúrate de que tu tabla tenga la columna 'origen' creada
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
    // 4. ELIMINAR (PROTEGIDO)
    // ==========================================
    if ($action === 'eliminar') {
        $id = $_POST['id'];
        $llave = $_POST['llave_maestra'] ?? '';

        // Validación de seguridad
        if (!isset($MASTER_PASSWORD)) throw new Exception("Error: Llave Maestra no configurada.");
        if ($llave !== $MASTER_PASSWORD) throw new Exception("Llave maestra incorrecta");
        
        // Obtener info para borrar foto
        $stmtInfo = $conn->prepare("SELECT foto FROM caja_movimientos WHERE id = ?");
        $stmtInfo->execute([$id]);
        $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        
        // Borrar registro
        $conn->prepare("DELETE FROM caja_movimientos WHERE id = ?")->execute([$id]);
        
        // Borrar archivo físico si existe
        if ($row && !empty($row['foto'])) {
            $ruta = '../uploads/' . $row['foto'];
            if (file_exists($ruta)) unlink($ruta);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500); // Error de servidor
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>