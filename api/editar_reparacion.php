<?php
/*
 * API EDITAR REPARACIÓN
 * Versión Final: Fotos + Historial + Ubicación + FECHA ENTREGA + LIBERAR ESPACIO
 */
ini_set('display_errors', 0);
error_reporting(0);

session_start();
if (!isset($_SESSION['nombre'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

// --- HELPER 1: Subir Evidencia ---
function subirEvidencia() {
    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $directorio = "../uploads/evidencias/";
        if (!is_dir($directorio)) mkdir($directorio, 0777, true);

        $ext = pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = "evidencia_" . time() . "_" . uniqid() . "." . $ext;
        $ruta_final = $directorio . $nombre_archivo;

        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $ruta_final)) {
            return "/local3M/uploads/evidencias/" . $nombre_archivo; 
        }
    }
    return null;
}

// --- HELPER 2: Registrar Historial ---
function registrarHistorial($conn, $id_reparacion, $estado, $comentario, $usuario, $url_evidencia = null) {
    try {
        $sql = "INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable, url_evidencia, fecha_cambio) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id_reparacion, $estado, $comentario, $usuario, $url_evidencia]);
    } catch (Exception $e) { }
}

$data = $_POST;
$action = $data['action'] ?? null;
$id = $data['id'] ?? null;

if (!$id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit();
}

$sql_caja = "INSERT INTO caja_movimientos (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha) VALUES (?, 'REPARACION', ?, ?, 1, ?, ?, 0, ?, ?, NOW())";
$stmt_caja = $conn->prepare($sql_caja);

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $reparacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reparacion) throw new Exception("Reparación no encontrada.");

    // --- ACCIÓN: ENTREGAR (Botón Verde) ---
    if ($action === 'entregar') {
        $monto_total = (float)$reparacion['monto'];
        $adelanto_actual = (float)$reparacion['adelanto'];
        $pago_restante = $monto_total - $adelanto_actual;

        if ($pago_restante > 0) {
            $stmt_caja->execute([
                $reparacion['id_transaccion'], $reparacion['id'],
                "Pago Final: " . $reparacion['tipo_reparacion'] . " " . $reparacion['modelo'],
                $pago_restante, $pago_restante,
                $_SESSION['nombre'], $reparacion['nombre_cliente']
            ]);
        }

        // AQUÍ AGREGAMOS 'ubicacion = NULL' PARA LIBERAR EL LUGAR
        $conn->prepare("UPDATE reparaciones SET adelanto=monto, deuda=0, estado='Entregado', fecha_entrega=NOW(), ubicacion=NULL WHERE id=:id")->execute([':id' => $id]);
        
        registrarHistorial($conn, $id, 'Entregado', 'Equipo entregado y lugar liberado', $_SESSION['nombre']);

        $conn->commit();
        $ticketUrl = 'generar_ticket_id.php?id_transaccion=' . urlencode($reparacion['id_transaccion']);
        echo json_encode(['success' => true, 'ticketUrl' => $ticketUrl]);
        exit();
    }

    // --- ACCIÓN: GUARDAR CAMBIOS (Botón Azul) ---
    if ($action === 'guardar') {
        $url_foto = subirEvidencia();

        // Datos nuevos
        $estado_nuevo = $data['estado'];
        
        // LÓGICA DE UBICACIÓN: Si se entrega, se borra la ubicación. Si no, se actualiza.
        $ubicacion_nueva = $data['ubicacion'];
        if ($estado_nuevo === 'Entregado') {
            $ubicacion_nueva = null; // Liberar si se marca como entregado
        }

        $fecha_estimada_nueva = !empty($data['fecha_estimada']) ? $data['fecha_estimada'] : null;
        $monto = (float)$data['monto'];
        $adelanto = (float)$data['adelanto'];
        
        // Caja
        $adelanto_previo = (float)$reparacion['adelanto'];
        $nuevo_pago = $adelanto - $adelanto_previo;
        
        if ($nuevo_pago > 0) {
            $stmt_caja->execute([
                $reparacion['id_transaccion'], $reparacion['id'],
                "Abono Extra: " . $data['tipo_reparacion'] . " " . $data['modelo'],
                $nuevo_pago, $nuevo_pago,
                $_SESSION['nombre'], $data['nombre_cliente']
            ]);
        }
        $deuda = max(0, $monto - $adelanto);

        // Update SQL
        $sql_fecha_ent = "";
        if ($estado_nuevo === 'Entregado' && $reparacion['estado'] !== 'Entregado') {
            $sql_fecha_ent = ", fecha_entrega = NOW()";
        }

        $sql_up = "UPDATE reparaciones SET 
                    nombre_cliente=:n, telefono=:t, tipo_reparacion=:tr, 
                    marca_celular=:ma, modelo=:mo, monto=:m, adelanto=:a, 
                    deuda=:d, info_extra=:i, estado=:e, ubicacion=:u, 
                    fecha_estimada=:fe 
                    $sql_fecha_ent 
                   WHERE id=:id";
        
        $conn->prepare($sql_up)->execute([
            ':n'=>$data['nombre_cliente'], ':t'=>$data['telefono'], ':tr'=>$data['tipo_reparacion'],
            ':ma'=>$data['marca_celular'], ':mo'=>$data['modelo'], ':m'=>$monto, 
            ':a'=>$adelanto, ':d'=>$deuda, ':i'=>$data['info_extra'], 
            ':e'=>$estado_nuevo, 
            ':u'=>$ubicacion_nueva, // Aquí pasamos el NULL o la ubicación
            ':fe'=>$fecha_estimada_nueva,
            ':id'=>$id
        ]);

        // Historial Inteligente
        $comentarios = [];
        
        if ($reparacion['estado'] !== $estado_nuevo) {
            $comentarios[] = "Estado: " . $reparacion['estado'] . " -> " . $estado_nuevo;
        }
        // Solo registramos cambio de ubicación si NO se entregó (porque al entregar siempre se borra)
        if ($estado_nuevo !== 'Entregado' && ($reparacion['ubicacion']??'') !== $ubicacion_nueva) {
            $comentarios[] = "Ubicación: " . ($reparacion['ubicacion']??'N/A') . " -> " . $ubicacion_nueva;
        }
        
        // Detectar reprogramación
        $fe_ant = $reparacion['fecha_estimada'] ? date('Y-m-d H:i', strtotime($reparacion['fecha_estimada'])) : '';
        $fe_nue = $fecha_estimada_nueva ? date('Y-m-d H:i', strtotime($fecha_estimada_nueva)) : '';
        if ($fe_ant !== $fe_nue) {
            $comentarios[] = "Fecha entrega reprogramada";
        }

        if ($nuevo_pago > 0) $comentarios[] = "Abono de $" . number_format($nuevo_pago, 2);
        if ($url_foto) $comentarios[] = "Evidencia adjuntada";
        
        $texto_historial = empty($comentarios) ? "Actualización de datos" : implode(". ", $comentarios);

        registrarHistorial($conn, $id, $estado_nuevo, $texto_historial, $_SESSION['nombre'], $url_foto);

        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>