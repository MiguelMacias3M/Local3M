<?php
session_start();
include '../config/conexion.php'; 

header('Content-Type: application/json');

// --- 1. LEER DATOS (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = $_GET['accion'] ?? 'listar';

    if ($accion === 'listar') {
        try {
            // Traemos los apartados y los cruzamos con la tabla equipos para saber qué se llevaron
            $sql = "SELECT a.*, e.marca, e.modelo, e.imei_serie 
                    FROM apartados a 
                    INNER JOIN equipos e ON a.id_equipo = e.id 
                    ORDER BY a.estado ASC, a.fecha_limite ASC";
            $stmt = $conn->query($sql);
            $apartados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Verificamos si alguno ya se venció y actualizamos su estado
            $hoy = date('Y-m-d');
            foreach ($apartados as &$ap) {
                if ($ap['estado'] === 'Activo' && $ap['fecha_limite'] < $hoy) {
                    $ap['estado'] = 'Vencido';
                    $conn->prepare("UPDATE apartados SET estado = 'Vencido' WHERE id = ?")->execute([$ap['id']]);
                }
            }
            
            echo json_encode($apartados);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
} 
// --- 2. GUARDAR DATOS (POST) ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $usuario = $_SESSION['nombre'] ?? 'Sistema';

    // ... (AQUÍ ESTÁ TU CÓDIGO DE NUEVO APARTADO INTACTO) ...
    if ($accion === 'nuevo_apartado') {
        $id_equipo = $_POST['id_equipo'];
        $cliente_nombre = $_POST['cliente_nombre'];
        $cliente_telefono = $_POST['cliente_telefono'];
        $total = $_POST['total'];
        $enganche = $_POST['enganche'];
        $restante = $_POST['restante'];
        $fecha_limite = $_POST['fecha_limite'];
        $metodo_pago = $_POST['metodo_pago'];
        
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("INSERT INTO apartados (id_equipo, cliente_nombre, cliente_telefono, total, enganche, restante, fecha_limite, estado, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo', (SELECT id FROM usuarios WHERE nombre = ? LIMIT 1))");
            $stmt->execute([$id_equipo, $cliente_nombre, $cliente_telefono, $total, $enganche, $restante, $fecha_limite, $usuario]);
            $id_apartado = $conn->lastInsertId(); 

            $stmtAbono = $conn->prepare("INSERT INTO abonos_apartados (id_apartado, monto, metodo_pago, id_usuario) VALUES (?, ?, ?, (SELECT id FROM usuarios WHERE nombre = ? LIMIT 1))");
            $stmtAbono->execute([$id_apartado, $enganche, $metodo_pago, $usuario]);

            $stmtEquipo = $conn->prepare("UPDATE equipos SET estado = 'Apartado' WHERE id = ?");
            $stmtEquipo->execute([$id_equipo]);

            $stmtEqNom = $conn->prepare("SELECT marca, modelo FROM equipos WHERE id = ?");
            $stmtEqNom->execute([$id_equipo]);
            $equipoInfo = $stmtEqNom->fetch(PDO::FETCH_ASSOC);
            $nombreEquipo = $equipoInfo['marca'] . ' ' . $equipoInfo['modelo'];

            $idTx = 'APT' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $descripcionCaja = "Enganche de Apartado: " . $nombreEquipo;
            
            $stmtCaja = $conn->prepare("INSERT INTO caja_movimientos (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria, metodo_pago) VALUES (?, 'INGRESO', ?, ?, 1, ?, ?, 0, ?, ?, NOW(), 'Apartados', ?)");
            $stmtCaja->execute([$idTx, $id_apartado, $descripcionCaja, $enganche, $enganche, $usuario, $cliente_nombre, $metodo_pago]);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Apartado creado con éxito.']);
        } catch (PDOException $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
        }
    }
    
    // ===============================================
    // NUEVO: PROCESAR UN ABONO Y LIQUIDACIÓN
    // ===============================================
    else if ($accion === 'abonar') {
        $id_apartado = $_POST['id_apartado'];
        $monto_abono = (float)$_POST['monto'];
        $metodo_pago = $_POST['metodo_pago'];

        try {
            $conn->beginTransaction();

            // 1. Obtener datos actuales del apartado
            $stmtGet = $conn->prepare("SELECT * FROM apartados WHERE id = ?");
            $stmtGet->execute([$id_apartado]);
            $apartado = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$apartado || $apartado['estado'] === 'Liquidado') {
                throw new Exception("El apartado no existe o ya está liquidado.");
            }

            // 2. Calcular nueva deuda
            $nuevo_restante = max(0, $apartado['restante'] - $monto_abono);
            $estado_nuevo = ($nuevo_restante == 0) ? 'Liquidado' : $apartado['estado'];

            // 3. Actualizar el apartado
            $stmtUpd = $conn->prepare("UPDATE apartados SET restante = ?, estado = ? WHERE id = ?");
            $stmtUpd->execute([$nuevo_restante, $estado_nuevo, $id_apartado]);

            // 4. Registrar en historial de abonos
            $stmtAbono = $conn->prepare("INSERT INTO abonos_apartados (id_apartado, monto, metodo_pago, id_usuario) VALUES (?, ?, ?, (SELECT id FROM usuarios WHERE nombre = ? LIMIT 1))");
            $stmtAbono->execute([$id_apartado, $monto_abono, $metodo_pago, $usuario]);

            // 5. Inyectar dinero a caja
            $idTx = 'ABN' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $descripcionCaja = "Abono a Contrato #" . $id_apartado . " (" . $apartado['cliente_nombre'] . ")";
            
            $stmtCaja = $conn->prepare("INSERT INTO caja_movimientos (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria, metodo_pago) VALUES (?, 'INGRESO', ?, ?, 1, ?, ?, 0, ?, ?, NOW(), 'Apartados', ?)");
            $stmtCaja->execute([$idTx, $id_apartado, $descripcionCaja, $monto_abono, $monto_abono, $usuario, $apartado['cliente_nombre'], $metodo_pago]);

            // 6. Si se liquidó, liberar el equipo a "Vendido"
            if ($estado_nuevo === 'Liquidado') {
                $stmtEq = $conn->prepare("UPDATE equipos SET estado = 'Vendido' WHERE id = ?");
                $stmtEq->execute([$apartado['id_equipo']]);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Abono registrado. Saldo restante: $' . number_format($nuevo_restante, 2), 'liquidado' => ($estado_nuevo === 'Liquidado')]);

        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
?>