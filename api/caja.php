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
    if (isset($conn)) { try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {} }

    $action = $_GET['action'] ?? ($_POST['action'] ?? null);

    // ... (El bloque de fecha_servidor queda igual) ...
    if ($action === 'fecha_servidor') {
        echo json_encode(['success' => true, 'fecha' => date('Y-m-d'), 'fecha_hora' => date('Y-m-d H:i:s')]);
        exit();
    }

    // ==========================================
    // 2. REPORTES (CORREGIDO PARA NO AFECTAR BALANCE)
    // ==========================================
    if ($action === 'reporte_dia' || $action === 'reporte_rango') {
        $inicio = $_GET['inicio'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $fin    = $_GET['fin']    ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $usuario = $_GET['usuario'] ?? '';

        $sql = "SELECT * FROM caja_movimientos WHERE DATE(fecha) BETWEEN :inicio AND :fin ORDER BY fecha DESC";
        $params = [':inicio' => $inicio, ':fin' => $fin];
        
        if (!empty($usuario) && $usuario !== 'Todos') {
            $sql = "SELECT * FROM caja_movimientos WHERE DATE(fecha) BETWEEN :inicio AND :fin AND usuario = :u ORDER BY fecha DESC";
            $params[':u'] = $usuario;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Variables separadas
        $ingresoTotal = 0;
        $gastoTotal   = 0; // Gastos reales (afectan ganancia)
        $retiroTotal  = 0; // Retiros de efectivo (NO afectan ganancia)

        foreach ($movs as $m) {
            $valIngreso = (float)$m['ingreso'];
            $valEgreso  = (float)$m['egreso'];
            $cat        = $m['categoria'] ?? '';
            $desc       = $m['descripcion'] ?? '';
            $tipo       = $m['tipo'] ?? '';

            // Sumar ingresos
            if ($valIngreso > 0) {
                $ingresoTotal += $valIngreso;
            }

            // Clasificar Egresos
            if ($valEgreso > 0) {
                // DETECCIÓN ROBUSTA DE RETIRO (Por si el tipo en BD está vacío)
                $esRetiro = ($tipo === 'RETIRO') || 
                            (stripos($cat, 'Retiro') !== false) || 
                            (stripos($cat, 'Cierre') !== false) ||
                            (stripos($desc, 'Retiro') !== false);

                if ($esRetiro) {
                    $retiroTotal += $valEgreso; // Es retiro, no gasto
                } else {
                    $gastoTotal += $valEgreso;  // Es gasto operativo
                }
            }
        }

        $estadoCaja = obtenerEstadoCaja($conn);

        // Respuesta JSON
        $response = [
            'success' => true,
            'totales' => [
                'ingreso' => $ingresoTotal, 
                'egreso'  => $gastoTotal,      // Solo mostramos gastos reales como egresos del balance
                'retiros' => $retiroTotal,     // Dato informativo extra
                'neto'    => $ingresoTotal - $gastoTotal // Balance correcto: Ingreso - Gasto (sin restar retiros)
            ],
            'movimientos' => $movs,
            'estado_caja' => $estadoCaja
        ];

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            // ... (manejo utf8 anterior)
            echo json_encode($response);
        }
        exit();
    }

    // ... (El resto de acciones: registrar_movimiento, usuarios, etc. quedan igual hasta cerrar_caja) ...
    
    // ==========================================
    // 3. REGISTRAR MOVIMIENTO (Pequeña mejora)
    // ==========================================
    if ($action === 'registrar_movimiento') {
        $tipoBase = $_POST['tipo']; 
        $desc = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        $cat = $_POST['categoria'] ?? 'General';

        if ($monto <= 0 || empty($desc)) throw new Exception('Datos inválidos');

        $tipoFinal = $tipoBase; 
        // Forzamos categoría 'Retiro' si es un retiro manual
        if ($tipoBase === 'GASTO' && stripos($cat, 'Retiro') !== false) {
            $tipoFinal = 'RETIRO';
        }

        // ... (resto del código de inserción igual) ...
        // NOTA: Recuerda ejecutar el comando SQL recomendado abajo para que se guarde 'RETIRO' correctamente.
        
        // ... (código original de inserción)
        $prefijo = ($tipoFinal === 'RETIRO') ? 'RET' : substr($tipoBase, 0, 3);
        $idTx = $prefijo . date('ymdHi') . rand(10,99);
        $ing = ($tipoBase === 'INGRESO') ? $monto : 0;
        $egr = ($tipoBase === 'INGRESO') ? 0 : $monto;

        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idTx, $tipoFinal, $desc, $monto, $ing, $egr, $_SESSION['nombre'], date('Y-m-d H:i:s'), $cat]);
        
        echo json_encode(['success' => true]);
        exit();
    }

    // ... (Bloques de usuarios, info_cierre, abrir_caja se mantienen) ...
    // ... (action usuarios, info_cierre, abrir_caja van aquí igual al original) ...
    
    if ($action === 'usuarios') {
        $stmt = $conn->query("SELECT DISTINCT usuario FROM caja_movimientos ORDER BY usuario");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }
    if ($action === 'info_cierre') {
        echo json_encode(obtenerEstadoCaja($conn));
        exit();
    }
    if ($action === 'abrir_caja') {
        // ... (código original de abrir caja) ...
        $estado = obtenerEstadoCaja($conn);
        if ($estado['estado'] === 'ABIERTA') throw new Exception('La caja ya está abierta');
        $montoInicial = (float)$_POST['monto_inicial'];
        $usuario = $_SESSION['nombre'];
        $sql = "INSERT INTO caja_cierres (fecha_apertura, usuario_apertura, saldo_inicial, estado) VALUES (NOW(), ?, ?, 'ABIERTA')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$usuario, $montoInicial]);
        echo json_encode(['success' => true]);
        exit();
    }

    // ==========================================
    // 7. CERRAR CAJA (Lógica corregida de detección)
    // ==========================================
    if ($action === 'cerrar_caja') {
        $stmt = $conn->query("SELECT id, saldo_inicial, fecha_apertura FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$caja) throw new Exception('No hay una caja abierta');

        $sqlMovs = "SELECT tipo, categoria, descripcion, ingreso, egreso FROM caja_movimientos WHERE fecha >= ?";
        $stmtMovs = $conn->prepare($sqlMovs);
        $stmtMovs->execute([$caja['fecha_apertura']]);
        $movimientos = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

        $totalIngresos = 0;
        $totalRetiros = 0;

        foreach ($movimientos as $m) {
            $totalIngresos += (float)$m['ingreso'];
            $valEgreso = (float)$m['egreso'];
            
            // Detectar Retiros (por Tipo o Categoría)
            // Aquí definimos qué resta dinero de la caja. 
            // Si tus gastos salen de la caja, deberías sumar también los gastos aquí.
            // Asumiendo tu lógica original donde solo 'RETIRO' resta:
            $esRetiro = ($m['tipo'] === 'RETIRO') || 
                        (stripos($m['categoria'], 'Retiro') !== false) || 
                        (stripos($m['categoria'], 'Cierre') !== false);

            if ($esRetiro && $valEgreso > 0) {
                $totalRetiros += $valEgreso;
            }
        }

        $saldoSistema = (float)$caja['saldo_inicial'] + $totalIngresos - $totalRetiros;
        
        $montoReal = (float)$_POST['monto_final_real'];
        $notas = $_POST['notas'] ?? '';
        $retirar = $_POST['retirar_fondos'] ?? 'NO';
        $diferencia = $montoReal - $saldoSistema;
        $usuarioCierre = $_SESSION['nombre'];

        $sqlCierre = "UPDATE caja_cierres SET 
                      fecha_cierre = NOW(), usuario_cierre = ?, 
                      monto_final_sistema = ?, monto_final_real = ?, 
                      diferencia = ?, notas = ?, estado = 'CERRADA' 
                      WHERE id = ?";
        $stmtCierre = $conn->prepare($sqlCierre);
        $stmtCierre->execute([$usuarioCierre, $saldoSistema, $montoReal, $diferencia, $notas, $caja['id']]);

        if ($retirar === 'SI' && $montoReal > 0) {
            $idTx = 'RET-CIERRE-' . date('ymdHi');
            $sqlRetiro = "INSERT INTO caja_movimientos 
                          (id_transaccion, tipo, descripcion, monto_unitario, egreso, usuario, fecha, categoria) 
                          VALUES (?, 'RETIRO', 'Retiro por Cierre de Caja (Auto)', ?, ?, ?, ?, 'Cierre')";
            $stmtRet = $conn->prepare($sqlRetiro);
            $stmtRet->execute([$idTx, $montoReal, $montoReal, $usuarioCierre, date('Y-m-d H:i:s')]);
        }

        echo json_encode(['success' => true]);
        exit();
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

function obtenerEstadoCaja($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja) {
            $sql = "SELECT tipo, categoria, descripcion, ingreso, egreso FROM caja_movimientos WHERE fecha >= ?";
            $stmtCalc = $conn->prepare($sql);
            $stmtCalc->execute([$caja['fecha_apertura']]);
            $movs = $stmtCalc->fetchAll(PDO::FETCH_ASSOC);

            $totalIngresos = 0;
            $totalRetiros = 0;

            foreach ($movs as $m) {
                $totalIngresos += (float)$m['ingreso'];
                $valEgreso = (float)$m['egreso'];

                // Detección robusta de Retiros
                $esRetiro = ($m['tipo'] === 'RETIRO') || 
                            (stripos($m['categoria'], 'Retiro') !== false) || 
                            (stripos($m['categoria'], 'Cierre') !== false);

                // Solo restamos del efectivo si es un retiro (según tu lógica de negocio)
                if ($esRetiro && $valEgreso > 0) {
                    $totalRetiros += $valEgreso;
                }
            }

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