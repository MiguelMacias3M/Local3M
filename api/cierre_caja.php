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
    // --- 1. OBTENER ESTADO ACTUAL ---
    if ($action === 'estado') {
        // Buscar caja abierta
        $stmt = $conn->prepare("SELECT * FROM caja_cierres WHERE estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja) {
            // CAJA ABIERTA: Calcular totales en tiempo real
            $fechaApertura = $caja['fecha_apertura'];
            
            // Usamos caja_movimientos directamente
            $sqlMovs = "SELECT 
                            COALESCE(SUM(ingreso), 0) as ingresos, 
                            COALESCE(SUM(egreso), 0) as egresos 
                        FROM caja_movimientos 
                        WHERE fecha >= :fecha";
            $stmtMovs = $conn->prepare($sqlMovs);
            $stmtMovs->execute([':fecha' => $fechaApertura]);
            $movs = $stmtMovs->fetch(PDO::FETCH_ASSOC);

            // Cálculos
            $saldo_inicial = (float)$caja['saldo_inicial'];
            $ingresos = (float)$movs['ingresos'];
            $egresos = (float)$movs['egresos'];
            $saldo_teorico = $saldo_inicial + $ingresos - $egresos;

            echo json_encode([
                'success' => true,
                'estado' => 'ABIERTA',
                'datos' => [
                    'id' => $caja['id'],
                    'fecha_apertura' => $caja['fecha_apertura'],
                    'usuario_apertura' => $caja['usuario_apertura'],
                    'saldo_inicial' => $saldo_inicial,
                    'ingresos' => $ingresos,
                    'egresos' => $egresos,
                    'saldo_teorico' => $saldo_teorico
                ]
            ]);
        } else {
            // CAJA CERRADA: Buscar fondo sugerido
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

        // Doble verificación
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

    // --- 3. CERRAR CAJA ---
    if ($action === 'cerrar') {
        $id = $_POST['id_cierre'];
        $real = (float)$_POST['saldo_real'];
        $fondo = (float)$_POST['fondo_sig'];
        $notas = $_POST['notas'] ?? '';
        $usuario = $_SESSION['nombre'];

        $conn->beginTransaction();

        // Recalcular teóricos por seguridad
        $stmtCaja = $conn->prepare("SELECT * FROM caja_cierres WHERE id = ? AND estado = 'ABIERTA'");
        $stmtCaja->execute([$id]);
        $caja = $stmtCaja->fetch(PDO::FETCH_ASSOC);

        if (!$caja) throw new Exception("No se encontró la caja abierta");

        $stmtMovs = $conn->prepare("SELECT COALESCE(SUM(ingreso), 0) as ing, COALESCE(SUM(egreso), 0) as egr FROM caja_movimientos WHERE fecha >= ?");
        $stmtMovs->execute([$caja['fecha_apertura']]);
        $movs = $stmtMovs->fetch(PDO::FETCH_ASSOC);

        $teorico = (float)$caja['saldo_inicial'] + $movs['ing'] - $movs['egr'];
        $diferencia = $real - $teorico;
        
        // Retiro = Lo que había físicamente (real) - Lo que se deja para mañana (fondo)
        $retiro = $real - $fondo;

        // Registrar el Retiro en caja_movimientos si es mayor a 0
        if ($retiro > 0) {
            // CORRECCIÓN: Eliminamos el campo 'categoria' de la consulta
            $sqlRet = "INSERT INTO caja_movimientos (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha) 
                       VALUES (?, 'GASTO', ?, 1, ?, 0, ?, ?, NOW())";
            $stmtRet = $conn->prepare($sqlRet);
            $stmtRet->execute([
                'RET-' . date('ymd'),
                'Retiro de ganancia - Cierre #' . $id,
                $retiro,
                $retiro,
                $usuario
            ]);
        }

        // Actualizar tabla de cierres
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
            $movs['ing'], 
            $movs['egr'], 
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