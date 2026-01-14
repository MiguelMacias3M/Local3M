<?php
session_start();
date_default_timezone_set('America/Mexico_City'); 
ini_set('display_errors', 0); 
error_reporting(E_ALL); 
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['nombre'])) throw new Exception('No autorizado');
    if (!file_exists('../config/conexion.php')) throw new Exception('Falta conexión');
    include '../config/conexion.php';
    if (isset($conn)) $conn->exec("SET NAMES 'utf8'");

    $action = $_GET['action'] ?? ($_POST['action'] ?? null);

    // --- FECHA SERVIDOR ---
    if ($action === 'fecha_servidor') {
        echo json_encode(['success' => true, 'fecha' => date('Y-m-d')]);
        exit();
    }

    // --- REPORTES (FILTRADO POR ORIGEN = 'CAJA') ---
    if ($action === 'reporte_dia' || $action === 'reporte_rango') {
        $inicio = $_GET['inicio'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $fin    = $_GET['fin']    ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $usuario = $_GET['usuario'] ?? '';

        $sql = "SELECT * FROM caja_movimientos 
                WHERE DATE(fecha) BETWEEN :inicio AND :fin 
                AND origen = 'CAJA' 
                ORDER BY fecha DESC";
        $params = [':inicio' => $inicio, ':fin' => $fin];
        
        if (!empty($usuario) && $usuario !== 'Todos') {
            $sql .= " AND usuario = :u";
            $params[':u'] = $usuario;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- CÁLCULO DE KPI's (INDICADORES VISUALES) ---
        $ingresoTotal = 0; // Ventas + Ingresos Extras
        $gastoTotal  = 0;  // Solo Gastos Reales (NO Retiros)

        foreach ($movs as $m) {
            $valIngreso = (float)$m['ingreso'];
            $valEgreso  = (float)$m['egreso'];
            $tipo = strtoupper($m['tipo']);

            if ($valIngreso > 0) {
                $ingresoTotal += $valIngreso;
            }
            
            // CORRECCIÓN: Solo sumamos a "Gastos" si NO es un Retiro ni Cierre
            // Los retiros afectan el efectivo en caja, pero no son un gasto contable en el reporte rápido
            if ($valEgreso > 0 && $tipo !== 'RETIRO' && $tipo !== 'CIERRE') {
                $gastoTotal += $valEgreso;
            }
        }

        $estadoCaja = obtenerEstadoCaja($conn);

        $resp = [
            'success' => true,
            'totales' => [
                'ingreso' => $ingresoTotal, 
                'egreso' => $gastoTotal, // Aquí enviamos solo gastos reales
                'neto' => $ingresoTotal - $gastoTotal
            ],
            'movimientos' => $movs,
            'estado_caja' => $estadoCaja
        ];

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode($resp, JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            array_walk_recursive($resp, function(&$i) { if(is_string($i) && !mb_detect_encoding($i,'UTF-8',true)) $i = utf8_encode($i); });
            echo json_encode($resp);
        }
        exit();
    }

    // --- REGISTRAR MOVIMIENTO MANUAL ---
    if ($action === 'registrar_movimiento') {
        $tipoBase = $_POST['tipo']; 
        $desc = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        $cat = $_POST['categoria'] ?? 'General';

        if ($monto <= 0 || empty($desc)) throw new Exception('Datos inválidos');

        $tipoFinal = $tipoBase; 
        // Si la categoría dice Retiro, forzamos el tipo RETIRO
        if ($tipoBase === 'GASTO' && stripos($cat, 'Retiro') !== false) $tipoFinal = 'RETIRO';

        $idTx = ($tipoFinal === 'RETIRO' ? 'RET' : substr($tipoBase,0,3)) . date('ymdHi') . rand(10,99);
        $ing = ($tipoBase === 'INGRESO') ? $monto : 0;
        $egr = ($tipoBase === 'INGRESO') ? 0 : $monto;

        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria, origen) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 'CAJA')";
        
        $conn->prepare($sql)->execute([$idTx, $tipoFinal, $desc, $monto, $ing, $egr, $_SESSION['nombre'], date('Y-m-d H:i:s'), $cat]);

        echo json_encode(['success' => true]);
        exit();
    }
    
    // --- USUARIOS ---
    if ($action === 'usuarios') {
        $stmt = $conn->query("SELECT DISTINCT usuario FROM caja_movimientos WHERE origen='CAJA' ORDER BY usuario");
        echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_COLUMN)]);
        exit();
    }
    
    // --- INFO CIERRE ---
    if ($action === 'info_cierre') {
        $estado = obtenerEstadoCaja($conn);
        if($estado['estado'] === 'ABIERTA') $estado['inicio'] = date('d/m/Y h:i A', strtotime($estado['inicio']));
        echo json_encode($estado);
        exit();
    }

    // --- ABRIR CAJA ---
    if ($action === 'abrir_caja') {
        $estado = obtenerEstadoCaja($conn);
        if ($estado['estado'] === 'ABIERTA') throw new Exception('La caja ya está abierta');
        
        $sql = "INSERT INTO caja_cierres (fecha_apertura, usuario_apertura, saldo_inicial, estado) VALUES (?, ?, ?, 'ABIERTA')";
        $conn->prepare($sql)->execute([date('Y-m-d H:i:s'), $_SESSION['nombre'], (float)$_POST['monto_inicial']]);
        echo json_encode(['success' => true]);
        exit();
    }

    // --- CERRAR CAJA ---
    if ($action === 'cerrar_caja') {
        $stmt = $conn->query("SELECT id, saldo_inicial, fecha_apertura FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$caja) throw new Exception('No hay caja abierta');

        // CALCULO SISTEMA: Inicial + Ingresos - (Retiros + Cierres previos)
        // NOTA: Aquí SÍ restamos los retiros del saldo esperado, porque ese dinero ya no está.
        $sqlCalc = "SELECT tipo, ingreso, egreso FROM caja_movimientos WHERE fecha >= ? AND origen = 'CAJA'";
        $stmtMovs = $conn->prepare($sqlCalc);
        $stmtMovs->execute([$caja['fecha_apertura']]);
        $movs = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

        $totalIng = 0; 
        $totalRet = 0;
        
        foreach ($movs as $m) {
            $totalIng += (float)$m['ingreso'];
            // Restamos RETIROS y también CIERRES parciales si existieran
            $t = strtoupper($m['tipo']);
            if ($t === 'RETIRO' || $t === 'CIERRE') {
                $totalRet += (float)$m['egreso'];
            }
        }
        
        $saldoSistema = (float)$caja['saldo_inicial'] + $totalIng - $totalRet;
        
        $montoReal = (float)$_POST['monto_final_real'];
        $retirar = $_POST['retirar_fondos'] ?? 'NO';
        
        $sqlCierre = "UPDATE caja_cierres SET fecha_cierre=?, usuario_cierre=?, monto_final_sistema=?, monto_final_real=?, diferencia=?, notas=?, estado='CERRADA' WHERE id=?";
        $conn->prepare($sqlCierre)->execute([date('Y-m-d H:i:s'), $_SESSION['nombre'], $saldoSistema, $montoReal, $montoReal-$saldoSistema, $_POST['notas']??'', $caja['id']]);

        // AQUÍ EL CAMBIO: Guardamos como 'CIERRE'
        if ($retirar === 'SI' && $montoReal > 0) {
            $sqlRet = "INSERT INTO caja_movimientos 
                       (id_transaccion, tipo, descripcion, monto_unitario, egreso, usuario, fecha, categoria, origen) 
                       VALUES (?, 'CIERRE', 'Retiro por Cierre (Auto)', ?, ?, ?, ?, 'Cierre', 'CAJA')";
            
            $conn->prepare($sqlRet)->execute(['RET-CIERRE-'.date('ymdHi'), $montoReal, $montoReal, $_SESSION['nombre'], date('Y-m-d H:i:s')]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function obtenerEstadoCaja($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($caja) {
            $sql = "SELECT tipo, ingreso, egreso FROM caja_movimientos WHERE fecha >= ? AND origen = 'CAJA'";
            $stmt2 = $conn->prepare($sql); $stmt2->execute([$caja['fecha_apertura']]);
            $movs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            $totI = 0; 
            $totR = 0;
            foreach($movs as $m) {
                $totI += (float)$m['ingreso'];
                // SOLO RESTAMOS RETIROS DEL EFECTIVO EN CAJA
                if($m['tipo']==='RETIRO') $totR += (float)$m['egreso'];
            }
            return ['estado'=>'ABIERTA', 'usuario'=>$caja['usuario_apertura'], 'monto_actual'=>$caja['saldo_inicial']+$totI-$totR, 'inicio'=>$caja['fecha_apertura'], 'id'=>$caja['id']];
        }
        return ['estado'=>'CERRADA', 'monto_actual'=>0];
    } catch (Throwable $e) { return ['estado'=>'ERROR', 'monto_actual'=>0]; }
}
?>