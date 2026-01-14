<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City'); 
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';
// Asegurar UTF-8
if (isset($conn)) { try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {} }

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
        
        // Blindaje JSON
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(['success' => true, 'data' => $productos], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            array_walk_recursive($productos, function(&$item) {
                if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) $item = utf8_encode($item);
            });
            echo json_encode(['success' => true, 'data' => $productos]);
        }
        exit();
    }

    // --- 1.5 BUSCAR REPARACIONES (Para cobrar/abonar) ---
    if ($action === 'buscar_reparacion') {
        $q = $_GET['q'] ?? '';
        $sql = "SELECT * FROM reparaciones WHERE 
                (LOWER(nombre_cliente) LIKE :q OR LOWER(modelo) LIKE :q OR id = :qId)
                AND estado NOT IN ('Entregado', 'Cancelado') 
                ORDER BY id DESC LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        $term = '%' . strtolower($q) . '%';
        $termId = ctype_digit($q) ? $q : 0;
        
        $stmt->execute([':q' => $term, ':qId' => $termId]);
        $reparaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(['success' => true, 'data' => $reparaciones], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            array_walk_recursive($reparaciones, function(&$item) {
                if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) $item = utf8_encode($item);
            });
            echo json_encode(['success' => true, 'data' => $reparaciones]);
        }
        exit();
    }

    // --- 2. OBTENER CARRITO ---
    if ($action === 'get_carrito') {
        echo json_encode(['success' => true, 'carrito' => array_values($_SESSION['carrito_venta'])]);
        exit();
    }

    // --- 3. AGREGAR AL CARRITO ---
    if ($action === 'agregar') {
        $tipo = $_POST['tipo_item'] ?? 'producto'; 
        $id = $_POST['id'] ?? null;
        $cantidad = (int)($_POST['cantidad'] ?? 1);
        $montoManual = isset($_POST['monto']) ? (float)$_POST['monto'] : null;

        if (!$id) throw new Exception("ID no válido");

        // Validar duplicados (Reparaciones solo una vez)
        foreach ($_SESSION['carrito_venta'] as $item) {
            if ($item['id'] == $id && $item['tipo'] == $tipo) {
                if ($tipo === 'reparacion') {
                    echo json_encode(['success' => false, 'error' => 'Esta reparación ya está en el cobro']);
                    exit();
                }
            }
        }

        if ($tipo === 'producto') {
            $stmt = $conn->prepare("SELECT * FROM productos WHERE id_productos = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod) throw new Exception('Producto no encontrado');

            $index = -1;
            foreach ($_SESSION['carrito_venta'] as $k => $it) {
                if ($it['id'] == $id && $it['tipo'] == 'producto') { $index = $k; break; }
            }

            if ($index >= 0) {
                $nuevaCant = $_SESSION['carrito_venta'][$index]['cantidad'] + $cantidad;
                if ($nuevaCant > $prod['cantidad_piezas']) throw new Exception('Stock insuficiente');
                $_SESSION['carrito_venta'][$index]['cantidad'] = $nuevaCant;
            } else {
                if ($cantidad > $prod['cantidad_piezas']) throw new Exception('Stock insuficiente');
                $_SESSION['carrito_venta'][] = [
                    'id' => $prod['id_productos'],
                    'nombre' => $prod['nombre_producto'],
                    'precio' => (float)$prod['precio_producto'],
                    'codigo' => $prod['codigo_barras'],
                    'cantidad' => $cantidad,
                    'tipo' => 'producto'
                ];
            }
        } 
        elseif ($tipo === 'reparacion') {
            $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = ?");
            $stmt->execute([$id]);
            $rep = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rep) throw new Exception('Reparación no encontrada');
            
            $saldoPendiente = (float)$rep['costo'] - (float)$rep['abono'];
            if ($saldoPendiente < 0) $saldoPendiente = 0;

            $precioCobrar = 0;
            $nombreItem = "";

            if ($montoManual !== null) {
                if ($montoManual > $saldoPendiente + 0.01) throw new Exception("Monto excede saldo ($$saldoPendiente)");
                $precioCobrar = $montoManual;
                $nombreItem = "Abono Reparación: " . $rep['modelo'];
            } else {
                $precioCobrar = $saldoPendiente;
                $nombreItem = "Liq. Reparación: " . $rep['modelo'];
            }

            $_SESSION['carrito_venta'][] = [
                'id' => $rep['id'],
                'nombre' => $nombreItem . " (" . $rep['nombre_cliente'] . ")",
                'precio' => $precioCobrar,
                'codigo' => 'REP-'.$rep['id'],
                'cantidad' => 1,
                'tipo' => 'reparacion',
                'es_adelanto' => ($montoManual !== null && $montoManual < $saldoPendiente)
            ];
        }

        echo json_encode(['success' => true]);
        exit();
    }

    // --- 4. ELIMINAR / LIMPIAR ---
    if ($action === 'eliminar') {
        $index = $_POST['index'] ?? null;
        array_splice($_SESSION['carrito_venta'], $index, 1);
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'limpiar') {
        $_SESSION['carrito_venta'] = [];
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 6. FINALIZAR VENTA (AQUÍ ESTÁ LA MAGIA) ---
    if ($action === 'finalizar') {
        if (empty($_SESSION['carrito_venta'])) throw new Exception('Carrito vacío');

        $conn->beginTransaction();
        
        $idTx = 'POS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $usuario = $_SESSION['nombre'];
        // Usamos fecha PHP para consistencia total
        $fecha = date('Y-m-d H:i:s'); 
        
        $totalProductos = 0;
        $totalReparaciones = 0;

        $sqlVenta = "INSERT INTO ventas (id_producto, cantidad, id_transaccion, usuario, fecha) VALUES (?, ?, ?, ?, ?)";
        $stmtVenta = $conn->prepare($sqlVenta);

        $sqlUpdateProd = "UPDATE productos SET cantidad_piezas = cantidad_piezas - ? WHERE id_productos = ? AND cantidad_piezas >= ?";
        $stmtUpdateProd = $conn->prepare($sqlUpdateProd);

        $sqlAbonoRep = "UPDATE reparaciones SET abono = abono + ? WHERE id = ?";
        $stmtAbonoRep = $conn->prepare($sqlAbonoRep);

        $sqlEntregarRep = "UPDATE reparaciones SET estado = 'Entregado', fecha_entrega = ? WHERE id = ? AND abono >= costo AND estado IN ('Terminada', 'Listo', 'Reparado')";
        $stmtEntregarRep = $conn->prepare($sqlEntregarRep);

        foreach ($_SESSION['carrito_venta'] as $item) {
            $subtotal = $item['precio'] * $item['cantidad'];

            if ($item['tipo'] === 'producto') {
                $stmtUpdateProd->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
                if ($stmtUpdateProd->rowCount() === 0) throw new Exception("Stock insuficiente: " . $item['nombre']);
                
                $stmtVenta->execute([$item['id'], $item['cantidad'], $idTx, $usuario, $fecha]);
                $totalProductos += $subtotal;

            } elseif ($item['tipo'] === 'reparacion') {
                $stmtAbonoRep->execute([$subtotal, $item['id']]);
                $stmtEntregarRep->execute([$fecha, $item['id']]);
                $totalReparaciones += $subtotal;
            }
        }

        // --- REGISTRO EN CAJA CON 'origen = CAJA' ---
        
        // A) Venta de Productos
        if ($totalProductos > 0) {
            // Nota: Agregamos el campo 'origen' al final y el valor 'CAJA'
            $sqlCajaP = "INSERT INTO caja_movimientos 
                        (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria, origen) 
                        VALUES (?, 'VENTA', 0, 'Venta de Productos (POS)', 1, ?, ?, 0, ?, 'Mostrador', ?, 'Venta', 'CAJA')";
            $stmtCaja = $conn->prepare($sqlCajaP);
            $stmtCaja->execute([$idTx, $totalProductos, $totalProductos, $usuario, $fecha]);
        }

        // B) Cobro de Reparaciones
        if ($totalReparaciones > 0) {
            $descRep = "Abono/Cobro Reparación (POS)";
            $sqlCajaR = "INSERT INTO caja_movimientos 
                        (id_transaccion, tipo, ref_id, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, cliente, fecha, categoria, origen) 
                        VALUES (?, 'REPARACION', 0, ?, 1, ?, ?, 0, ?, 'Cliente', ?, 'Reparación', 'CAJA')";
            $stmtCaja = $conn->prepare($sqlCajaR);
            $stmtCaja->execute([$idTx, $descRep, $totalReparaciones, $totalReparaciones, $usuario, $fecha]);
        }

        $conn->commit();
        $_SESSION['carrito_venta'] = [];
        
        echo json_encode(['success' => true, 'id_transaccion' => $idTx, 'ticketUrl' => 'generar_ticket_venta.php?id_transaccion=' . urlencode($idTx)]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>