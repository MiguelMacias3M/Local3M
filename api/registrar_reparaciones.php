<?php
// api/registrar_reparaciones.php

// 1. Configuración para evitar errores visibles en el JSON
ini_set('display_errors', 0);
error_reporting(0);

session_start();
include '../config/conexion.php';

header('Content-Type: application/json');

// 2. Validar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']); 
    exit;
}

// 3. Leer los datos enviados desde JavaScript
$data = json_decode(file_get_contents('php://input'), true);
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
$estado         = 'En espera'; // Estado inicial por defecto
$carrito        = $data['carrito'];
$fechaHora      = date('Y-m-d H:i:s'); // Fecha del servidor

// 5. Validar datos obligatorios
if (empty($nombreCliente) || empty($telefono) || empty($usuario)) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos del cliente o usuario.']);
    exit;
}

// Función auxiliar para código de barras
function generarCodigoReparacion(): string {
    $fecha = date('ymd');
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len = 5;
    $rand = '';
    for ($i = 0; $i < $len; $i++) {
        $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return "REP{$fecha}{$rand}";
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    // A. SQL para insertar la REPARACIÓN
    $sql_rep = "INSERT INTO reparaciones (
            id_transaccion, usuario, nombre_cliente, telefono, 
            tipo_reparacion, marca_celular, modelo, 
            monto, adelanto, deuda, 
            fecha_hora, info_extra, estado, codigo_barras
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_rep = $conn->prepare($sql_rep);

    // B. SQL para insertar el ADELANTO en CAJA
    $sql_caja = "INSERT INTO caja_movimientos (
            id_transaccion, tipo, ref_id, descripcion, 
            cantidad, monto_unitario, ingreso, egreso, 
            usuario, cliente, fecha
        ) VALUES (?, 'REPARACION', ?, ?, 1, ?, ?, 0, ?, ?, NOW())";
    $stmt_caja = $conn->prepare($sql_caja);

    // C. SQL para insertar el HISTORIAL (¡NUEVO!)
    $sql_hist = "INSERT INTO historial_reparaciones (
            id_reparacion, estado_nuevo, comentario, usuario_responsable, fecha_cambio
        ) VALUES (?, ?, ?, ?, ?)";
    $stmt_hist = $conn->prepare($sql_hist);


    // 6. Iterar sobre el carrito
    foreach ($carrito as $r) {
        $maxIntentos = 5;
        $exito = false;
        
        for ($i = 0; $i < $maxIntentos; $i++) {
            $codigo_barras = generarCodigoReparacion();
            try {
                // --- PASO 1: Insertar Reparación ---
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
                    $estado,
                    $codigo_barras
                ]);

                // Obtenemos el ID de la reparación recién creada
                $id_reparacion_insertada = $conn->lastInsertId();


                // --- PASO 2: Insertar en Historial (INGRESO) ---
                $stmt_hist->execute([
                    $id_reparacion_insertada,
                    'Ingreso',              // Estado
                    'Recepción del equipo', // Comentario inicial
                    $usuario,               // Responsable
                    $fechaHora              // Fecha exacta
                ]);


                // --- PASO 3: Insertar en Caja (si hay dinero) ---
                $adelanto = (float)$r['adelanto'];
                if ($adelanto > 0) {
                    $stmt_caja->execute([
                        $id_transaccion,
                        $id_reparacion_insertada, // ref_id
                        "Adelanto Reparación: " . $r['tipoReparacion'] . " " . $r['modelo'],
                        $adelanto, // monto unitario
                        $adelanto, // ingreso
                        $usuario,
                        $nombreCliente
                    ]);
                }

                $exito = true;
                break; // Todo salió bien, salimos del reintento de código

            } catch (PDOException $e) {
                // Si el error es por código duplicado (1062), reintentamos
                if ($e->getCode() === '23000' && strpos($e->getMessage(), '1062') !== false) {
                    continue; 
                } else {
                    throw $e; // Si es otro error, lo lanzamos
                }
            }
        }

        if (!$exito) {
            throw new PDOException("No se pudo generar un codigo único después de varios intentos.");
        }
    }

    // 7. Confirmar todo
    $conn->commit();
    echo json_encode(['success' => true, 'id_transaccion' => $id_transaccion]);

} catch (PDOException $e) {
    // 8. Revertir si algo falló
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => 'Error al registrar: ' . $e->getMessage()]);
}
?>