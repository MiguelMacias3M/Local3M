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
        
        // CORRECCIÓN: Usamos :q1 y :q2 para evitar el error "Invalid parameter number"
        // Algunos servidores exigen un nombre único por cada "?" o placeholder.
        $sql = "SELECT * FROM productos WHERE 
                (LOWER(nombre_producto) LIKE :q1 OR CAST(codigo_barras AS CHAR) LIKE :q2) 
                AND cantidad_piezas > 0 
                ORDER BY nombre_producto ASC LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        $term = '%' . strtolower($q) . '%';
        
        // Enviamos el mismo término dos veces con nombres diferentes
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

    // --- 3. AGREGAR AL CARRITO ---
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

        // Buscar si ya existe en el carrito
        $indexEncontrado = -1;
        foreach ($_SESSION['carrito_venta'] as $key => $item) {
            if ($item['id'] == $id) {
                $indexEncontrado = $key;
                break;
            }
        }

        if ($indexEncontrado >= 0) {
            // Ya existe: Sumar cantidad
            $nuevaCantidad = $_SESSION['carrito_venta'][$indexEncontrado]['cantidad'] + $cantidad;
            
            if ($nuevaCantidad > $prod['cantidad_piezas']) {
                echo json_encode(['success' => false, 'error' => 'Stock insuficiente (Max: ' . $prod['cantidad_piezas'] . ')']);
                exit();
            }
            $_SESSION['carrito_venta'][$indexEncontrado]['cantidad'] = $nuevaCantidad;
        } else {
            // Nuevo item
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
        if (isset($_SESSION['carrito_venta'][$index])) {
            array_splice($_SESSION['carrito_venta'], $index, 1);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 5. LIMPIAR CARRITO ---
    if ($action === 'limpiar') {
        $_SESSION['carrito_venta'] = [];
        echo json_encode(['success' => true]);
        exit();
    }

    // --- 6. FINALIZAR VENTA ---
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
            // Descontar Stock
            $stmtUpdate->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
            if ($stmtUpdate->rowCount() === 0) {
                throw new Exception("Stock insuficiente para: " . $item['nombre']);
            }

            // Registrar Venta
            $stmtVenta->execute([$item['id'], $item['cantidad'], $idTx, $usuario]);

            $totalVenta += ($item['precio'] * $item['cantidad']);
        }

        // Registrar en Caja
        // AQUI ESTÁ LA CATEGORÍA 'Venta' QUE PEDISTE
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

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>