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
        // Mantenemos esto por si tienes otra sección usando el carrito viejo
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
    // --- 7. FINALIZAR VENTA DESDE EL CARRITO GLOBAL (CON DESCUENTOS) ---
    // =========================================================================
    if ($action === 'finalizar_global') {
        $input = json_decode(file_get_contents('php://input'), true);
        $carrito = $input['carrito'] ?? [];
        $pagaCon = $input['paga_con'] ?? 0;
        $metodoPago = $input['metodo_pago'] ?? 'Efectivo'; 

        if (empty($carrito)) {
            echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
            exit();
        }

        $conn->beginTransaction();
        
        $idTx = 'VEN' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $usuario = $_SESSION['nombre'];

        // Queries preparadas para Productos
        $sqlVenta = "INSERT INTO ventas (id_producto, cantidad, id_transaccion, usuario, fecha) VALUES (?, ?, ?, ?, NOW())";
        $stmtVenta = $conn->prepare($sqlVenta);

        $sqlUpdateStock = "UPDATE productos SET cantidad_piezas = cantidad_piezas - ? WHERE id_productos = ? AND cantidad_piezas >= ?";
        $stmtUpdateStock = $conn->prepare($sqlUpdateStock);
        
        // Queries preparadas para Reparaciones
        $sqlRepUpdate = "UPDATE reparaciones SET estado = 'Entregado', adelanto = adelanto + deuda, deuda = 0 WHERE id = ?";        
        $stmtRepUpdate = $conn->prepare($sqlRepUpdate);
        
        $sqlHistorial = "INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) 
                         VALUES (?, 'Entregado', ?, ?)";
        $stmtHist = $conn->prepare($sqlHistorial);

        // Queries preparadas para Equipos de Vitrina Unificada
        $sqlEquipoUpdate = "UPDATE vitrina SET estado = 'Vendido', cliente_nombre = 'Mostrador', fecha_operacion = NOW() WHERE id = ?";        
        $stmtEquipoUpdate = $conn->prepare($sqlEquipoUpdate);

        // Caja Central
        $sqlCaja = "INSERT INTO caja_movimientos 
                    (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria, metodo_pago) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), ?, ?)";
        $stmtCaja = $conn->prepare($sqlCaja);

        foreach ($carrito as $item) {
            
            // CAPTURAR EL DESCUENTO NEGOCIADO
            $descuentoUnitario = isset($item['descuento_unitario']) ? (float)$item['descuento_unitario'] : 0;
            
            // ==========================================
            // PROCESAR PRODUCTOS NORMALES
            // ==========================================
            if ($item['tipo'] === 'producto') {
                $stmtUpdateStock->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
                if ($stmtUpdateStock->rowCount() === 0) throw new Exception("Stock insuficiente para: " . $item['nombre']);

                $stmtVenta->execute([$item['id'], $item['cantidad'], $idTx, $usuario]);
                
                // Lógica de precio real
                $precioOriginal = (float)$item['precio'];
                $precioFinal = max(0, $precioOriginal - $descuentoUnitario);
                $subtotalFinal = $precioFinal * $item['cantidad'];
                
                $descripcion = $item['nombre'];
                if ($descuentoUnitario > 0) {
                    $descripcion .= " (Descuento aplicado: -$" . number_format($descuentoUnitario * $item['cantidad'], 2) . ")";
                }

                $stmtCaja->execute([
                    $idTx, 'INGRESO', $item['id'], $descripcion, $item['cantidad'], 
                    $precioFinal, $subtotalFinal, $usuario, 'Público General', 'Venta', $metodoPago
                ]);
            } 
            // ==========================================
            // PROCESAR EQUIPOS DE VITRINA (VENTA DIRECTA)
            // ==========================================
            else if ($item['tipo'] === 'equipo') {
                $clienteEq = (isset($item['cliente_nombre']) && trim($item['cliente_nombre']) !== '') ? $item['cliente_nombre'] : 'Público General';
                $telefonoEq = $item['telefono'] ?? '';
                
                $stmtEq = $conn->prepare("UPDATE vitrina SET estado = 'Vendido', cliente_nombre = ?, cliente_telefono = ?, fecha_operacion = NOW() WHERE id = ?");
                $stmtEq->execute([$clienteEq, $telefonoEq, $item['id']]);

                // Lógica de precio real
                $precioOriginal = (float)$item['precio'];
                $precioFinal = max(0, $precioOriginal - $descuentoUnitario);
                $subtotalFinal = $precioFinal * $item['cantidad'];
                
                $descripcion = "Venta Equipo: " . $item['nombre'];
                if ($descuentoUnitario > 0) {
                    $descripcion .= " (Descuento: -$" . number_format($descuentoUnitario * $item['cantidad'], 2) . ")";
                }
                
                $stmtCaja->execute([
                    $idTx, 'INGRESO', $item['id'], $descripcion, $item['cantidad'], 
                    $precioFinal, $subtotalFinal, $usuario, $clienteEq, 'Equipos', $metodoPago
                ]);
            }
            // ==========================================
            // PROCESAR REPARACIONES
            // ==========================================
            else if ($item['tipo'] === 'reparacion') {
                $accionRep = $item['accion_reparacion'] ?? 'liquidar';
                
                // Lógica de precio real para el cobro actual
                $monto_pagado_original = (float)$item['a_cobrar'];
                $monto_pagado_final = max(0, $monto_pagado_original - $descuentoUnitario); 

                $stmtGetRep = $conn->prepare("SELECT nombre_cliente, tipo_reparacion, modelo, estado FROM reparaciones WHERE id = ?");
                $stmtGetRep->execute([$item['id']]);
                $repDB = $stmtGetRep->fetch(PDO::FETCH_ASSOC);
                
                $clienteReal = $repDB ? $repDB['nombre_cliente'] : 'Cliente Mostrador';
                $detalleReal = $repDB ? $repDB['tipo_reparacion'] . ' ' . $repDB['modelo'] : $item['nombre'];
                $estadoActual = $repDB ? $repDB['estado'] : 'En progreso';
                
                $descDescuento = ($descuentoUnitario > 0) ? " (Desc: -$" . number_format($descuentoUnitario, 2) . ")" : "";

                if ($accionRep === 'liquidar') {
                    $stmtRepUpdate->execute([$item['id']]);
                    $comentario = "Equipo entregado y saldo liquidado en caja ($metodoPago). Folio: $idTx";
                    $stmtHist->execute([$item['id'], $comentario, $usuario]);

                    $stmtCaja->execute([
                        $idTx, 'REPARACION', $item['id'], 'Pago Final: ' . $detalleReal . $descDescuento, 1, 
                        $monto_pagado_final, $monto_pagado_final, $usuario, $clienteReal, 'General', $metodoPago
                    ]);
                } 
                else if ($accionRep === 'abonar') {
                    $sqlAbono = "UPDATE reparaciones SET adelanto = adelanto + ?, deuda = GREATEST(0, deuda - ?) WHERE id = ?";
                    $stmtAbono = $conn->prepare($sqlAbono);
                    $stmtAbono->execute([$monto_pagado_final, $monto_pagado_final, $item['id']]);

                    $comentario = "Abono registrado en caja por $" . number_format($monto_pagado_final, 2) . " ($metodoPago). Folio: $idTx";
                    $stmtHistAbono = $conn->prepare("INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, ?, ?, ?)");
                    $stmtHistAbono->execute([$item['id'], $estadoActual, $comentario, $usuario]);

                    $stmtCaja->execute([
                        $idTx, 'REPARACION', $item['id'], 'Abono: ' . $detalleReal . $descDescuento, 1, 
                        $monto_pagado_final, $monto_pagado_final, $usuario, $clienteReal, 'Abono', $metodoPago
                    ]);
                }
                else if ($accionRep === 'nuevo_adelanto') {
                    $comentarioCaja = "Adelanto (Nueva Orden): " . $detalleReal . $descDescuento;
                    $stmtCaja->execute([
                        $idTx, 'REPARACION', $item['id'], $comentarioCaja, 1, 
                        $monto_pagado_final, $monto_pagado_final, $usuario, $clienteReal, 'Adelanto', $metodoPago
                    ]);
                    
                    $comentarioHistorial = "Adelanto cobrado en caja por $" . number_format($monto_pagado_final, 2) . " ($metodoPago). Folio: $idTx";
                    $stmtHistExtra = $conn->prepare("INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, ?, ?, ?)");
                    $stmtHistExtra->execute([$item['id'], $estadoActual, $comentarioHistorial, $usuario]);
                }
            }
            // ============================================================
            // PROCESAR ABONOS Y NUEVOS APARTADOS VITRINA (SIN DESCUENTOS)
            // ============================================================
            else if ($item['tipo'] === 'abono_apartado') {
                $id_equipo = $item['id'];
                $monto_pago = (float)$item['precio'];
                $cliente_nombre = (isset($item['cliente_nombre']) && trim($item['cliente_nombre']) !== '') ? $item['cliente_nombre'] : 'Público General';
                $metodoAbonoDB = ($metodoPago === 'Terminal') ? 'Tarjeta' : $metodoPago;

                if (isset($item['es_nuevo_apartado']) && $item['es_nuevo_apartado'] == true) {
                    $telefono = $item['telefono'] ?? '';
                    $saldo = (float)$item['saldo_restante'];
                    
                    $stmtUpd = $conn->prepare("UPDATE vitrina SET estado = 'Apartado', cliente_nombre = ?, cliente_telefono = ?, anticipo = ?, saldo_restante = ?, fecha_operacion = NOW() WHERE id = ?");
                    $stmtUpd->execute([$cliente_nombre, $telefono, $monto_pago, $saldo, $id_equipo]);
                    
                    $descripcionCaja = "Enganche: " . str_replace("Enganche: ", "", $item['nombre']);
                } 
                else {
                    $stmtGet = $conn->prepare("SELECT anticipo, saldo_restante FROM vitrina WHERE id = ?");
                    $stmtGet->execute([$id_equipo]);
                    $equipo = $stmtGet->fetch(PDO::FETCH_ASSOC);

                    if ($equipo) {
                        $nuevo_anticipo = $equipo['anticipo'] + $monto_pago;
                        $nuevo_saldo = max(0, $equipo['saldo_restante'] - $monto_pago);
                        $estado_nuevo = ($nuevo_saldo == 0) ? 'Vendido' : 'Apartado';

                        $stmtUpd = $conn->prepare("UPDATE vitrina SET estado = ?, anticipo = ?, saldo_restante = ?, fecha_operacion = NOW() WHERE id = ?");
                        $stmtUpd->execute([$estado_nuevo, $nuevo_anticipo, $nuevo_saldo, $id_equipo]);
                    }
                    $descripcionCaja = "Abono Vitrina: " . str_replace("Abono: ", "", $item['nombre']);
                }

                $stmtAbono = $conn->prepare("INSERT INTO abonos_apartados (id_apartado, monto, metodo_pago, fecha_abono, id_usuario) VALUES (?, ?, ?, NOW(), (SELECT id FROM usuarios WHERE nombre = ? LIMIT 1))");
                $stmtAbono->execute([$id_equipo, $monto_pago, $metodoAbonoDB, $usuario]);

                $stmtCaja->execute([
                    $idTx, 'INGRESO', $id_equipo, $descripcionCaja, 1, 
                    $monto_pago, $monto_pago, $usuario, $cliente_nombre, 'Abono', $metodoPago
                ]);
            }
        }

        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'id_transaccion' => $idTx, 
            'ticketUrl' => '/local3M/generar_ticket_venta.php?id_transaccion=' . urlencode($idTx) . '&paga_con=' . urlencode($pagaCon)
        ]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
?>