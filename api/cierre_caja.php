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
    // --- 1. OBTENER ESTADO ACTUAL (PARA MOSTRAR EN PANTALLA DE CIERRE) ---
    if ($action === 'estado') {
        $stmt = $conn->prepare("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja) {
            $fechaApertura = $caja['fecha_apertura'];
            
            // CORRECCIÓN 1: Filtramos solo los movimientos del Mostrador (CAJA)
            $sqlMovs = "SELECT tipo, categoria, ingreso, egreso FROM caja_movimientos WHERE fecha >= :fecha AND (origen = 'CAJA' OR origen IS NULL)";
            $stmtMovs = $conn->prepare($sqlMovs);
            $stmtMovs->execute([':fecha' => $fechaApertura]);
            $movimientos = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

            $ingresos = 0;
            $gastosReales = 0;
            $retirosCaja = 0;

            foreach ($movimientos as $m) {
                $ingresos += (float)$m['ingreso'];
                $valEgreso = (float)$m['egreso'];

                if ($valEgreso > 0) {
                    $esRetiro = ($m['tipo'] === 'RETIRO') || 
                                (stripos($m['categoria'], 'Retiro') !== false) || 
                                (stripos($m['categoria'], 'Cierre') !== false);
                    
                    if ($esRetiro) {
                        $retirosCaja += $valEgreso;
                    } else {
                        $gastosReales += $valEgreso;
                    }
                }
            }

            $saldo_inicial = (float)$caja['saldo_inicial'];
            $saldo_teorico = $saldo_inicial + $ingresos - $gastosReales - $retirosCaja;

            echo json_encode([
                'success' => true,
                'estado' => 'ABIERTA',
                'datos' => [
                    'id' => $caja['id'],
                    'fecha_apertura' => $caja['fecha_apertura'],
                    'usuario_apertura' => $caja['usuario_apertura'],
                    'saldo_inicial' => $saldo_inicial,
                    'ingresos' => $ingresos,
                    'egresos' => $gastosReales + $retirosCaja, 
                    'saldo_teorico' => $saldo_teorico
                ]
            ]);
        } else {
            $stmtLast = $conn->query("SELECT fondo_siguiente_dia FROM caja_cierres WHERE estado = 'CERRADA' ORDER BY id DESC LIMIT 1");
            $last = $stmtLast->fetch(PDO::FETCH_ASSOC);
            $fondo = $last ? (float)$last['fondo_siguiente_dia'] : 0;

            echo json_encode([
                'success' => true,
                'estado' => 'CERRADA',
                'fondo_sugerido' => $fondo
            ]);
        }
        exit();
    }

    // --- 2. ABRIR CAJA ---
    if ($action === 'abrir') {
        $saldo_inicial = (float)$_POST['saldo_inicial'];
        $usuario = $_SESSION['nombre'];

        $check = $conn->query("SELECT id FROM caja_cierres WHERE estado = 'ABIERTA'");
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Ya existe una caja abierta']);
            exit();
        }

        $sql = "INSERT INTO caja_cierres (fecha_apertura, usuario_apertura, saldo_inicial, estado) VALUES (NOW(), ?, ?, 'ABIERTA')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$usuario, $saldo_inicial]);

        echo json_encode(['success' => true]);
        exit();
    }

    // --- 3. CERRAR CAJA (MOMENTO DE GUARDAR) ---
    if ($action === 'cerrar') {
        $id = $_POST['id_cierre'];
        $real = (float)$_POST['saldo_real'];
        $fondo = (float)$_POST['fondo_sig'];
        $notas = $_POST['notas'] ?? '';
        $usuario = $_SESSION['nombre'];

        $conn->beginTransaction();

        $stmtCaja = $conn->prepare("SELECT * FROM caja_cierres WHERE id = ? AND estado = 'ABIERTA'");
        $stmtCaja->execute([$id]);
        $caja = $stmtCaja->fetch(PDO::FETCH_ASSOC);

        if (!$caja) throw new Exception("No se encontró la caja abierta");

        // CORRECCIÓN 2: Calcular totales actuales asegurando que solo sean de CAJA
        $stmtMovs = $conn->prepare("SELECT tipo, categoria, ingreso, egreso FROM caja_movimientos WHERE fecha >= ? AND (origen = 'CAJA' OR origen IS NULL)");
        $stmtMovs->execute([$caja['fecha_apertura']]);
        $movs = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

        $ingTotal = 0;
        $egrTotal = 0; 

        foreach($movs as $m) {
            $ingTotal += (float)$m['ingreso'];
            $egrTotal += (float)$m['egreso'];
        }

        $teorico = (float)$caja['saldo_inicial'] + $ingTotal - $egrTotal;
        $diferencia = $real - $teorico;
        
        $retiro = $real - $fondo;

        // REGISTRAR RETIRO DE GANANCIA (Asignando el origen CAJA explícitamente)
        if ($retiro > 0) {
            $sqlRet = "INSERT INTO caja_movimientos 
                       (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria, origen) 
                       VALUES (?, 'RETIRO', ?, 1, ?, 0, ?, ?, NOW(), 'Cierre', 'CAJA')";
            $stmtRet = $conn->prepare($sqlRet);
            $stmtRet->execute([
                'RET-' . date('ymd'),
                'Retiro de ganancia - Cierre #' . $id,
                $retiro,
                $retiro,
                $usuario
            ]);
            
            $egrTotal += $retiro;
        }

        $sqlUpdate = "UPDATE caja_cierres SET 
                        fecha_cierre = NOW(), 
                        usuario_cierre = ?, 
                        ingresos_sistema = ?, 
                        egresos_sistema = ?, 
                        saldo_teorico = ?, 
                        saldo_real_contado = ?, 
                        diferencia = ?, 
                        fondo_siguiente_dia = ?, 
                        retiro_ganancia = ?, 
                        notas = ?, 
                        estado = 'CERRADA' 
                      WHERE id = ?";
        
        $stmtUpd = $conn->prepare($sqlUpdate);
        $stmtUpd->execute([
            $usuario, 
            $ingTotal, 
            $egrTotal, 
            $teorico, 
            $real, 
            $diferencia, 
            $fondo, 
            $retiro, 
            $notas, 
            $id
        ]);

        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>