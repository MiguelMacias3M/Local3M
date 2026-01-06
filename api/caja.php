<?php
session_start();
date_default_timezone_set('America/Mexico_City'); // Forzar zona horaria de México

// CONFIGURACIÓN DE ERRORES (0 para producción, 1 para debug)
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
        throw new Exception('Falta el archivo de conexión');
    }
    include '../config/conexion.php';

    // 3. Forzar UTF-8 en BD
    if (isset($conn)) {
        try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {}
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? null);

    // ==========================================
    // ACCIÓN: OBTENER FECHA SERVIDOR (Para sincronizar frontend)
    // ==========================================
    if ($action === 'fecha_servidor') {
        echo json_encode([
            'success' => true,
            'fecha' => date('Y-m-d'),            
            'fecha_hora' => date('Y-m-d H:i:s'),
            'zona' => date_default_timezone_get()
        ]);
        exit();
    }

    // ==========================================
    // ACCIÓN: REPORTES (DÍA O RANGO)
    // ==========================================
    if ($action === 'reporte_dia' || $action === 'reporte_rango') {
        
        $inicio = $_GET['inicio'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $fin    = $_GET['fin']    ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $usuario = $_GET['usuario'] ?? '';

        // Consulta Base
        $sql = "SELECT * FROM caja_movimientos 
                WHERE DATE(fecha) BETWEEN :inicio AND :fin 
                ORDER BY fecha DESC";
        
        $params = [':inicio' => $inicio, ':fin' => $fin];
        
        // Filtro por Usuario
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

        // Procesamiento de Totales
        $ingresoTotal = 0;
        $egresoTotal  = 0;
        $movimientosFiltrados = []; 

        foreach ($todosLosMovimientos as $m) {
            $tipo = strtoupper(trim($m['tipo']));
            $categoria = isset($m['categoria']) ? ucwords(strtolower(trim($m['categoria']))) : '';

            // Detectar si es un Retiro (para no sumarlo como gasto operativo común si no se desea,
            // o para manejarlo diferente. Aquí asumimos que Retiro SÍ resta al flujo de caja efectivo).
            
            // Lógica: 
            // Ingreso = Suma
            // Gasto = Resta
            // Retiro = Resta (generalmente)
            
            if ($tipo === 'INGRESO') {
                $ingresoTotal += (float)$m['ingreso'];
            } else {
                // GASTO o RETIRO
                $egresoTotal += (float)$m['egreso'];
            }

            $movimientosFiltrados[] = $m;
        }

        $totales = [
            'ingreso' => $ingresoTotal,
            'egreso'  => $egresoTotal,
            'neto'    => $ingresoTotal - $egresoTotal
        ];

        // Estado de caja (Info Cierre/Apertura)
        $estadoCaja = obtenerEstadoCaja($conn);

        // Respuesta
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            echo json_encode([
                'success' => true,
                'totales' => $totales,
                'movimientos' => $movimientosFiltrados,
                'estado_caja' => $estadoCaja
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            // Fallback para PHP antiguo
             array_walk_recursive($movimientosFiltrados, function(&$item) {
                if (is_string($item) && !mb_detect_encoding($item, 'UTF-8', true)) $item = utf8_encode($item);
            });
            echo json_encode([
                'success' => true,
                'totales' => $totales,
                'movimientos' => $movimientosFiltrados,
                'estado_caja' => $estadoCaja
            ]);
        }
        exit();
    }

    // ==========================================
    // ACCIÓN: REGISTRAR MOVIMIENTO MANUAL
    // ==========================================
    if ($action === 'registrar_movimiento') {
        $tipo = $_POST['tipo']; // 'INGRESO' o 'GASTO'
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)$_POST['monto'];
        
        // Manejo Inteligente de Categoría
        $categoria = $_POST['categoria'] ?? 'General';

        // Si es un Ingreso Manual y la categoría es genérica, la forzamos a "Ingreso Extra"
        // para distinguirla de "Venta" (que viene de procesar_venta.php)
        if ($tipo === 'INGRESO' && ($categoria === 'General' || empty($categoria))) {
             $categoria = 'Ingreso Extra'; 
        }

        // Validación básica
        if ($monto <= 0 || empty($descripcion)) {
            throw new Exception('Monto o descripción inválidos');
        }

        // Ajuste de Tipo si es Retiro
        if ($tipo === 'GASTO' && stripos($categoria, 'Retiro') !== false) {
            $tipo = 'RETIRO';
        }

        // Generar ID Transacción
        $prefijo = ($tipo === 'GASTO') ? 'GAS' : ($tipo === 'RETIRO' ? 'RET' : 'ING');
        $idTx = $prefijo . date('ymdHi') . rand(10,99);
        
        $ingreso = ($tipo === 'INGRESO') ? $monto : 0;
        $egreso = ($tipo === 'INGRESO') ? 0 : $monto;
        $fechaMovimiento = date('Y-m-d H:i:s');
        $usuario = $_SESSION['nombre'];

        // Insertar
        $sql = "INSERT INTO caja_movimientos 
                (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria) 
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$idTx, $tipo, $descripcion, $monto, $ingreso, $egreso, $usuario, $fechaMovimiento, $categoria]);

        echo json_encode(['success' => true]);
        exit();
    }

    // ==========================================
    // ACCIÓN: INFO PARA CIERRE (Estado y Montos)
    // ==========================================
    if ($action === 'info_cierre') {
        $estado = obtenerEstadoCaja($conn);
        if($estado['estado'] === 'ABIERTA') {
            $estado['inicio'] = date('d/m/Y h:i A', strtotime($estado['inicio']));
        }
        echo json_encode($estado);
        exit();
    }

    // ==========================================
    // ACCIÓN: ABRIR CAJA
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
    // ACCIÓN: CERRAR CAJA
    // ==========================================
    if ($action === 'cerrar_caja') {
        // 1. Buscar turno abierto
        $stmt = $conn->query("SELECT id, saldo_inicial, fecha_apertura FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$caja) throw new Exception('No hay una caja abierta para cerrar');

        // 2. Calcular saldos del sistema (Ingresos - Egresos desde la apertura)
        $sqlCalc = "SELECT 
                    COALESCE(SUM(ingreso), 0) as total_ingresos, 
                    COALESCE(SUM(egreso), 0) as total_gastos 
                    FROM caja_movimientos 
                    WHERE fecha >= ?";
        $stmtCalc = $conn->prepare($sqlCalc);
        $stmtCalc->execute([$caja['fecha_apertura']]);
        $movs = $stmtCalc->fetch(PDO::FETCH_ASSOC);

        $saldoSistema = (float)$caja['saldo_inicial'] + $movs['total_ingresos'] - $movs['total_gastos'];
        
        // 3. Recibir datos del cierre físico
        $montoReal = (float)$_POST['monto_final_real'];
        $notas = $_POST['notas'] ?? '';
        $retirar = $_POST['retirar_fondos'] ?? 'NO';
        $diferencia = $montoReal - $saldoSistema;
        $usuarioCierre = $_SESSION['nombre'];
        $fechaCierre = date('Y-m-d H:i:s');

        // 4. Actualizar registro de cierre
        $sqlCierre = "UPDATE caja_cierres SET 
                      fecha_cierre = ?, usuario_cierre = ?, 
                      monto_final_sistema = ?, monto_final_real = ?, 
                      diferencia = ?, notas = ?, estado = 'CERRADA' 
                      WHERE id = ?";
        $stmtCierre = $conn->prepare($sqlCierre);
        $stmtCierre->execute([$fechaCierre, $usuarioCierre, $saldoSistema, $montoReal, $diferencia, $notas, $caja['id']]);

        // 5. Opción de Retiro Automático (Dejar caja en 0)
        if ($retirar === 'SI' && $montoReal > 0) {
            $idTx = 'RET-CIERRE-' . date('ymdHi');
            $sqlRetiro = "INSERT INTO caja_movimientos (id_transaccion, tipo, descripcion, monto_unitario, egreso, usuario, fecha, categoria) 
                          VALUES (?, 'RETIRO', 'Retiro por Cierre de Caja (Auto)', ?, ?, ?, ?, 'Cierre')";
            $stmtRet = $conn->prepare($sqlRetiro);
            $stmtRet->execute([$idTx, $montoReal, $montoReal, $usuarioCierre, $fechaCierre]);
        }

        echo json_encode(['success' => true]);
        exit();
    }

    // ==========================================
    // ACCIÓN: LISTA DE USUARIOS
    // ==========================================
    if ($action === 'usuarios') {
        $stmt = $conn->query("SELECT DISTINCT usuario FROM caja_movimientos ORDER BY usuario");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Blindaje UTF-8 para nombres de usuario
        if (!defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
             array_walk($users, function(&$item) {
                if (!mb_detect_encoding($item, 'UTF-8', true)) $item = utf8_encode($item);
            });
            echo json_encode(['success' => true, 'data' => $users]);
        } else {
            echo json_encode(['success' => true, 'data' => $users], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit();
    }

} catch (Throwable $e) { 
    // Captura cualquier error fatal o excepción
    http_response_code(500); 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

// ==========================================
// FUNCIÓN AUXILIAR: ESTADO DE CAJA
// ==========================================
function obtenerEstadoCaja($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja) {
            $fechaApertura = $caja['fecha_apertura'];
            
            // Calculamos saldo actual sumando/restando movimientos desde la fecha de apertura
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