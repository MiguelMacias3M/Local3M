<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

try {
    if (isset($conn)) $conn->exec("SET NAMES 'utf8'");
} catch (Exception $e) {}

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // 1. LISTAR O BUSCAR
    if ($action === 'listar') {
        $q = $_GET['q'] ?? '';
        $term = "%" . strtolower($q) . "%";
        
        $sql = "SELECT * FROM vitrina 
                WHERE 
                LOWER(imei_serie) LIKE ? OR 
                LOWER(marca) LIKE ? OR 
                LOWER(modelo) LIKE ? OR 
                LOWER(cliente_nombre) LIKE ?
                ORDER BY id DESC LIMIT 100";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([$term, $term, $term, $term]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    // 2. GUARDAR NUEVO EQUIPO
    if ($action === 'guardar_equipo') {
        $id = $_POST['id'] ?? '';
        $tipo = $_POST['tipo'];
        $imei = $_POST['imei_serie'];
        $marca = $_POST['marca'];
        $modelo = $_POST['modelo'];
        $color = $_POST['color'];
        $costo = $_POST['costo'];
        $precio = $_POST['precio_venta'];

        if (empty($id)) {
            $sql = "INSERT INTO vitrina (tipo, imei_serie, marca, modelo, color, costo, precio_venta, estado, fecha_ingreso) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Disponible', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tipo, $imei, $marca, $modelo, $color, $costo, $precio]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    // 3. PROCESAR VENTA
    if ($action === 'vender') {
        $id = $_POST['id'];
        $cliente = $_POST['cliente'];
        $telefono = $_POST['telefono'];

        $sql = "UPDATE vitrina SET 
                estado = 'Vendido', 
                cliente_nombre = ?, 
                cliente_telefono = ?, 
                fecha_operacion = NOW() 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$cliente, $telefono, $id]);
        echo json_encode(['success' => true]);
        exit();
    }

    // 4. PROCESAR APARTADO
    if ($action === 'apartar') {
        $id = $_POST['id'];
        $cliente = $_POST['cliente'];
        $telefono = $_POST['telefono'];
        $anticipo = $_POST['anticipo'];
        $saldo = $_POST['saldo_restante'];

        $sql = "UPDATE vitrina SET 
                estado = 'Apartado', 
                cliente_nombre = ?, 
                cliente_telefono = ?, 
                anticipo = ?, 
                saldo_restante = ?, 
                fecha_operacion = NOW() 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$cliente, $telefono, $anticipo, $saldo, $id]);
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


// 5. PROCESAR ABONO
    if ($action === 'abonar') {
        $id = $_POST['id'];
        $abono = (float)$_POST['abono'];
        
        // 1. Obtenemos el registro actual del equipo
        $stmt = $conn->prepare("SELECT anticipo, saldo_restante FROM vitrina WHERE id = ?");
        $stmt->execute([$id]);
        $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$equipo) throw new Exception("Equipo no encontrado en la vitrina");

        // 2. Calculamos los nuevos totales
        $nuevo_anticipo = $equipo['anticipo'] + $abono;
        $nuevo_saldo = $equipo['saldo_restante'] - $abono;
        
        // 3. Verificamos si el cliente ya liquidó
        if ($nuevo_saldo <= 0) {
            $nuevo_saldo = 0;
            $estado = 'Vendido'; // Liquidó la deuda, se marca como Vendido
        } else {
            $estado = 'Apartado'; // Sigue debiendo, se mantiene en Apartado
        }

        // 4. Actualizamos la base de datos
        $sql = "UPDATE vitrina SET 
                estado = ?, 
                anticipo = ?, 
                saldo_restante = ?, 
                fecha_operacion = NOW() 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$estado, $nuevo_anticipo, $nuevo_saldo, $id]);
        
        echo json_encode(['success' => true]);
        exit();
    }
?>