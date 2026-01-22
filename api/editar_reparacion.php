<?php
/*
 * API para procesar la edición y entrega de reparaciones
 * VERSIÓN FINAL: SOPORTE PARA FOTOS/EVIDENCIAS E HISTORIAL
 */

// 1. Configuración de errores
ini_set('display_errors', 0);
error_reporting(0);

session_start();
if (!isset($_SESSION['nombre'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/conexion.php'; 

// --- HELPER 1: Subir Evidencia (Foto) ---
function subirEvidencia() {
    // Verificamos si se envió un archivo llamado 'evidencia' sin errores
    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        
        // Definir carpeta de destino (crearla si no existe)
        $directorio = "../uploads/evidencias/";
        if (!is_dir($directorio)) {
            mkdir($directorio, 0777, true);
        }

        // Generar nombre único para evitar sobrescribir
        $ext = pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = "evidencia_" . time() . "_" . uniqid() . "." . $ext;
        $ruta_final = $directorio . $nombre_archivo;

        // Mover el archivo
        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $ruta_final)) {
            // Retornamos la ruta pública para guardar en BD
            // Ajusta "/local3M/uploads..." si tu carpeta base es distinta
            return "/local3M/uploads/evidencias/" . $nombre_archivo; 
        }
    }
    return null; // Si no hay foto o falló
}
// ----------------------------------------

// --- HELPER 2: Registrar en Historial ---
function registrarHistorial($conn, $id_reparacion, $estado, $comentario, $usuario, $url_evidencia = null) {
    try {
        $sql = "INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable, url_evidencia, fecha_cambio) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id_reparacion, $estado, $comentario, $usuario, $url_evidencia]);
    } catch (Exception $e) {
        // Ignoramos error de historial para no bloquear el proceso principal
    }
}
// ----------------------------------------

// 2. Procesar Datos (Soporta JSON y FormData)
$data = [];
if (!empty($_POST)) {
    // Si viene por FormData (con o sin archivos)
    $data = $_POST;
} else {
    // Si viene por JSON raw (sin archivos)
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) $data = $input;
}

$action = $data['action'] ?? null;
$id = $data['id'] ?? null;

if (!$id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios (ID o Acción)']);
    exit();
}

// Preparar SQL de Caja
$sql_caja = "INSERT INTO caja_movimientos (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha) VALUES (?, 'REPARACION', ?, ?, 1, ?, ?, 0, ?, ?, NOW())";
$stmt_caja = $conn->prepare($sql_caja);

try {
    $conn->beginTransaction();

    // Obtener datos actuales
    $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $reparacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reparacion) throw new Exception("Reparación no encontrada.");

    // ===========================
    // ACCIÓN: ENTREGAR
    // ===========================
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

        $conn->prepare("UPDATE reparaciones SET adelanto=monto, deuda=0, estado='Entregado', fecha_entrega=NOW() WHERE id=:id")->execute([':id' => $id]);

        registrarHistorial($conn, $id, 'Entregado', 'Equipo entregado (Finalizado)', $_SESSION['nombre']);

        $conn->commit();
        $ticketUrl = 'generar_ticket_id.php?id_transaccion=' . urlencode($reparacion['id_transaccion']);
        echo json_encode(['success' => true, 'ticketUrl' => $ticketUrl]);
        exit();
    }

    // ===========================
    // ACCIÓN: GUARDAR CAMBIOS (CON FOTO)
    // ===========================
    if ($action === 'guardar') {
        // 1. Intentar subir la foto
        $url_foto_nueva = subirEvidencia();

        // 2. Recoger datos
        $nombre = $data['nombre_cliente'];
        $tel    = $data['telefono'];
        $tipo   = $data['tipo_reparacion'];
        $marca  = $data['marca_celular'];
        $modelo = $data['modelo'];
        $monto  = (float)$data['monto'];
        $adelanto = (float)$data['adelanto'];
        $info   = $data['info_extra'];
        $estado = $data['estado'];

        // 3. Cálculos de dinero
        $adelanto_previo = (float)$reparacion['adelanto'];
        $nuevo_pago = $adelanto - $adelanto_previo;
        
        if ($nuevo_pago > 0) {
            $stmt_caja->execute([
                $reparacion['id_transaccion'], $reparacion['id'],
                "Abono Extra: " . $tipo . " " . $modelo,
                $nuevo_pago, $nuevo_pago,
                $_SESSION['nombre'], $nombre
            ]);
        }
        $deuda = max(0, $monto - $adelanto);

        // 4. Actualizar BD
        $sql_fecha = "";
        if ($estado === 'Entregado' && $reparacion['estado'] !== 'Entregado') {
            $sql_fecha = ", fecha_entrega = NOW()";
        }

        $sql_up = "UPDATE reparaciones SET 
                    nombre_cliente=:n, telefono=:t, tipo_reparacion=:tr, 
                    marca_celular=:ma, modelo=:mo, monto=:m, adelanto=:a, 
                    deuda=:d, info_extra=:i, estado=:e $sql_fecha 
                   WHERE id=:id";
        
        $conn->prepare($sql_up)->execute([
            ':n'=>$nombre, ':t'=>$tel, ':tr'=>$tipo, ':ma'=>$marca, ':mo'=>$modelo,
            ':m'=>$monto, ':a'=>$adelanto, ':d'=>$deuda, ':i'=>$info, ':e'=>$estado, ':id'=>$id
        ]);

        // 5. Historial Inteligente
        $comentario = "Actualización de datos";
        if ($reparacion['estado'] !== $estado) {
            $comentario = "Cambio estado: " . $reparacion['estado'] . " -> " . $estado;
        } elseif ($nuevo_pago > 0) {
            $comentario = "Se abonaron $" . $nuevo_pago;
        }

        if ($url_foto_nueva) {
            $comentario .= " (Se adjuntó evidencia)";
        }

        registrarHistorial($conn, $id, $estado, $comentario, $_SESSION['nombre'], $url_foto_nueva);

        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>