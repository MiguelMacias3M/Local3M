<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

// Inicializar carrito
if (!isset($_SESSION['carrito_venta'])) {
    $_SESSION['carrito_venta'] = [];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. BUSCAR PRODUCTOS ---
    if ($action === 'buscar') {
        $q = $_GET['q'] ?? '';
        
        $sql = "SELECT * FROM productos WHERE 
                (LOWER(nombre_producto) LIKE :q1 OR CAST(codigo_barras AS CHAR) LIKE :q2) 
                AND cantidad_piezas > 0 
                ORDER BY nombre_producto ASC LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        $term = '%' . strtolower($q) . '%';
        $stmt->execute([':q1' => $term, ':q2' => $term]);
        
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $productos]);
        exit();
    }

    // --- 2. OBTENER CARRITO (Sistema Viejo) ---
    if ($action === 'get_carrito') {
        echo json_encode(['success' => true, 'carrito' => array_values($_SESSION['carrito_venta'])]);
        exit();
    }

    // --- 3. AGREGAR AL CARRITO (Sistema Viejo) ---
    if ($action === 'agregar') {
        $id = $_POST['id'] ?? null;
        $cantidad = (int)($_POST['cantidad'] ?? 1);

        if (!$id) throw new Exception("ID de producto no válido");

        $stmt = $conn->prepare("SELECT * FROM productos WHERE id_productos = :id");
        $stmt->execute([':id' => $id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
            exit();
        }

        $indexEncontrado = -1;
        foreach ($_SESSION['carrito_venta'] as $key => $item) {
            if ($item['id'] == $id) {
                $indexEncontrado = $key;
                break;
            }
        }

        if ($indexEncontrado >= 0) {
            $nuevaCantidad = $_SESSION['carrito_venta'][$indexEncontrado]['cantidad'] + $cantidad;
            if ($nuevaCantidad > $prod['cantidad_piezas']) {
                echo json_encode(['success' => false, 'error' => 'Stock insuficiente']);
                exit();
            }
            $_SESSION['carrito_venta'][$indexEncontrado]['cantidad'] = $nuevaCantidad;
        } else {
            if ($cantidad > $prod['cantidad_piezas']) {
                echo json_encode(['success' => false, 'error' => 'Stock insuficiente']);
                exit();
            }
            $_SESSION['carrito_venta'][] = [
                'id' => $prod['id_productos'],
                'nombre' => $prod['nombre_producto'],
                'precio' => (float)$prod['precio_producto'],
                'codigo' => $prod['codigo_barras'],
                'cantidad' => $cantidad
            ];
        }
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 4. ELIMINAR DEL CARRITO (Sistema Viejo) ---
    if ($action === 'eliminar') {
        $index = $_POST['index'] ?? null;
        if (isset($_SESSION['carrito_venta'][$index])) {
            array_splice($_SESSION['carrito_venta'], $index, 1);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 5. LIMPIAR CARRITO (Sistema Viejo) ---
    if ($action === 'limpiar') {
        $_SESSION['carrito_venta'] = [];
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 6. FINALIZAR VENTA (Sistema Viejo) ---
    if ($action === 'finalizar') {
        if (empty($_SESSION['carrito_venta'])) {
            echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
            exit();
        }

        $conn->beginTransaction();
        $idTx = 'VEN' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $usuario = $_SESSION['nombre'];
        $totalVenta = 0;

        $sqlVenta = "INSERT INTO ventas (id_producto, cantidad, id_transaccion, usuario, fecha) VALUES (?, ?, ?, ?, NOW())";
        $stmtVenta = $conn->prepare($sqlVenta);

        $sqlUpdate = "UPDATE productos SET cantidad_piezas = cantidad_piezas - ? WHERE id_productos = ? AND cantidad_piezas >= ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);

        foreach ($_SESSION['carrito_venta'] as $item) {
            $stmtUpdate->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
            if ($stmtUpdate->rowCount() === 0) {
                throw new Exception("Stock insuficiente para: " . $item['nombre']);
            }
            $stmtVenta->execute([$item['id'], $item['cantidad'], $idTx, $usuario]);
            $totalVenta += ($item['precio'] * $item['cantidad']);
        }

        $sqlCaja = "INSERT INTO caja_movimientos 
                    (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria) 
                    VALUES (?, 'INGRESO', 0, 'Venta de Productos', 1, ?, ?, 0, ?, 'Público General', NOW(), 'Venta')";
        
        $stmtCaja = $conn->prepare($sqlCaja);
        $stmtCaja->execute([$idTx, $totalVenta, $totalVenta, $usuario]);
        $conn->commit();
        
        $_SESSION['carrito_venta'] = [];
        echo json_encode(['success' => true, 'id_transaccion' => $idTx, 'ticketUrl' => 'generar_ticket_venta.php?id_transaccion=' . urlencode($idTx)]);
        exit();
    }

// =========================================================================
    // --- 7. FINALIZAR VENTA DESDE EL CARRITO GLOBAL (NUEVO SISTEMA FLOTANTE) ---
    // =========================================================================
    if ($action === 'finalizar_global') {
        $input = json_decode(file_get_contents('php://input'), true);
        $carrito = $input['carrito'] ?? [];
        $pagaCon = $input['paga_con'] ?? 0;

        if (empty($carrito)) {
            echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
            exit();
        }

        $conn->beginTransaction();
        
        $idTx = 'VEN' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $usuario = $_SESSION['nombre'];

        // Sentencias para Productos
        $sqlVenta = "INSERT INTO ventas (id_producto, cantidad, id_transaccion, usuario, fecha) VALUES (?, ?, ?, ?, NOW())";
        $stmtVenta = $conn->prepare($sqlVenta);

        $sqlUpdateStock = "UPDATE productos SET cantidad_piezas = cantidad_piezas - ? WHERE id_productos = ? AND cantidad_piezas >= ?";
        $stmtUpdateStock = $conn->prepare($sqlUpdateStock);
        
        // Sentencias para Reparaciones
        $sqlRepUpdate = "UPDATE reparaciones SET estado = 'Entregado', deuda = 0 WHERE id = ?";
        $stmtRepUpdate = $conn->prepare($sqlRepUpdate);
        
        $sqlHistorial = "INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) 
                         VALUES (?, 'Entregado', 'Equipo entregado y saldo liquidado en caja general. Folio Venta: $idTx', ?)";
        $stmtHist = $conn->prepare($sqlHistorial);

        // NUEVO: Sentencia para Caja (Guardará ítem por ítem)
        $sqlCaja = "INSERT INTO caja_movimientos 
                    (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), ?)";
        $stmtCaja = $conn->prepare($sqlCaja);

        foreach ($carrito as $item) {
            
            if ($item['tipo'] === 'producto') {
                // 1. Descontar Stock
                $stmtUpdateStock->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception("Stock insuficiente para: " . $item['nombre']);
                }

                // 2. Registrar Venta Interna
                $stmtVenta->execute([$item['id'], $item['cantidad'], $idTx, $usuario]);
                
                // 3. Registrar Movimiento Detallado en Caja
                $subtotal = $item['precio'] * $item['cantidad'];
                $stmtCaja->execute([
                    $idTx, 'INGRESO', $item['id'], $item['nombre'], $item['cantidad'], 
                    $item['precio'], $subtotal, $usuario, 'Público General', 'Venta'
                ]);

} else if ($item['tipo'] === 'reparacion') {
                $accionRep = $item['accion_reparacion'] ?? 'liquidar';
                $monto_pagado = $item['a_cobrar'];

                // 1. Buscamos el nombre del cliente y estado actual
                $stmtGetRep = $conn->prepare("SELECT nombre_cliente, tipo_reparacion, modelo, estado FROM reparaciones WHERE id = ?");
                $stmtGetRep->execute([$item['id']]);
                $repDB = $stmtGetRep->fetch(PDO::FETCH_ASSOC);
                
                $clienteReal = $repDB ? $repDB['nombre_cliente'] : 'Cliente Mostrador';
                $detalleReal = $repDB ? $repDB['tipo_reparacion'] . ' ' . $repDB['modelo'] : $item['nombre'];
                $estadoActual = $repDB ? $repDB['estado'] : 'En progreso';

                // ==========================================
                // CASO A: LIQUIDAR Y ENTREGAR
                // ==========================================
                if ($accionRep === 'liquidar') {
                    $stmtRepUpdate->execute([$item['id']]); // Esto le pone estado 'Entregado' y deuda 0
                    $stmtHist->execute([$item['id'], $usuario]);

                    $stmtCaja->execute([
                        $idTx, 'REPARACION', $item['id'], 'Pago Final: ' . $detalleReal, 1, 
                        $monto_pagado, $monto_pagado, $usuario, $clienteReal, 'General'
                    ]);
                } 
                // ==========================================
                // CASO B: SOLO ABONAR (No se entrega)
                // ==========================================
                else if ($accionRep === 'abonar') {
                    
                    // Sumamos al adelanto y restamos a la deuda
                    $sqlAbono = "UPDATE reparaciones SET adelanto = adelanto + ?, deuda = GREATEST(0, deuda - ?) WHERE id = ?";
                    $stmtAbono = $conn->prepare($sqlAbono);
                    $stmtAbono->execute([$monto_pagado, $monto_pagado, $item['id']]);

                    // Guardamos en el historial (dejando el mismo estado que ya tenía)
                    $comentario = "Abono registrado en caja por $" . number_format($monto_pagado, 2) . ". Folio Venta: $idTx";
                    $sqlHistAbono = "INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, ?, ?, ?)";
                    $stmtHistAbono = $conn->prepare($sqlHistAbono);
                    $stmtHistAbono->execute([$item['id'], $estadoActual, $comentario, $usuario]);

                    // Registramos en Caja como "Abono"
                    $stmtCaja->execute([
                        $idTx, 'REPARACION', $item['id'], 'Abono: ' . $detalleReal, 1, 
                        $monto_pagado, $monto_pagado, $usuario, $clienteReal, 'Abono'
                    ]);
                }
            }
        }

        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'id_transaccion' => $idTx, 
            // AQUÍ AGREGAMOS EL PARAMETRO &paga_con=
            'ticketUrl' => '/local3M/generar_ticket_venta.php?id_transaccion=' . urlencode($idTx) . '&paga_con=' . urlencode($pagaCon)
        ]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Si hay un error, lo devolvemos como JSON limpio
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
?>