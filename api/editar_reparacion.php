<?php
/*
 * API para procesar la edición y entrega de reparaciones
 * ACTUALIZADO: INCLUYE REGISTRO EN HISTORIAL
 */

// Desactivar visualización de errores para no romper JSON
ini_set('display_errors', 0);
error_reporting(0);

session_start();
if (!isset($_SESSION['nombre'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/conexion.php'; // Ruta correcta a tu conexión

// --- FUNCIÓN HELPER: REGISTRAR HISTORIAL ---
function registrarHistorial($conn, $id_reparacion, $estado, $comentario, $usuario) {
    try {
        $stmt = $conn->prepare("INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable, fecha_cambio) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$id_reparacion, $estado, $comentario, $usuario]);
    } catch (Exception $e) {
        // Silenciamos error del historial para no detener el proceso principal
        // pero podrías loguearlo si quisieras.
    }
}
// -------------------------------------------

// Leer datos JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Si viene por $_POST normal (FormData), lo usamos
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

$action = $data['action'] ?? null;
$id = $data['id'] ?? null;

if (!$id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios (ID o Acción)']);
    exit();
}

// --- Preparar SQL para movimientos de caja ---
$sql_caja = "INSERT INTO caja_movimientos (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha) VALUES (?, 'REPARACION', ?, ?, 1, ?, ?, 0, ?, ?, NOW())";
$stmt_caja = $conn->prepare($sql_caja);

try {
    $conn->beginTransaction();

    // 1. Obtener datos actuales de la reparación
    $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $reparacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reparacion) {
        throw new Exception("Reparación no encontrada.");
    }

    // --- ACCIÓN: ENTREGAR ---
    if ($action === 'entregar') {
        $monto_total = (float)$reparacion['monto'];
        $adelanto_actual = (float)$reparacion['adelanto'];
        $pago_restante = $monto_total - $adelanto_actual;

        // Si hay deuda, registrar el pago en caja
        if ($pago_restante > 0) {
            $stmt_caja->execute([
                $reparacion['id_transaccion'],
                $reparacion['id'],
                "Pago Final: " . $reparacion['tipo_reparacion'] . " " . $reparacion['modelo'],
                $pago_restante,
                $pago_restante,
                $_SESSION['nombre'],
                $reparacion['nombre_cliente']
            ]);
        }

        // Actualizar reparación
        $stmtUpdate = $conn->prepare("UPDATE reparaciones SET adelanto = monto, deuda = 0, estado = 'Entregado', fecha_entrega = NOW() WHERE id = :id");
        $stmtUpdate->execute([':id' => $id]);

        // --- NUEVO: REGISTRAR EN HISTORIAL ---
        registrarHistorial($conn, $id, 'Entregado', 'Equipo entregado al cliente (Proceso finalizado)', $_SESSION['nombre']);
        // -------------------------------------

        $conn->commit();

        // Generar URL del ticket
        $ticketUrl = 'generar_ticket_id.php?id_transaccion=' . urlencode($reparacion['id_transaccion']);
        echo json_encode(['success' => true, 'ticketUrl' => $ticketUrl]);
        exit();
    }

    // --- ACCIÓN: GUARDAR CAMBIOS ---
    if ($action === 'guardar') {
        // Recoger datos del formulario
        $nombre_cliente = $data['nombre_cliente'];
        $telefono       = $data['telefono'];
        $tipo_rep       = $data['tipo_reparacion'];
        $marca          = $data['marca_celular'];
        $modelo         = $data['modelo'];
        $monto_form     = (float)$data['monto'];
        $adelanto_form  = (float)$data['adelanto'];
        $info_extra     = $data['info_extra'];
        $estado_form    = $data['estado'];

        // Calcular nuevo pago (si aumentaron el adelanto)
        $adelanto_actual_db = (float)$reparacion['adelanto'];
        $nuevo_pago = $adelanto_form - $adelanto_actual_db;

        if ($nuevo_pago > 0) {
            $stmt_caja->execute([
                $reparacion['id_transaccion'],
                $reparacion['id'],
                "Abono Extra: " . $tipo_rep . " " . $modelo,
                $nuevo_pago,
                $nuevo_pago,
                $_SESSION['nombre'],
                $nombre_cliente
            ]);
        }

        // Calcular deuda
        $deuda = max(0, $monto_form - $adelanto_form);

        // Lógica de fecha de entrega
        $sql_fecha = "";
        if ($estado_form === 'Entregado' && $reparacion['estado'] !== 'Entregado') {
            $sql_fecha = ", fecha_entrega = NOW()";
        }

        $sql_update = "UPDATE reparaciones SET 
                        nombre_cliente = :nombre, telefono = :tel, 
                        tipo_reparacion = :tipo, marca_celular = :marca, modelo = :modelo,
                        monto = :monto, adelanto = :adelanto, deuda = :deuda,
                        info_extra = :info, estado = :estado
                        $sql_fecha
                       WHERE id = :id";
        
        $stmtUpdate = $conn->prepare($sql_update);
        $stmtUpdate->execute([
            ':nombre' => $nombre_cliente,
            ':tel' => $telefono,
            ':tipo' => $tipo_rep,
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':monto' => $monto_form,
            ':adelanto' => $adelanto_form,
            ':deuda' => $deuda,
            ':info' => $info_extra,
            ':estado' => $estado_form,
            ':id' => $id
        ]);

        // --- NUEVO: REGISTRAR EN HISTORIAL ---
        $comentario_historial = "Actualización de información";
        // Si cambió el estado, lo detallamos
        if ($reparacion['estado'] !== $estado_form) {
            $comentario_historial = "Cambio de estado: " . $reparacion['estado'] . " -> " . $estado_form;
        } 
        // Si hubo abono, lo detallamos
        elseif ($nuevo_pago > 0) {
            $comentario_historial = "Se registró un abono de $" . number_format($nuevo_pago, 2);
        }

        registrarHistorial($conn, $id, $estado_form, $comentario_historial, $_SESSION['nombre']);
        // -------------------------------------

        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>