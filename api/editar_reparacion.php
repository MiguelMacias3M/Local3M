<?php
/*
 * API EDITAR REPARACIÓN
 * Versión Final: Sin duplicados + Sin signos ???? + Bitácora + Historial + Liberar Espacio
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['nombre'])) {
    ob_clean();
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';
// Aseguramos soporte completo de caracteres en la conexión actual
try { $conn->exec("SET NAMES utf8mb4"); } catch (Exception $e) {}

function responderJSON($array) {
    if (ob_get_length()) ob_clean();
    echo json_encode($array);
    exit();
}

// --- HELPER 1: Guardar archivo físico ---
function guardarArchivoEvidencia($tmp_name, $orig_name) {
    if (empty($tmp_name) || !is_uploaded_file($tmp_name)) return null;

    $directorio = "../uploads/evidencias/";
    if (!is_dir($directorio)) {
        @mkdir($directorio, 0777, true);
    }

    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
        $ext = 'jpg';
    }
    $nombre_archivo = "evidencia_" . time() . "_" . uniqid() . "." . $ext;
    $ruta_final = $directorio . $nombre_archivo;

    if (@move_uploaded_file($tmp_name, $ruta_final)) {
        $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
        $prefijo = (strpos($script_path, '/local3M/') !== false) ? '/local3M' : '';
        return $prefijo . "/uploads/evidencias/" . $nombre_archivo; 
    }
    return null;
}

// Procesa las fotos sin duplicar nunca
function procesarSubidaEvidencias() {
    $urls = [];

    if (isset($_FILES['evidencias'])) {
        $files = $_FILES['evidencias'];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $u = guardarArchivoEvidencia($files['tmp_name'][$i], $files['name'][$i]);
                    if ($u) $urls[] = $u;
                }
            }
        } elseif ($files['error'] === UPLOAD_ERR_OK) {
            $u = guardarArchivoEvidencia($files['tmp_name'], $files['name']);
            if ($u) $urls[] = $u;
        }
    } elseif (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        // Solo entra aquí si NO se envió 'evidencias'
        $u = guardarArchivoEvidencia($_FILES['evidencia']['tmp_name'], $_FILES['evidencia']['name']);
        if ($u) $urls[] = $u;
    }

    return $urls;
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

if (!$action) {
    responderJSON(['success' => false, 'message' => 'Falta la acción a realizar']);
}

// --- ACCIÓN EXTRA 1: ELIMINAR FOTO DE EVIDENCIA ---
if ($action === 'eliminar_evidencia') {
    $id_historial = (int)($data['id_historial'] ?? 0);
    if (!$id_historial) {
        responderJSON(['success' => false, 'message' => 'ID de historial inválido']);
    }
    try {
        $stmt_h = $conn->prepare("SELECT * FROM historial_reparaciones WHERE id = :id_h");
        $stmt_h->execute([':id_h' => $id_historial]);
        $reg = $stmt_h->fetch(PDO::FETCH_ASSOC);

        if ($reg && !empty($reg['url_evidencia'])) {
            $nombre_img = basename($reg['url_evidencia']);
            $ruta_fisica = "../uploads/evidencias/" . $nombre_img;
            if (file_exists($ruta_fisica)) {
                @unlink($ruta_fisica);
            }
            if ($reg['comentario'] === 'Evidencia adjuntada' || strpos($reg['comentario'], 'Evidencia fotográfica') !== false) {
                $conn->prepare("DELETE FROM historial_reparaciones WHERE id = :id_h")->execute([':id_h' => $id_historial]);
            } else {
                $conn->prepare("UPDATE historial_reparaciones SET url_evidencia = NULL WHERE id = :id_h")->execute([':id_h' => $id_historial]);
            }
        }
        responderJSON(['success' => true]);
    } catch (Exception $e) {
        responderJSON(['success' => false, 'message' => $e->getMessage()]);
    }
}

if (!$id) {
    responderJSON(['success' => false, 'message' => 'Falta ID de reparación']);
}

$sql_caja = "INSERT INTO caja_movimientos (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha) VALUES (?, 'REPARACION', ?, ?, 1, ?, ?, 0, ?, ?, NOW())";
$stmt_caja = $conn->prepare($sql_caja);

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $reparacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reparacion) throw new Exception("Reparación no encontrada.");

    // --- ACCIÓN EXTRA 2: AGREGAR NOTA O FOTO RÁPIDA A LA BITÁCORA (SIN EMOJIS QUE CAUSEN ????) ---
    if ($action === 'agregar_bitacora') {
        $nota = trim($data['nota_bitacora'] ?? '');
        $urls_fotos = procesarSubidaEvidencias();
        $estado_actual = $reparacion['estado'] ?: 'En Reparacion';

        if (empty($nota) && empty($urls_fotos)) {
            throw new Exception("Escribe una nota o selecciona al menos una foto.");
        }

        if (!empty($urls_fotos)) {
            foreach ($urls_fotos as $index => $url_f) {
                $texto_nota = ($index === 0) 
                    ? ($nota !== '' ? "Bitácora: " . $nota : "Evidencia fotográfica adjuntada") 
                    : ($nota !== '' ? "Bitácora: " . $nota . " (Foto " . ($index + 1) . ")" : "Evidencia fotográfica (" . ($index + 1) . ")");
                
                registrarHistorial($conn, $id, $estado_actual, $texto_nota, $_SESSION['nombre'], $url_f);
            }
        } else {
            registrarHistorial($conn, $id, $estado_actual, "Bitácora: " . $nota, $_SESSION['nombre'], null);
        }

        $conn->commit();
        responderJSON(['success' => true]);
    }

    // --- ACCIÓN: ENTREGAR ---
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

        $conn->prepare("UPDATE reparaciones SET adelanto=monto, deuda=0, estado='Entregado', fecha_entrega=NOW(), ubicacion=NULL WHERE id=:id")->execute([':id' => $id]);
        
        registrarHistorial($conn, $id, 'Entregado', 'Equipo entregado y lugar liberado', $_SESSION['nombre']);

        $conn->commit();
        $ticketUrl = 'generar_ticket_id.php?id_transaccion=' . urlencode($reparacion['id_transaccion']);
        responderJSON(['success' => true, 'ticketUrl' => $ticketUrl]);
    }

    // --- ACCIÓN: GUARDAR CAMBIOS ---
    if ($action === 'guardar') {
        $estado_nuevo = $data['estado'] ?? $reparacion['estado'];
        $ubicacion_nueva = $data['ubicacion'] ?? $reparacion['ubicacion'];
        if ($estado_nuevo === 'Entregado') {
            $ubicacion_nueva = null;
        }

        $fecha_estimada_nueva = !empty($data['fecha_estimada']) ? $data['fecha_estimada'] : null;
        $monto = (float)($data['monto'] ?? $reparacion['monto']);
        $adelanto = (float)($data['adelanto'] ?? $reparacion['adelanto']);
        
        $adelanto_previo = (float)$reparacion['adelanto'];
        $nuevo_pago = $adelanto - $adelanto_previo;
        
        if ($nuevo_pago > 0) {
            $stmt_caja->execute([
                $reparacion['id_transaccion'], $reparacion['id'],
                "Abono Extra: " . ($data['tipo_reparacion'] ?? '') . " " . ($data['modelo'] ?? ''),
                $nuevo_pago, $nuevo_pago,
                $_SESSION['nombre'], ($data['nombre_cliente'] ?? '')
            ]);
        }
        $deuda = max(0, $monto - $adelanto);

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
            ':n'  => $data['nombre_cliente'] ?? $reparacion['nombre_cliente'],
            ':t'  => $data['telefono'] ?? $reparacion['telefono'],
            ':tr' => $data['tipo_reparacion'] ?? $reparacion['tipo_reparacion'],
            ':ma' => $data['marca_celular'] ?? $reparacion['marca_celular'],
            ':mo' => $data['modelo'] ?? $reparacion['modelo'],
            ':m'  => $monto, 
            ':a'  => $adelanto,
            ':d'  => $deuda,
            ':i'  => $data['info_extra'] ?? $reparacion['info_extra'], 
            ':e'  => $estado_nuevo, 
            ':u'  => $ubicacion_nueva,
            ':fe' => $fecha_estimada_nueva,
            ':id' => $id
        ]);

        // Historial Inteligente
        $comentarios = [];
        
        if ($reparacion['estado'] !== $estado_nuevo) {
            $comentarios[] = "Estado: " . $reparacion['estado'] . " -> " . $estado_nuevo;
        }
        if ($estado_nuevo !== 'Entregado' && ($reparacion['ubicacion'] ?? '') !== $ubicacion_nueva) {
            $comentarios[] = "Ubicación: " . ($reparacion['ubicacion'] ?? 'N/A') . " -> " . $ubicacion_nueva;
        }
        
        $fe_ant = $reparacion['fecha_estimada'] ? date('Y-m-d H:i', strtotime($reparacion['fecha_estimada'])) : '';
        $fe_nue = $fecha_estimada_nueva ? date('Y-m-d H:i', strtotime($fecha_estimada_nueva)) : '';
        if ($fe_ant !== $fe_nue) {
            $comentarios[] = "Fecha entrega reprogramada";
        }

        if ($nuevo_pago > 0) $comentarios[] = "Abono de $" . number_format($nuevo_pago, 2);
        
        $texto_historial = empty($comentarios) ? "Actualización de datos" : implode(". ", $comentarios);

        registrarHistorial($conn, $id, $estado_nuevo, $texto_historial, $_SESSION['nombre'], null);

        $conn->commit();
        responderJSON(['success' => true]);
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    responderJSON(['success' => false, 'message' => $e->getMessage()]);
}
?>