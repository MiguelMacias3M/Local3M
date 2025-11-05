<?php
// ----------- Esta es la corrección -----------
ini_set('display_errors', 0);
error_reporting(0);
// -------------------------------------------

session_start();
include '../config/conexion.php';
// ... el resto de tu código ...

header('Content-Type: application/json');

// 1. Validar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']); 
    exit;
}

// 2. Leer los datos enviados desde JavaScript
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['carrito'])) {
    echo json_encode(['success' => false, 'error' => 'No hay reparaciones en el carrito.']); 
    exit;
}

// 3. Asignar variables (con valores por defecto por seguridad)
$id_transaccion = $data['id_transaccion'] ?? uniqid('trans_');
$usuario        = $data['usuario'] ?? 'Sistema';
$nombreCliente  = $data['nombreCliente'] ?? '';
$telefono       = $data['telefono'] ?? '';
$info_extra     = $data['infoExtra'] ?? 'Ninguna';
$estado         = 'En espera'; // Estado inicial
$carrito        = $data['carrito'];
$fechaHora      = date('Y-m-d H:i:s'); // Usamos la fecha del servidor

// 4. Validar datos principales
if (empty($nombreCliente) || empty($telefono) || empty($usuario)) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos del cliente o usuario.']);
    exit;
}

// Función para generar un código de reparación único
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

    // SQL para insertar la REPARACIÓN
    $sql_rep = "
        INSERT INTO reparaciones (
            id_transaccion, usuario, nombre_cliente, telefono, 
            tipo_reparacion, marca_celular, modelo, 
            monto, adelanto, deuda, 
            fecha_hora, info_extra, estado, codigo_barras
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_rep = $conn->prepare($sql_rep);

    // SQL para insertar el ADELANTO en la CAJA
    $sql_caja = "
        INSERT INTO caja_movimientos (
            id_transaccion, tipo, ref_id, descripcion, 
            cantidad, monto_unitario, ingreso, egreso, 
            usuario, cliente, fecha
        ) VALUES (?, 'REPARACION', ?, ?, 1, ?, ?, 0, ?, ?, NOW())
    ";
    $stmt_caja = $conn->prepare($sql_caja);

    // 5. Iterar sobre el carrito y guardar cada item
    foreach ($carrito as $r) {
        $maxIntentos = 5;
        $exito = false;
        for ($i = 0; $i < $maxIntentos; $i++) {
            $codigo_barras = generarCodigoReparacion();
            try {
                // 1. Insertar la REPARACIÓN
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

                // 2. Registramos el ADELANTO en la CAJA
                $id_reparacion_insertada = $conn->lastInsertId();
                $adelanto = (float)$r['adelanto'];

                if ($adelanto > 0) {
                    $stmt_caja->execute([
                        $id_transaccion,
                        $id_reparacion_insertada, // ref_id (el ID de la reparación)
                        "Adelanto Reparación: " . $r['tipoReparacion'] . " " . $r['modelo'], // descripcion
                        $adelanto, // monto_unitario
                        $adelanto, // ingreso
                        $usuario,
                        $nombreCliente
                    ]);
                }

                $exito = true;
                break; // Salió bien, pasamos a la siguiente reparación
            } catch (PDOException $e) {
                // Si el código de barras se repite, lo reintenta
                if ($e->getCode() === '23000' && strpos($e->getMessage(), '1062') !== false) {
                    continue; // Clave duplicada (codigo_barras), reintentar
                } else {
                    throw $e; // Otro error real
                }
            }
        }

        if (!$exito) {
            throw new PDOException("No se pudo generar un codigo de barras único después de {$maxIntentos} intentos.");
        }
    }

    // 6. Si todo salió bien, confirmamos la transacción
    $conn->commit();
    echo json_encode(['success' => true, 'id_transaccion' => $id_transaccion]);

} catch (PDOException $e) {
    // 7. Si algo falló, revertimos todo
    if ($conn->inTransaction()) $conn->rollBack();
    // Ahora, aunque haya un error, el JSON será válido
    echo json_encode(['success' => false, 'error' => 'Error al registrar: ' . $e->getMessage()]);
}
?>