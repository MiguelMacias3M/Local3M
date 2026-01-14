<?php
session_start();
date_default_timezone_set('America/Mexico_City'); 
ini_set('display_errors', 0); 
error_reporting(E_ALL); 
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Verificar sesión
    if (!isset($_SESSION['nombre'])) {
        throw new Exception('No autorizado');
    }

    // 2. Verificar conexión
    if (!file_exists('../config/conexion.php')) {
        throw new Exception('Falta conexión');
    }
    include '../config/conexion.php';
    
    // 3. Forzar UTF-8 en conexión a BD
    if (isset($conn)) {
        try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {}
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? null);

    // ==========================================
    // 1. OBTENER FECHA SERVIDOR
    // ==========================================
    if ($action === 'fecha_servidor') {
        echo json_encode([
            'success' => true, 
            'fecha' => date('Y-m-d'),
            'fecha_hora' => date('Y-m-d H:i:s')
        ]);
        exit();
    }

    // ==========================================
    // 2. REPORTES (DÍA O RANGO)
    // ==========================================
    if ($action === 'reporte_dia' || $action === 'reporte_rango') {
        $inicio = $_GET['inicio'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $fin    = $_GET['fin']    ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $usuario = $_GET['usuario'] ?? '';

        // Consulta base
        $sql = "SELECT * FROM caja_movimientos WHERE DATE(fecha) BETWEEN :inicio AND :fin ORDER BY fecha DESC";
        $params = [':inicio' => $inicio, ':fin' => $fin];
        
        // Filtro opcional por usuario
        if (!empty($usuario) && $usuario !== 'Todos') {
            $sql = "SELECT * FROM caja_movimientos WHERE DATE(fecha) BETWEEN :inicio AND :fin AND usuario = :u ORDER BY fecha DESC";
            $params[':u'] = $usuario;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesamiento de Totales
        $ingresoTotal = 0;
        $egresoTotal  = 0;

        foreach ($movs as $m) {
            $valIngreso = (float)$m['ingreso'];
            $valEgreso  = (float)$m['egreso'];

            // Sumar ingresos si la columna tiene valor > 0
            if ($valIngreso > 0) {
                $ingresoTotal += $valIngreso;
            }
            // Sumar egresos si la columna tiene valor > 0
            if ($valEgreso > 0) {
                $egresoTotal += $valEgreso;
            }
        }

        $estadoCaja = obtenerEstadoCaja($conn);

        // Respuesta JSON
        $response = [
            'success' => true,
            'totales' => [
                'ingreso' => $ingresoTotal, 
                'egreso' => $egresoTotal, 
                'neto' => $ingresoTotal - $egresoTotal
            ],
            'movimientos' => $movs,
            'estado_caja' => $estadoCaja
        ];

        // Blindaje UTF-8 para JSON
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            array_walk_recursive($response, function(&$item) {
                if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) {
                    $item = utf8_encode($item);
                }
            });
            echo json_encode($response);
        }
        exit();
    }

    // ==========================================
    // 3. REGISTRAR MOVIMIENTO MANUAL
    // ==========================================
    if ($action === 'registrar_movimiento') {
        $tipoBase = $_POST['tipo']; // 'INGRESO' o 'GASTO'
        $desc = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        $cat = $_POST['categoria'] ?? 'General';

        // Validaciones
        if ($monto <= 0 || empty($desc)) {
            throw new Exception('Datos inválidos');
        }

        // Ajuste de Tipo si es Retiro
        $tipoFinal = $tipoBase; 
        if ($tipoBase === 'GASTO' && stripos($cat, 'Retiro') !== false) {
            $tipoFinal = 'RETIRO';
        }

        // Generar ID de transacción
        $prefijo = ($tipoFinal === 'RETIRO') ? 'RET' : substr($tipoBase, 0, 3);
        $idTx = $prefijo . date('ymdHi') . rand(10,99);
        
        $ing = ($tipoBase === 'INGRESO') ? $monto : 0;
        $egr = ($tipoBase === 'INGRESO') ? 0 : $monto;

        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $idTx, 
            $tipoFinal, 
            $desc, 
            $monto, 
            $ing, 
            $egr, 
            $_SESSION['nombre'], 
            date('Y-m-d H:i:s'), 
            $cat
        ]);

        echo json_encode(['success' => true]);
        exit();
    }

    // ==========================================
    // 4. LISTAR USUARIOS
    // ==========================================
    if ($action === 'usuarios') {
        $stmt = $conn->query("SELECT DISTINCT usuario FROM caja_movimientos ORDER BY usuario");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode(['success' => true, 'data' => $users], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            array_walk($users, function(&$item) {
                if (!mb_detect_encoding($item, 'UTF-8', true)) $item = utf8_encode($item);
            });
            echo json_encode(['success' => true, 'data' => $users]);
        }
        exit();
    }
    
    // ==========================================
    // 5. INFO PARA CIERRE DE CAJA
    // ==========================================
    if ($action === 'info_cierre') {
        $estado = obtenerEstadoCaja($conn);
        // Formatear fecha para mostrar
        if($estado['estado'] === 'ABIERTA') {
            $estado['inicio'] = date('d/m/Y h:i A', strtotime($estado['inicio']));
        }
        echo json_encode($estado);
        exit();
    }

    // ==========================================
    // 6. ABRIR CAJA
    // ==========================================
    if ($action === 'abrir_caja') {
        $estado = obtenerEstadoCaja($conn);
        if ($estado['estado'] === 'ABIERTA') throw new Exception('La caja ya está abierta');

        $montoInicial = (float)$_POST['monto_inicial'];
        $usuario = $_SESSION['nombre'];
        $fecha = date('Y-m-d H:i:s');

        $sql = "INSERT INTO caja_cierres (fecha_apertura, usuario_apertura, saldo_inicial, estado) VALUES (?, ?, ?, 'ABIERTA')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$fecha, $usuario, $montoInicial]);

        echo json_encode(['success' => true]);
        exit();
    }

    // ==========================================
    // 7. CERRAR CAJA
    // ==========================================
    if ($action === 'cerrar_caja') {
        // Buscar turno abierto
        $stmt = $conn->query("SELECT id, saldo_inicial, fecha_apertura FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$caja) throw new Exception('No hay una caja abierta para cerrar');

        // Calcular saldos del sistema
        // MODIFICADO: Usamos la misma lógica que en obtenerEstadoCaja
        // Solo sumamos ingresos y restamos retiros si es necesario, pero IGNORAMOS gastos comunes
        // para el cálculo de "efectivo esperado".
        $sqlCalc = "SELECT 
                    COALESCE(SUM(ingreso), 0) as total_ingresos, 
                    COALESCE(SUM(egreso), 0) as total_egresos 
                    FROM caja_movimientos 
                    WHERE fecha >= ?";
        
        // Pero espera, necesitamos filtrar qué egresos sí restan (RETIROS) y cuáles no (GASTOS)
        // La consulta SQL anterior suma TODO egreso. Vamos a hacerlo más fino.
        
        $sqlMovs = "SELECT tipo, ingreso, egreso FROM caja_movimientos WHERE fecha >= ?";
        $stmtMovs = $conn->prepare($sqlMovs);
        $stmtMovs->execute([$caja['fecha_apertura']]);
        $movimientos = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

        $totalIngresos = 0;
        $totalRetiros = 0;

        foreach ($movimientos as $m) {
            $totalIngresos += (float)$m['ingreso'];
            
            // LÓGICA DE NEGOCIO:
            // Solo restamos del efectivo en caja si es un RETIRO explícito.
            // Los GASTOS se registran pero NO restan del efectivo esperado (según tu petición).
            if ($m['tipo'] === 'RETIRO') {
                $totalRetiros += (float)$m['egreso'];
            }
        }

        // Saldo esperado = Inicial + Ingresos - Retiros (Ignorando Gastos)
        $saldoSistema = (float)$caja['saldo_inicial'] + $totalIngresos - $totalRetiros;
        
        // Recibir datos del usuario
        $montoReal = (float)$_POST['monto_final_real'];
        $notas = $_POST['notas'] ?? '';
        $retirar = $_POST['retirar_fondos'] ?? 'NO';
        $diferencia = $montoReal - $saldoSistema;
        $usuarioCierre = $_SESSION['nombre'];
        $fechaCierre = date('Y-m-d H:i:s');

        // Actualizar registro
        $sqlCierre = "UPDATE caja_cierres SET 
                      fecha_cierre = ?, usuario_cierre = ?, 
                      monto_final_sistema = ?, monto_final_real = ?, 
                      diferencia = ?, notas = ?, estado = 'CERRADA' 
                      WHERE id = ?";
        $stmtCierre = $conn->prepare($sqlCierre);
        $stmtCierre->execute([$fechaCierre, $usuarioCierre, $saldoSistema, $montoReal, $diferencia, $notas, $caja['id']]);

        // Retiro automático si se seleccionó
        if ($retirar === 'SI' && $montoReal > 0) {
            $idTx = 'RET-CIERRE-' . date('ymdHi');
            $sqlRetiro = "INSERT INTO caja_movimientos 
                          (id_transaccion, tipo, descripcion, monto_unitario, egreso, usuario, fecha, categoria) 
                          VALUES (?, 'RETIRO', 'Retiro por Cierre de Caja (Auto)', ?, ?, ?, ?, 'Cierre')";
            $stmtRet = $conn->prepare($sqlRetiro);
            $stmtRet->execute([$idTx, $montoReal, $montoReal, $usuarioCierre, $fechaCierre]);
        }

        echo json_encode(['success' => true]);
        exit();
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

// ==========================================
// FUNCIÓN AUXILIAR: ESTADO CAJA (LÓGICA MODIFICADA)
// ==========================================
function obtenerEstadoCaja($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja) {
            $fechaApertura = $caja['fecha_apertura'];
            
            // Obtenemos todos los movimientos desde la apertura
            $sql = "SELECT tipo, ingreso, egreso FROM caja_movimientos WHERE fecha >= ?";
            $stmtCalc = $conn->prepare($sql);
            $stmtCalc->execute([$fechaApertura]);
            $movs = $stmtCalc->fetchAll(PDO::FETCH_ASSOC);

            $totalIngresos = 0;
            $totalRetiros = 0;

            foreach ($movs as $m) {
                $totalIngresos += (float)$m['ingreso'];
                
                // AQUÍ ESTÁ EL CAMBIO CLAVE:
                // Solo restamos si el tipo es 'RETIRO'.
                // Los tipos 'GASTO' se ignoran para el cálculo de efectivo en caja.
                if ($m['tipo'] === 'RETIRO') {
                    $totalRetiros += (float)$m['egreso'];
                }
            }

            // Saldo en Caja = Inicial + Ingresos - Retiros
            $enCaja = (float)$caja['saldo_inicial'] + $totalIngresos - $totalRetiros;

            return [
                'estado' => 'ABIERTA',
                'usuario' => $caja['usuario_apertura'],
                'monto_actual' => $enCaja,
                'inicio' => $caja['fecha_apertura'],
                'id' => $caja['id']
            ];
        } else {
            return ['estado' => 'CERRADA', 'monto_actual' => 0];
        }
    } catch (Throwable $e) {
        return ['estado' => 'ERROR', 'monto_actual' => 0];
    }
}
?>