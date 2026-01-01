<?php
session_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL); 
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. REPORTES DEL DÍA ---
    if ($action === 'reporte_dia') {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $usuario = $_GET['usuario'] ?? '';

        // CORRECCIÓN DEFINITIVA: Usamos "tipo != 'RETIRO'"
        // Esto excluye tanto los retiros manuales como los automáticos del cierre.
        $where = "DATE(fecha) = :fecha AND tipo != 'RETIRO'";
        $params = [':fecha' => $fecha];

        if (!empty($usuario) && $usuario !== 'Todos') {
            $where .= " AND usuario = :usuario";
            $params[':usuario'] = $usuario;
        }

        // 1.1 Totales (Sin contar retiros)
        try {
            $sqlTot = "SELECT 
                        COALESCE(SUM(ingreso), 0) as ingreso, 
                        COALESCE(SUM(egreso), 0) as egreso
                       FROM caja_movimientos 
                       WHERE $where";
            $stmt = $conn->prepare($sqlTot);
            $stmt->execute($params);
            $totales = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $totales = ['ingreso'=>0, 'egreso'=>0];
        }
        
        $totales['neto'] = $totales['ingreso'] - $totales['egreso'];

        // 1.2 Lista de Movimientos
        // Aquí SÍ mostramos los retiros para que se vean en la tabla, aunque no sumen al gasto.
        // Quitamos el filtro solo para la lista visual.
        $whereLista = "DATE(fecha) = :fecha"; 
        // Si quieres que TAMPOCO aparezcan en la tabla, usa $where en vez de $whereLista
        
        $sqlList = "SELECT * FROM caja_movimientos 
                    WHERE $whereLista 
                    ORDER BY fecha DESC";
        
        // Si hay filtro de usuario, lo aplicamos también aquí
        if (!empty($usuario) && $usuario !== 'Todos') {
            $sqlList = "SELECT * FROM caja_movimientos WHERE $whereLista AND usuario = :usuario ORDER BY fecha DESC";
        }

        $stmtList = $conn->prepare($sqlList);
        $stmtList->execute($params);
        $movimientos = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        $estadoCaja = obtenerEstadoCaja($conn);

        echo json_encode([
            'success' => true,
            'totales' => $totales,
            'movimientos' => $movimientos,
            'estado_caja' => $estadoCaja
        ]);
        exit();
    }

    // --- 2. REGISTRAR MOVIMIENTO MANUAL ---
    if ($action === 'registrar_movimiento') {
        $tipo = $_POST['tipo'];
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        $categoria = $_POST['categoria'] ?? 'General';

        if ($monto <= 0 || empty($descripcion)) {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            exit();
        }

        // Si la categoría es Retiro, forzamos el tipo 'RETIRO'
        if ($tipo === 'GASTO' && (stripos($categoria, 'Retiro') !== false)) {
            $tipo = 'RETIRO';
        }

        $idTx = ($tipo === 'GASTO' ? 'GAS' : ($tipo === 'RETIRO' ? 'RET' : 'ING')) . date('ymdHi') . rand(10,99);
        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'INGRESO') ? 0 : $monto;

        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idTx, $tipo, $descripcion, $monto, $ingreso, $egreso, $_SESSION['nombre'], $categoria]);

        echo json_encode(['success' => true]);
        exit();
    }

    // --- 3. USUARIOS ---
    if ($action === 'usuarios') {
        $stmt = $conn->query("SELECT DISTINCT usuario FROM caja_movimientos ORDER BY usuario");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function obtenerEstadoCaja($conn) {
    $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($caja) {
        $fechaApertura = $caja['fecha_apertura'];
        // Aquí SÍ restamos los retiros porque el dinero ya no está en el cajón físico
        $sql = "SELECT COALESCE(SUM(ingreso), 0) as ing, COALESCE(SUM(egreso), 0) as egr 
                FROM caja_movimientos WHERE fecha >= ?";
        $stmtCalc = $conn->prepare($sql);
        $stmtCalc->execute([$fechaApertura]);
        $movs = $stmtCalc->fetch(PDO::FETCH_ASSOC);

        $enCaja = (float)$caja['saldo_inicial'] + $movs['ing'] - $movs['egr'];

        return [
            'estado' => 'ABIERTA',
            'usuario' => $caja['usuario_apertura'],
            'monto_actual' => $enCaja,
            'inicio' => $caja['fecha_apertura']
        ];
    } else {
        return ['estado' => 'CERRADA', 'monto_actual' => 0];
    }
}