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

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. OBTENER REPORTE DEL DÍA (KPIs y Tabla) ---
    if ($action === 'reporte_dia') {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $usuario = $_GET['usuario'] ?? '';

        // Filtros base
        $where = "DATE(fecha) = :fecha AND COALESCE(categoria, '') != 'Retiro'";
        $params = [':fecha' => $fecha];

        if (!empty($usuario) && $usuario !== 'Todos') {
            $where .= " AND usuario = :usuario";
            $params[':usuario'] = $usuario;
        }

        // 1.1 Totales Generales
        $sqlTot = "SELECT 
                    COALESCE(SUM(ingreso), 0) as ingreso, 
                    COALESCE(SUM(egreso), 0) as egreso
                   FROM vw_caja_unificada 
                   WHERE $where";
        $stmt = $conn->prepare($sqlTot);
        $stmt->execute($params);
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totales['neto'] = $totales['ingreso'] - $totales['egreso'];

        // 1.2 Lista de Movimientos
        $sqlList = "SELECT * FROM vw_caja_unificada 
                    WHERE $where 
                    ORDER BY fecha DESC"; // Más recientes primero
        $stmtList = $conn->prepare($sqlList);
        $stmtList->execute($params);
        $movimientos = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        // 1.3 Estado de la Caja Actual (Independiente de la fecha del reporte)
        $estadoCaja = obtenerEstadoCaja($conn);

        echo json_encode([
            'success' => true,
            'totales' => $totales,
            'movimientos' => $movimientos,
            'estado_caja' => $estadoCaja
        ]);
        exit();
    }

    // --- 2. REGISTRAR GASTO / INGRESO EXTRA ---
    if ($action === 'registrar_movimiento') {
        $tipo = $_POST['tipo']; // 'GASTO' o 'INGRESO'
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        $categoria = $_POST['categoria'] ?? 'General';

        if ($monto <= 0 || empty($descripcion)) {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            exit();
        }

        // Generar ID transacción simple para gastos
        $idTx = ($tipo === 'GASTO' ? 'GAS' : 'ING') . date('ymdHi') . rand(10,99);
        
        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'GASTO') ? $monto : 0;

        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idTx, $tipo, $descripcion, $monto, $ingreso, $egreso, $_SESSION['nombre'], $categoria]);

        echo json_encode(['success' => true]);
        exit();
    }

    // --- 3. OBTENER LISTA DE USUARIOS (Para filtro) ---
    if ($action === 'usuarios') {
        $stmt = $conn->query("SELECT DISTINCT usuario FROM caja_movimientos ORDER BY usuario");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Función auxiliar para calcular cuánto dinero hay FÍSICAMENTE en caja ahora
function obtenerEstadoCaja($conn) {
    // Buscar última apertura abierta
    $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($caja) {
        // Caja ABIERTA: Saldo Inicial + Ingresos - Egresos (desde la hora de apertura)
        $fechaApertura = $caja['fecha_apertura'];
        
        $sql = "SELECT 
                    COALESCE(SUM(ingreso), 0) as ing, 
                    COALESCE(SUM(egreso), 0) as egr 
                FROM vw_caja_unificada 
                WHERE fecha >= ?";
        $stmtCalc = $conn->prepare($sql);
        $stmtCalc->execute([$fechaApertura]);
        $movs = $stmtCalc->fetch(PDO::FETCH_ASSOC);

        $actual = (float)$caja['saldo_inicial'] + $movs['ing']; // Solo sumamos ingresos al arqueo teórico
        // Nota: Los egresos se restan visualmente, pero el "Fondo" suele contarse diferente. 
        // Para simplificar: Dinero en Caja = Inicial + Entradas - Salidas
        $enCaja = (float)$caja['saldo_inicial'] + $movs['ing'] - $movs['egr'];

        return [
            'estado' => 'ABIERTA',
            'usuario' => $caja['usuario_apertura'],
            'monto_actual' => $enCaja,
            'inicio' => $caja['fecha_apertura']
        ];
    } else {
        // Caja CERRADA
        return ['estado' => 'CERRADA', 'monto_actual' => 0];
    }
}
?>