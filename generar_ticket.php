<?php
session_start();
if (!isset($_SESSION['nombre'])) {
    die("Acceso denegado. Inicia sesión.");
}

include 'config/conexion.php';

// Validar entrada
$id_transaccion = $_GET['id_transaccion'] ?? null;
$id_individual  = $_GET['id'] ?? null;

if (!$id_transaccion && !$id_individual) {
    die("Error: No se especificó ninguna orden para imprimir.");
}

try {
    // 1. Obtener los datos
    if ($id_transaccion) {
        $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id_transaccion = :id");
        $stmt->execute([':id' => $id_transaccion]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
        $stmt->execute([':id' => $id_individual]);
    }

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        die("No se encontraron reparaciones.");
    }

    // 2. Datos de cabecera
    $cliente  = $items[0]['nombre_cliente'];
    $telefono = $items[0]['telefono'];
    $fecha    = $items[0]['fecha_hora']; 
    $usuario  = $items[0]['usuario'];
    
    // Usamos el campo 'codigo_barras' real de la BD
    $codigo_barras = !empty($items[0]['codigo_barras']) ? $items[0]['codigo_barras'] : $items[0]['id_transaccion'];

    $fecha_format = $fecha; 
    try {
        $dateObj = new DateTime($fecha);
        $fecha_format = $dateObj->format('d/m/Y h:i A');
    } catch (Exception $e) {}

    // 3. Lógica para Fecha Estimada General (Header)
    // Buscamos la fecha más lejana de todas las reparaciones para mostrar cuándo estará lista la orden completa.
    $fecha_maxima_ts = null;
    foreach ($items as $item) {
        if (!empty($item['fecha_estimada'])) {
            $ts = strtotime($item['fecha_estimada']);
            if (!$fecha_maxima_ts || $ts > $fecha_maxima_ts) {
                $fecha_maxima_ts = $ts;
            }
        }
    }
    
    $texto_entrega = "";
    if ($fecha_maxima_ts) {
        // Formato ejemplo: 10/02/2026 05:30 PM
        $texto_entrega = date('d/m/Y h:i A', $fecha_maxima_ts);
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $codigo_barras; ?></title>
    <style>
        @page {
            margin: 0; 
            size: 54.5mm auto; 
        }

        body {
            font-family: Arial, sans-serif; 
            font-size: 13px;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
        }

        .ticket {
            width: 54.5mm; 
            max-width: 54.5mm;
            margin: 0 auto; 
            padding: 2px 0; 
            box-sizing: border-box;
            overflow: hidden; 
        }

        /* Textos */
        h1, h2, p { margin: 0; padding: 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        /* Encabezado */
        .logo { 
            font-size: 24px; 
            font-weight: 900; 
            margin-bottom: 2px;
            letter-spacing: -1px;
            text-transform: uppercase;
        }
        .subtitle { font-size: 10px; letter-spacing: 3px; margin-bottom: 8px; display: block;}
        .ticket-title { font-size: 14px; font-weight: bold; border-bottom: 2px solid #000; display: inline-block; margin-bottom: 5px; }

        .divider { 
            border-top: 1px dashed #000; 
            margin: 6px 0; 
            width: 100%;
        }

        /* Tablas */
        .info-table, .items-table, .totals-table { 
            width: 100%; 
            font-size: 12px; 
            border-collapse: collapse; 
        }
        
        .info-table td { padding: 1px 0; }
        .items-table td { padding: 2px 0; }
        .totals-table td { padding: 2px 0; font-size: 13px; }

        /* Footer */
        .footer-text { 
            font-size: 11px; 
            text-align: center; 
            margin-top: 10px; 
            line-height: 1.2;
        }

        /* Botones (No imprimir) */
        .no-print {
            text-align: center;
            padding: 20px;
            background: #f0f0f0;
            border-bottom: 1px solid #ccc;
            margin-bottom: 10px;
        }
        .btn {
            padding: 10px 20px;
            color: #fff;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        .btn-print { background: #000; }
        .btn-close { background: #dc3545; }

        @media print {
            body { margin: 0; padding: 0; }
            .ticket { width: 54.5mm; margin: 0 auto; } 
            .no-print { display: none; }
            * { color: #000 !important; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print">Imprimir 🖨️</button>
        <button onclick="cerrarTicket()" class="btn btn-close">Cerrar ✕</button>
    </div>

    <div class="ticket">
        <div class="center">
            <div class="logo">3M</div>
            <span class="subtitle">TECHNOLOGY</span><br>
            <span class="ticket-title">NOTA DE SERVICIO</span>
        </div>

        <div class="divider"></div>

        <table class="info-table">
            <tr>
                <td class="bold">Folio:</td>
                <td class="right bold" style="font-size:14px;"><?php echo strtoupper($codigo_barras); ?></td>
            </tr>
            <tr>
                <td>Fecha:</td>
                <td class="right"><?php echo $fecha_format; ?></td>
            </tr>
            <tr>
                <td>Atendió:</td>
                <td class="right"><?php echo htmlspecialchars(substr($usuario, 0, 15)); ?></td>
            </tr>
            
            <?php if (!empty($texto_entrega)): ?>
            <tr>
                <td class="bold">Entrega estimada:</td>
                <td class="right bold"><?php echo $texto_entrega; ?></td>
            </tr>
            <?php endif; ?>

        </table>

        <div class="divider"></div>

        <div class="bold" style="font-size:13px;">CLIENTE:</div>
        <div style="font-size:13px; margin-bottom:2px;"><?php echo htmlspecialchars($cliente); ?></div>
        <div>Tel: <?php echo htmlspecialchars($telefono); ?></div>

        <div class="divider"></div>

        <?php 
        $total_monto = 0;
        $total_adelanto = 0;
        $i = 1;
        foreach ($items as $item): 
            $total_monto += $item['monto'];
            $total_adelanto += $item['adelanto'];
            $deuda_item = $item['monto'] - $item['adelanto'];
        ?>
            <div style="margin-bottom: 6px;">
                <div class="bold" style="font-size:13px;">
                    <?php echo $i++; ?>. <?php echo htmlspecialchars($item['tipo_reparacion']); ?>
                </div>
                <div>
                    <?php echo htmlspecialchars($item['marca_celular'] . " " . $item['modelo']); ?>
                </div>
                
                <?php if(!empty($item['info_extra']) && $item['info_extra'] !== 'Ninguna'): ?>
                    <div style="font-style:italic; font-size:11px;">(<?php echo htmlspecialchars($item['info_extra']); ?>)</div>
                <?php endif; ?>

                <table class="items-table" style="margin-top:2px;">
                    <tr>
                        <td>Costo:</td>
                        <td class="right">$<?php echo number_format($item['monto'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Abono:</td>
                        <td class="right">-$<?php echo number_format($item['adelanto'], 2); ?></td>
                    </tr>
                    <tr style="font-weight:bold;">
                        <td>Resta:</td>
                        <td class="right">$<?php echo number_format($deuda_item, 2); ?></td>
                    </tr>
                </table>
            </div>
            <?php if ($i <= count($items)): ?><div style="border-top:1px dotted #ccc; margin:4px 0;"></div><?php endif; ?>
        <?php endforeach; ?>

        <div class="divider"></div>

        <?php if (count($items) > 1): 
            $total_final = $total_monto - $total_adelanto;
        ?>
            <table class="totals-table bold">
                <tr>
                    <td>TOTAL:</td>
                    <td class="right">$<?php echo number_format($total_monto, 2); ?></td>
                </tr>
                <tr>
                    <td>ABONADO:</td>
                    <td class="right">-$<?php echo number_format($total_adelanto, 2); ?></td>
                </tr>
                <tr style="font-size:16px;">
                    <td>RESTA:</td>
                    <td class="right">$<?php echo number_format($total_final, 2); ?></td>
                </tr>
            </table>
            <div class="divider"></div>
        <?php endif; ?>

        <div class="footer-text">
            <strong>CONDICIONES DE SERVICIO</strong><br>
            1. Garantía válida solo con este ticket.<br>
            2. Después de 30 días no nos hacemos responsables por equipos olvidados.<br>
            3. No hay garantía en equipos mojados.<br>
            <br>
            <strong>¡Gracias por su confianza!</strong>
        </div>

        <div class="center" style="margin-top:15px; overflow:hidden; width:100%;">
            <svg id="barcode" style="max-width: 100%; height: auto;"></svg>
        </div>
        
        <div class="center" style="font-size:12px; font-weight:bold; letter-spacing:1px; margin-top:2px;">
            <?php echo strtoupper($codigo_barras); ?>
        </div>
        
        <div class="center" style="margin-top:10px; font-size:11px;">
            <strong>WhatsApp:</strong> 449 491 2164<br>
            Lunes a Sábado 10am - 10pm
        </div>
        <div class="center" style="margin-top:5px;">--- 3M TECHNOLOGY ---</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        try {
            JsBarcode("#barcode", "<?php echo $codigo_barras; ?>", {
                format: "CODE128",
                width: 1.2,
                height: 60,
                displayValue: false, 
                margin: 0,
                flat: true 
            });
        } catch (e) {}

        function cerrarTicket() {
            window.close();
            setTimeout(function() {
                window.location.href = '/local3M/control.php';
            }, 300);
        }
    </script>
</body>
</html>