<?php
/*
 * API: REGISTRAR REPARACIONES (VERSIÓN COMPLETA Y FINAL)
 * Incluye: Reparación, Caja, Historial, Ubicación y Fecha Estimada.
 */

// 1. Configuración de errores (Silencioso para JSON)
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

session_start();
include '../config/conexion.php';

// 2. Validar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']); 
    exit;
}

// 3. Leer y Decodificar JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['carrito'])) {
    echo json_encode(['success' => false, 'error' => 'No hay reparaciones en el carrito.']); 
    exit;
}

// 4. Asignar variables
$id_transaccion = $data['id_transaccion'] ?? uniqid('trans_');
$usuario        = $data['usuario'] ?? 'Sistema';
$nombreCliente  = $data['nombreCliente'] ?? '';
$telefono       = $data['telefono'] ?? '';
$info_extra     = $data['infoExtra'] ?? 'Ninguna';
$estado         = 'En espera'; // Estado inicial
$carrito        = $data['carrito'];
$fechaHora      = date('Y-m-d H:i:s'); // Fecha servidor

// 5. Validar datos obligatorios
if (empty($nombreCliente) || empty($telefono) || empty($usuario)) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos del cliente o usuario.']);
    exit;
}

// Función auxiliar para generar código de barras único
function generarCodigoReparacion(): string {
    $fecha = date('ymd');
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Sin I, O, 0, 1 para evitar confusión
    $rand = '';
    for ($i = 0; $i < 5; $i++) {
        $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return "REP{$fecha}{$rand}";
}

try {
    // Iniciar Transacción (Todo o nada)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    // ---------------------------------------------------------
    // A. PREPARAR SENTENCIAS SQL (Se preparan UNA vez, se usan MUCHAS)
    // ---------------------------------------------------------

    // 1. SQL Reparaciones (Incluye 'ubicacion' y 'fecha_estimada')
    // Nota: 'ubicacion' se fija por defecto en 'Recepción'
    $sql_rep = "INSERT INTO reparaciones (
        id_transaccion, usuario, nombre_cliente, telefono, 
        tipo_reparacion, marca_celular, modelo, 
        monto, adelanto, deuda, 
        fecha_hora, info_extra, estado, 
        codigo_barras, ubicacion, fecha_estimada 
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Recepción', ?)";
    
    $stmt_rep = $conn->prepare($sql_rep);

    // 2. SQL Caja (Movimiento de dinero)
    $sql_caja = "INSERT INTO caja_movimientos (
        id_transaccion, tipo, ref_id, descripcion, 
        cantidad, monto_unitario, ingreso, egreso, 
        usuario, cliente, fecha
    ) VALUES (?, 'REPARACION', ?, ?, 1, ?, ?, 0, ?, ?, NOW())";
    
    $stmt_caja = $conn->prepare($sql_caja);

    // 3. SQL Historial (Primer evento: Ingreso)
    $sql_hist = "INSERT INTO historial_reparaciones (
        id_reparacion, estado_nuevo, comentario, usuario_responsable, fecha_cambio
    ) VALUES (?, 'Ingreso', 'Recepción del equipo', ?, NOW())";
    
    $stmt_hist = $conn->prepare($sql_hist);


    // ---------------------------------------------------------
    // B. PROCESAR EL CARRITO
    // ---------------------------------------------------------
    foreach ($carrito as $r) {
        $maxIntentos = 5;
        $exito = false;
        
        // Bucle para intentar generar código único si choca
        for ($i = 0; $i < $maxIntentos; $i++) {
            $codigo_barras = generarCodigoReparacion();
            
            // Validar fecha estimada (puede venir null o vacía)
            $fecha_estimada = !empty($r['fechaEstimada']) ? $r['fechaEstimada'] : null;

            try {
                // --- 1. Insertar Reparación ---
                $stmt_rep->execute([
                    $id_transaccion,
                    $usuario,
                    $nombreCliente,
                    $telefono,
                    $r['tipoReparacion'],
                    $r['marcaCelular'],
                    $r['modelo'],
                    $r['monto'],
                    $r['adelanto'],
                    $r['deuda'],
                    $fechaHora,
                    $info_extra,
                    $estado,        // "En espera"
                    $codigo_barras,
                    $fecha_estimada // Nueva columna
                ]);

                // Obtener ID generado
                $id_reparacion_insertada = $conn->lastInsertId();

                // --- 2. Insertar Historial ---
                $stmt_hist->execute([
                    $id_reparacion_insertada,
                    $usuario
                ]);

                // --- 3. Insertar en Caja (Solo si hay adelanto) ---
                $adelanto = (float)$r['adelanto'];
                if ($adelanto > 0) {
                    $descripcion_caja = "Adelanto: " . $r['tipoReparacion'] . " (" . $r['modelo'] . ")";
                    $stmt_caja->execute([
                        $id_transaccion,
                        $id_reparacion_insertada,
                        $descripcion_caja,
                        $adelanto, // Monto unitario
                        $adelanto, // Ingreso total
                        $usuario,
                        $nombreCliente
                    ]);
                }

                $exito = true;
                break; // Éxito, salir del bucle de intentos

            } catch (PDOException $e) {
                // Si el error es código duplicado (Error 1062), reintentar
                if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062') !== false) {
                    continue; 
                } else {
                    throw $e; // Otro error, lanzar excepción
                }
            }
        }

        if (!$exito) {
            throw new Exception("Error al generar código único para el equipo.");
        }
    }

    // 6. Confirmar Transacción
    $conn->commit();
    echo json_encode(['success' => true, 'id_transaccion' => $id_transaccion]);

} catch (Exception $e) {
    // 7. Revertir cambios si algo falló
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => 'Error en servidor: ' . $e->getMessage()]);
}
?>