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

    // --- 2. OBTENER CARRITO ---
    if ($action === 'get_carrito') {
        echo json_encode(['success' => true, 'carrito' => array_values($_SESSION['carrito_venta'])]);
        exit();
    }

    // --- 3. AGREGAR AL CARRITO (Pestaña local) ---
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
            if ($item['id'] == $id) { $indexEncontrado = $key; break; }
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

    // --- 4. ELIMINAR DEL CARRITO ---
    if ($action === 'eliminar') {
        $index = $_POST['index'] ?? null;
        if (isset($_SESSION['carrito_venta'][$index])) array_splice($_SESSION['carrito_venta'], $index, 1);
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 5. LIMPIAR CARRITO ---
    if ($action === 'limpiar') {
        $_SESSION['carrito_venta'] = [];
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 6. FINALIZAR VENTA (Sistema Viejo) ---
    if ($action === 'finalizar') {
        $stmtCajaCheck = $conn->query("SELECT COUNT(*) FROM caja_cierres WHERE estado = 'ABIERTA'");
        if ($stmtCajaCheck->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'error' => '🛑 CAJA CERRADA. Abre turno en el módulo de Caja. <a href="cierre_caja.php" style="color: #007aff; font-weight: bold; text-decoration: none;">Abre el turno aquí</a>']);
            exit();
        }
        if (empty($_SESSION['carrito_venta'])) {
            echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
            exit();
        }

        $conn->beginTransaction();
        $idTx = 'VEN' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $usuario = $_SESSION['nombre'];
        $totalVenta = 0;

        $sqlUpdate = "UPDATE productos SET cantidad_piezas = cantidad_piezas - ? WHERE id_productos = ? AND cantidad_piezas >= ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        
        $sqlCaja = "INSERT INTO caja_movimientos 
                    (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria) 
                    VALUES (?, 'INGRESO', ?, ?, ?, ?, ?, 0, ?, 'Público General', NOW(), 'Venta')";
        $stmtCaja = $conn->prepare($sqlCaja);

        foreach ($_SESSION['carrito_venta'] as $item) {
            $stmtUpdate->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
            if ($stmtUpdate->rowCount() === 0) throw new Exception("Stock insuficiente para: " . $item['nombre']);
            
            $subtotalFila = $item['precio'] * $item['cantidad'];
            // Registramos directamente en caja_movimientos (Adiós tabla ventas)
            $stmtCaja->execute([$idTx, $item['id'], $item['nombre'], $item['cantidad'], $item['precio'], $subtotalFila, $usuario]);
        }
        
        $conn->commit();
        $_SESSION['carrito_venta'] = [];
        echo json_encode(['success' => true, 'id_transaccion' => $idTx, 'ticketUrl' => 'generar_ticket_venta.php?id_transaccion=' . urlencode($idTx)]);
        exit();
    }

    // =========================================================================
    // --- 7. FINALIZAR VENTA DESDE EL CARRITO GLOBAL ---
    // =========================================================================
    if ($action === 'finalizar_global') {
        $input = json_decode(file_get_contents('php://input'), true);
        $carrito = $input['carrito'] ?? [];
        $pagaCon = $input['paga_con'] ?? 0;
        $metodoPago = $input['metodo_pago'] ?? 'Efectivo'; 

        // 🔒 CANDADO DE CAJA INFALIBLE
        $stmtCajaCheck = $conn->query("SELECT COUNT(*) FROM caja_cierres WHERE estado = 'ABIERTA'");
        if ($stmtCajaCheck->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'error' => '🛑 CAJA CERRADA. Ve al módulo de Flujo de Caja y abre turno antes de procesar pagos. <a href="cierre_caja.php" style="color: #007aff; font-weight: bold; text-decoration: none;">Abre el turno aquí</a>']);
            exit();
        }

        if (empty($carrito)) {
            echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
            exit();
        }

        $conn->beginTransaction();
        $idTx = 'VEN' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $usuario = $_SESSION['nombre'];

        // Ya NO preparamos un INSERT para la tabla `ventas`
        $sqlUpdateStock = "UPDATE productos SET cantidad_piezas = cantidad_piezas - ? WHERE id_productos = ? AND cantidad_piezas >= ?";
        $stmtUpdateStock = $conn->prepare($sqlUpdateStock);
        $sqlRepUpdate = "UPDATE reparaciones SET estado = 'Entregado', adelanto = adelanto + deuda, deuda = 0 WHERE id = ?";        
        $stmtRepUpdate = $conn->prepare($sqlRepUpdate);
        $sqlHistorial = "INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, 'Entregado', ?, ?)";
        $stmtHist = $conn->prepare($sqlHistorial);

        $pagosMixtos = $input['pagos_mixtos'] ?? ['efectivo' => 0, 'terminal' => 0, 'transferencia' => 0];
        $bolsaEfectivo = 0; $bolsaTerminal = 0; $bolsaTransferencia = 0;

        if ($metodoPago === 'Mixto') {
            $totalCobrarExacto = 0;
            foreach ($carrito as $i) {
                $dUnit = isset($i['descuento_unitario']) ? (float)$i['descuento_unitario'] : 0;
                if ($i['tipo'] === 'producto' || $i['tipo'] === 'equipo') $totalCobrarExacto += (max(0, (float)$i['precio'] - $dUnit) * $i['cantidad']);
                else if ($i['tipo'] === 'reparacion') $totalCobrarExacto += max(0, (float)$i['a_cobrar'] - $dUnit);
                else if ($i['tipo'] === 'abono_apartado') $totalCobrarExacto += (float)$i['precio'];
            }
            $sumaRecibida = $pagosMixtos['efectivo'] + $pagosMixtos['terminal'] + $pagosMixtos['transferencia'];
            $cambio = max(0, $sumaRecibida - $totalCobrarExacto);
            $bolsaEfectivo = max(0, $pagosMixtos['efectivo'] - $cambio);
            $bolsaTerminal = $pagosMixtos['terminal'];
            $bolsaTransferencia = $pagosMixtos['transferencia'];
        }

        // Query Central para Guardar Todo en CAJA_MOVIMIENTOS
        $stmtCaja = $conn->prepare("INSERT INTO caja_movimientos (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria, metodo_pago) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), ?, ?)");

        $procesarCobroCaja = function($tipoMov, $refId, $descripcion, $cantidad, $montoFila, $clienteCaja, $catCaja) use ($stmtCaja, $idTx, $usuario, &$bolsaEfectivo, &$bolsaTerminal, &$bolsaTransferencia, $metodoPago) {
            if ($montoFila <= 0) return; 
            if ($metodoPago !== 'Mixto') {
                $stmtCaja->execute([$idTx, $tipoMov, $refId, $descripcion, $cantidad, $montoFila / $cantidad, $montoFila, $usuario, $clienteCaja, $catCaja, $metodoPago]);
                return;
            }
            $montoPorCobrar = $montoFila;
            while ($montoPorCobrar > 0.009) {
                $montoAplicar = 0; $metodoAplicar = '';
                if ($bolsaEfectivo > 0.009) { $montoAplicar = min($montoPorCobrar, $bolsaEfectivo); $bolsaEfectivo -= $montoAplicar; $metodoAplicar = 'Efectivo'; } 
                else if ($bolsaTerminal > 0.009) { $montoAplicar = min($montoPorCobrar, $bolsaTerminal); $bolsaTerminal -= $montoAplicar; $metodoAplicar = 'Terminal'; } 
                else if ($bolsaTransferencia > 0.009) { $montoAplicar = min($montoPorCobrar, $bolsaTransferencia); $bolsaTransferencia -= $montoAplicar; $metodoAplicar = 'Transferencia'; } 
                else { $montoAplicar = $montoPorCobrar; $metodoAplicar = 'Efectivo'; }
                
                $descFinal = $descripcion . " [Pagado c/ " . $metodoAplicar . "]";
                $stmtCaja->execute([$idTx, $tipoMov, $refId, $descFinal, 1, $montoAplicar, $montoAplicar, $usuario, $clienteCaja, $catCaja, $metodoAplicar]);
                $montoPorCobrar -= $montoAplicar;
            }
        };

        foreach ($carrito as $item) {
            $descuentoUnitario = isset($item['descuento_unitario']) ? (float)$item['descuento_unitario'] : 0;
            
            if ($item['tipo'] === 'producto') {
                $stmtUpdateStock->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
                if ($stmtUpdateStock->rowCount() === 0) throw new Exception("Stock insuficiente para: " . $item['nombre']);
                
                $precioFinal = max(0, (float)$item['precio'] - $descuentoUnitario);
                $descripcion = $item['nombre'] . ($descuentoUnitario > 0 ? " (Desc: -$" . number_format($descuentoUnitario * $item['cantidad'], 2) . ")" : "");
                
                // Entra 100% directo a la caja
                $procesarCobroCaja('INGRESO', $item['id'], $descripcion, $item['cantidad'], $precioFinal * $item['cantidad'], 'Público General', 'Venta');
            } 
            else if ($item['tipo'] === 'servicio') {
                $precioFinal = max(0, (float)$item['precio'] - $descuentoUnitario);
                $descripcion = $item['nombre'] . ($descuentoUnitario > 0 ? " (Desc: -$" . number_format($descuentoUnitario * $item['cantidad'], 2) . ")" : "");
                $procesarCobroCaja('INGRESO', 0, $descripcion, $item['cantidad'], $precioFinal * $item['cantidad'], 'Público General', 'Servicio');
            }
            else if ($item['tipo'] === 'equipo') {
                $clienteEq = (isset($item['cliente_nombre']) && trim($item['cliente_nombre']) !== '') ? $item['cliente_nombre'] : 'Público General';
                $stmtEq = $conn->prepare("UPDATE vitrina SET estado = 'Vendido', cliente_nombre = ?, cliente_telefono = ?, fecha_operacion = NOW() WHERE id = ?");
                $stmtEq->execute([$clienteEq, $item['telefono'] ?? '', $item['id']]);
                $precioFinal = max(0, (float)$item['precio'] - $descuentoUnitario);
                $descripcion = "Venta Equipo: " . $item['nombre'] . ($descuentoUnitario > 0 ? " (Desc: -$" . number_format($descuentoUnitario * $item['cantidad'], 2) . ")" : "");
                $procesarCobroCaja('INGRESO', $item['id'], $descripcion, $item['cantidad'], $precioFinal * $item['cantidad'], $clienteEq, 'Equipos');
            }
            else if ($item['tipo'] === 'reparacion') {
                $accionRep = $item['accion_reparacion'] ?? 'liquidar';
                $monto_pagado_final = max(0, (float)$item['a_cobrar'] - $descuentoUnitario); 
                $stmtGetRep = $conn->prepare("SELECT nombre_cliente, tipo_reparacion, modelo, estado FROM reparaciones WHERE id = ?");
                $stmtGetRep->execute([$item['id']]);
                $repDB = $stmtGetRep->fetch(PDO::FETCH_ASSOC);
                $clienteReal = $repDB ? $repDB['nombre_cliente'] : 'Cliente Mostrador';
                $detalleReal = $repDB ? $repDB['tipo_reparacion'] . ' ' . $repDB['modelo'] : $item['nombre'];
                $descDescuento = ($descuentoUnitario > 0) ? " (Desc: -$" . number_format($descuentoUnitario, 2) . ")" : "";

                if ($accionRep === 'liquidar') {
                    $stmtRepUpdate->execute([$item['id']]);
                    $stmtHist->execute([$item['id'], "Equipo entregado y saldo liquidado en caja ($metodoPago). Folio: $idTx", $usuario]);
                    $procesarCobroCaja('REPARACION', $item['id'], 'Pago Final: ' . $detalleReal . $descDescuento, 1, $monto_pagado_final, $clienteReal, 'General');
                } else if ($accionRep === 'abonar') {
                    $conn->prepare("UPDATE reparaciones SET adelanto = adelanto + ?, deuda = GREATEST(0, deuda - ?) WHERE id = ?")->execute([$monto_pagado_final, $monto_pagado_final, $item['id']]);
                    $conn->prepare("INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, ?, ?, ?)")->execute([$item['id'], $repDB['estado'], "Abono registrado en caja por $" . number_format($monto_pagado_final, 2) . " ($metodoPago). Folio: $idTx", $usuario]);
                    $procesarCobroCaja('REPARACION', $item['id'], 'Abono: ' . $detalleReal . $descDescuento, 1, $monto_pagado_final, $clienteReal, 'Abono');
                } else if ($accionRep === 'nuevo_adelanto') {
                    $conn->prepare("INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, ?, ?, ?)")->execute([$item['id'], $repDB['estado'], "Adelanto cobrado en caja por $" . number_format($monto_pagado_final, 2) . " ($metodoPago). Folio: $idTx", $usuario]);
                    $procesarCobroCaja('REPARACION', $item['id'], "Adelanto: " . $detalleReal . $descDescuento, 1, $monto_pagado_final, $clienteReal, 'Adelanto');
                }
            }
            else if ($item['tipo'] === 'abono_apartado') {
                $id_equipo = $item['id'];
                $monto_pago = (float)$item['precio'];
                $cliente_nombre = (isset($item['cliente_nombre']) && trim($item['cliente_nombre']) !== '') ? $item['cliente_nombre'] : 'Público General';
                $metodoAbonoDB = ($metodoPago === 'Terminal') ? 'Tarjeta' : $metodoPago;

                if (isset($item['es_nuevo_apartado']) && $item['es_nuevo_apartado'] == true) {
                    $conn->prepare("UPDATE vitrina SET estado = 'Apartado', cliente_nombre = ?, cliente_telefono = ?, anticipo = ?, saldo_restante = ?, fecha_operacion = NOW() WHERE id = ?")->execute([$cliente_nombre, $item['telefono'] ?? '', $monto_pago, (float)$item['saldo_restante'], $id_equipo]);
                    $descripcionCaja = "Enganche: " . str_replace("Enganche: ", "", $item['nombre']);
                } else {
                    $stmtGet = $conn->prepare("SELECT anticipo, saldo_restante FROM vitrina WHERE id = ?");
                    $stmtGet->execute([$id_equipo]);
                    $equipo = $stmtGet->fetch(PDO::FETCH_ASSOC);
                    if ($equipo) {
                        $nuevo_saldo = max(0, $equipo['saldo_restante'] - $monto_pago);
                        $conn->prepare("UPDATE vitrina SET estado = ?, anticipo = ?, saldo_restante = ?, fecha_operacion = NOW() WHERE id = ?")->execute([($nuevo_saldo == 0) ? 'Vendido' : 'Apartado', $equipo['anticipo'] + $monto_pago, $nuevo_saldo, $id_equipo]);
                    }
                    $descripcionCaja = "Abono Vitrina: " . str_replace("Abono: ", "", $item['nombre']);
                }
                $conn->prepare("INSERT INTO abonos_apartados (id_apartado, monto, metodo_pago, fecha_abono, id_usuario) VALUES (?, ?, ?, NOW(), (SELECT id FROM usuarios WHERE nombre = ? LIMIT 1))")->execute([$id_equipo, $monto_pago, $metodoAbonoDB, $usuario]);
                $procesarCobroCaja('INGRESO', $id_equipo, $descripcionCaja, 1, $monto_pago, $cliente_nombre, 'Abono');
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'id_transaccion' => $idTx, 'ticketUrl' => '/local3M/generar_ticket_venta.php?id_transaccion=' . urlencode($idTx) . '&paga_con=' . urlencode($pagaCon)]);
        exit();
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
?>