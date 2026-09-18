<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');
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
            
            // CORRECCIÓN 1: Traemos el metodo_pago de la BD
            $sqlMovs = "SELECT tipo, categoria, ingreso, egreso, metodo_pago FROM caja_movimientos WHERE fecha >= :fecha AND (origen = 'CAJA' OR origen IS NULL)";
            $stmtMovs = $conn->prepare($sqlMovs);
            $stmtMovs->execute([':fecha' => $fechaApertura]);
            $movimientos = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

            $ingresosFisicos = 0; // Aquí solo sumaremos efectivo
            $gastosReales = 0;
            $retirosCaja = 0;

            foreach ($movimientos as $m) {
                // Filtro para ignorar transferencias y tarjeta en el cajón
                $metodoPago = $m['metodo_pago'] ?? 'Efectivo';
                if ($metodoPago === 'Efectivo') {
                    $ingresosFisicos += (float)$m['ingreso'];
                }

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
            // El teórico ahora solo considera el efectivo
            $saldo_teorico = $saldo_inicial + $ingresosFisicos - $gastosReales - $retirosCaja;

            echo json_encode([
                'success' => true,
                'estado' => 'ABIERTA',
                'datos' => [
                    'id' => $caja['id'],
                    'fecha_apertura' => $caja['fecha_apertura'],
                    'usuario_apertura' => $caja['usuario_apertura'],
                    'saldo_inicial' => $saldo_inicial,
                    'ingresos' => $ingresosFisicos, // Reflejamos solo efectivo en pantalla
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

        // CORRECCIÓN 2: Calcular el cierre final ignorando pagos virtuales
        $stmtMovs = $conn->prepare("SELECT tipo, categoria, ingreso, egreso, metodo_pago FROM caja_movimientos WHERE fecha >= ? AND (origen = 'CAJA' OR origen IS NULL)");
        $stmtMovs->execute([$caja['fecha_apertura']]);
        $movs = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

        $ingTotalFisico = 0;
        $egrTotal = 0; 

        foreach($movs as $m) {
            $metodoPago = $m['metodo_pago'] ?? 'Efectivo';
            if ($metodoPago === 'Efectivo') {
                $ingTotalFisico += (float)$m['ingreso'];
            }
            $egrTotal += (float)$m['egreso'];
        }

        $teorico = (float)$caja['saldo_inicial'] + $ingTotalFisico - $egrTotal;
        $diferencia = $real - $teorico;
        
        $retiro = $real - $fondo;

        if ($retiro > 0) {
            $sqlRet = "INSERT INTO caja_movimientos 
                       (id_transaccion, tipo, descripcion, cantidad, monto_unitario, ingreso, egreso, usuario, fecha, categoria, origen, metodo_pago) 
                       VALUES (?, 'RETIRO', ?, 1, ?, 0, ?, ?, NOW(), 'Cierre', 'CAJA', 'Efectivo')";
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
            $ingTotalFisico, 
            $egrTotal, 
            $teorico, 
            $real, 
            $diferencia, 
            $fondo, 
            $retiro, 
            $notas, 
            $id
        ]);

        // ====================================================
        // ALERTA POR CORREO SI HAY DIFERENCIA EN CAJA
        // ====================================================
        if (round($diferencia, 2) != 0.00) {
            $correo_destino = "miguelmacias3m@gmail.com"; 
            
            $tipoDif = ($diferencia < 0) ? "FALTANTE" : "SOBRANTE";
            $colorDif = ($diferencia < 0) ? "#ff3b30" : "#34c759";
            
            $asunto = "⚠️ Alerta de $tipoDif en Corte de Caja - 3M TECHNOLOGY";
            
            $mensaje_html = "
            <html>
            <head><title>Diferencia en Corte</title></head>
            <body style='font-family: Arial, sans-serif; color: #1d1d1f; background-color: #f5f5f7; padding: 20px;'>
                <div style='background: white; border-radius: 16px; padding: 20px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid {$colorDif};'>
                    <h2 style='color: {$colorDif}; margin-top: 0;'>Alerta de Arqueo de Caja</h2>
                    <p style='color: #86868b;'>Se ha registrado un cierre de turno con una diferencia en el efectivo físico.</p>
                    
                    <table border='0' cellpadding='12' cellspacing='0' style='width: 100%; text-align: left; font-size: 14px;'>
                        <tr style='background: rgba(0,0,0,0.02);'>
                            <th style='width: 40%; border-bottom: 1px solid #eee;'>👤 Usuario:</th>
                            <td style='border-bottom: 1px solid #eee;'><b>{$usuario}</b></td>
                        </tr>
                        <tr>
                            <th style='border-bottom: 1px solid #eee;'>🖥️ Efectivo Esperado:</th>
                            <td style='border-bottom: 1px solid #eee;'>$" . number_format($teorico, 2) . "</td>
                        </tr>
                        <tr style='background: rgba(0,0,0,0.02);'>
                            <th style='border-bottom: 1px solid #eee;'>💰 Efectivo Contado:</th>
                            <td style='border-bottom: 1px solid #eee;'>$" . number_format($real, 2) . "</td>
                        </tr>
                        <tr>
                            <th style='border-bottom: 1px solid #eee;'>⚖️ Diferencia:</th>
                            <td style='border-bottom: 1px solid #eee; color: {$colorDif}; font-size: 18px;'><b>$" . number_format($diferencia, 2) . " ({$tipoDif})</b></td>
                        </tr>
                        <tr style='background: rgba(0,0,0,0.02);'>
                            <th style='border-bottom: 1px solid #eee;'>📝 Justificación:</th>
                            <td style='border-bottom: 1px solid #eee; color: #ff9500;'><i>\"{$notas}\"</i></td>
                        </tr>
                    </table>
                    
                    <p style='font-size: 12px; color: #86868b; text-align: center; margin-top: 20px;'>
                        Este es un mensaje automático del sistema POS de 3M TECHNOLOGY.<br>
                        " . date('d/m/Y h:i A') . "
                    </p>
                </div>
            </body>
            </html>
            ";

            $headers  = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: 3M System <sistema@3mtechnologyoficial.com>" . "\r\n";

            @mail($correo_destino, $asunto, $mensaje_html, $headers);
        }
        // ====================================================

        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>