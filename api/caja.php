<?php
session_start();
// Desactivar errores en pantalla para no ensuciar el JSON
ini_set('display_errors', 0); 
error_reporting(0); 
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    // --- 1. REPORTES (RANGO O DÍA) ---
    if ($action === 'reporte_dia' || $action === 'reporte_rango') {
        
        // Manejo flexible de parámetros (soporta ambos modos)
        $inicio = $_GET['inicio'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $fin    = $_GET['fin']    ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $usuario = $_GET['usuario'] ?? '';

        // 1. CONSULTA SQL SIMPLE (Sin comparaciones de texto complejas para evitar error de Collation)
        $sql = "SELECT * FROM caja_movimientos 
                WHERE DATE(fecha) BETWEEN :inicio AND :fin 
                ORDER BY fecha DESC";
        
        $params = [':inicio' => $inicio, ':fin' => $fin];
        
        // Si hay filtro de usuario, lo aplicamos en SQL (es seguro porque es comparación exacta =)
        if (!empty($usuario) && $usuario !== 'Todos') {
            $sql = "SELECT * FROM caja_movimientos 
                    WHERE DATE(fecha) BETWEEN :inicio AND :fin 
                    AND usuario = :usuario 
                    ORDER BY fecha DESC";
            $params[':usuario'] = $usuario;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $todosLosMovimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. PROCESAMIENTO EN PHP (Aquí hacemos los filtros de 'Retiro' para evitar errores SQL)
        $ingresoTotal = 0;
        $egresoTotal  = 0;
        $movimientosFiltrados = []; // Lista para la tabla

        foreach ($todosLosMovimientos as $m) {
            // Normalizar textos para comparación segura
            $tipo = strtoupper(trim($m['tipo']));
            $categoria = isset($m['categoria']) ? ucwords(strtolower(trim($m['categoria']))) : '';

            // Detectar si es un RETIRO (Cierre de caja o Retiro manual)
            // Estos NO deben sumar a los gastos operativos del negocio
            $esRetiro = ($tipo === 'RETIRO') || 
                        ($categoria === 'Retiro') || 
                        ($categoria === 'Retiro De Efectivo') || 
                        ($categoria === 'Cierre');

            if (!$esRetiro) {
                // Solo sumamos si NO es un retiro
                $ingresoTotal += (float)$m['ingreso'];
                $egresoTotal  += (float)$m['egreso'];
            }

            // Agregamos todo a la lista visual (tabla), o puedes filtrar también aquí si prefieres
            $movimientosFiltrados[] = $m;
        }

        $totales = [
            'ingreso' => $ingresoTotal,
            'egreso'  => $egresoTotal,
            'neto'    => $ingresoTotal - $egresoTotal
        ];

        // 3. ESTADO DE CAJA ACTUAL
        $estadoCaja = obtenerEstadoCaja($conn);

        echo json_encode([
            'success' => true,
            'totales' => $totales,
            'movimientos' => $movimientosFiltrados,
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

        // Normalización de Retiros
        if ($tipo === 'GASTO' && stripos($categoria, 'Retiro') !== false) {
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

    // --- 3. LISTA DE USUARIOS ---
    if ($action === 'usuarios') {
        $stmt = $conn->query("SELECT DISTINCT usuario FROM caja_movimientos ORDER BY usuario");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Función auxiliar
function obtenerEstadoCaja($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja) {
            $fechaApertura = $caja['fecha_apertura'];
            // Cálculo directo y simple
            $sql = "SELECT COALESCE(SUM(ingreso), 0) as ing, COALESCE(SUM(egreso), 0) as egr 
                    FROM caja_movimientos WHERE fecha >= ?";
            $stmtCalc = $conn->prepare($sql);
            $stmtCalc->execute([$fechaApertura]);
            $movs = $stmtCalc->fetch(PDO::FETCH_ASSOC);

            // En el arqueo físico, los retiros (egresos) SÍ se restan porque el dinero sale del cajón
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
    } catch (Exception $e) {
        return ['estado' => 'ERROR', 'monto_actual' => 0];
    }
}
?>