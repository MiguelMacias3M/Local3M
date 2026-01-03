<?php
session_start();
// 1. Configuración de errores para depuración
ini_set('display_errors', 0); // En producción 0, pero si sigue fallando cámbialo a 1 temporalmente
error_reporting(E_ALL); 
header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar sesión
    if (!isset($_SESSION['nombre'])) {
        throw new Exception('No autorizado');
    }

    // Verificar archivo de conexión
    if (!file_exists('../config/conexion.php')) {
        throw new Exception('Falta el archivo de conexión');
    }
    include '../config/conexion.php';

    // Validar conexión
    if (!isset($conn)) {
        throw new Exception('La conexión a la base de datos falló');
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? null);

    // --- 1. REPORTES (RANGO O DÍA) ---
    if ($action === 'reporte_dia' || $action === 'reporte_rango') {
        
        $inicio = $_GET['inicio'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $fin    = $_GET['fin']    ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $usuario = $_GET['usuario'] ?? '';

        // CONSULTA
        $sql = "SELECT * FROM caja_movimientos 
                WHERE DATE(fecha) BETWEEN :inicio AND :fin 
                ORDER BY fecha DESC";
        
        $params = [':inicio' => $inicio, ':fin' => $fin];
        
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

        // PROCESAMIENTO
        $ingresoTotal = 0;
        $egresoTotal  = 0;
        $movimientosFiltrados = []; 

        foreach ($todosLosMovimientos as $m) {
            $tipo = strtoupper(trim($m['tipo']));
            // Manejo seguro de 'categoria' por si viene null
            $categoria = isset($m['categoria']) ? ucwords(strtolower(trim($m['categoria']))) : '';

            // Detectar Retiros
            $esRetiro = ($tipo === 'RETIRO') || 
                        ($categoria === 'Retiro') || 
                        ($categoria === 'Retiro De Efectivo') || 
                        ($categoria === 'Cierre');

            if (!$esRetiro) {
                $ingresoTotal += (float)$m['ingreso'];
                $egresoTotal  += (float)$m['egreso'];
            }

            $movimientosFiltrados[] = $m;
        }

        $totales = [
            'ingreso' => $ingresoTotal,
            'egreso'  => $egresoTotal,
            'neto'    => $ingresoTotal - $egresoTotal
        ];

        // Estado de caja
        $estadoCaja = obtenerEstadoCaja($conn);

        // RESPUESTA JSON SEGURA
        echo json_encode([
            'success' => true,
            'totales' => $totales,
            'movimientos' => $movimientosFiltrados,
            'estado_caja' => $estadoCaja
        ], JSON_INVALID_UTF8_SUBSTITUTE); // Evita errores por acentos
        exit();
    }

    // --- 2. REGISTRAR MOVIMIENTO MANUAL ---
    if ($action === 'registrar_movimiento') {
        $tipo = $_POST['tipo'];
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        $categoria = $_POST['categoria'] ?? 'General';

        if ($monto <= 0 || empty($descripcion)) {
            throw new Exception('Datos inválidos');
        }

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
        echo json_encode(['success' => true, 'data' => $users], JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

} catch (Throwable $e) { // Captura Exception Y Error (PHP 7+)
    // Si algo falla, devuelve JSON con el error real
    http_response_code(500); // Opcional: marca error de servidor
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

// Función auxiliar
function obtenerEstadoCaja($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja) {
            $fechaApertura = $caja['fecha_apertura'];
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
    } catch (Throwable $e) {
        return ['estado' => 'ERROR', 'monto_actual' => 0];
    }
}
?>