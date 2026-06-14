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

    // 3. Lógica para Fecha Estimada General
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
        $texto_entrega = date('d/m/Y h:i A', $fecha_maxima_ts);
    }

    // 4. URL DE TU PÁGINA PARA EL CÓDIGO QR
    // Asegúrate de cambiar esto por la URL real de tu página pública (ej: "https://misitio.com/index.php")
    // Al escanear, el cliente irá a esta dirección y su navegador leerá el ?folio=...
    $baseUrlPaginaWeb = "http://localhost/Local3M/index.php"; 

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
    
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* 🚨 AJUSTE PARA IMPRESORA TÉRMICA 80mm 🚨 */
        @page {
            margin: 0; 
            size: 76mm auto; /* 76mm de ancho imprimible real para rollos de 80mm */
        }

        body {
            font-family: Arial, sans-serif; 
            font-size: 14px; /* Un poco más grande para mejor lectura en 80mm */
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
        }

        .ticket {
            width: 72mm; /* Dejamos un margen seguro de 4mm */
            max-width: 72mm;
            margin: 0 auto; 
            padding: 5px 0; 
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
            font-size: 32px; /* Más grande en 80mm */
            font-weight: 900; 
            margin-bottom: 2px;
            letter-spacing: -1px;
            text-transform: uppercase;
        }
        .subtitle { font-size: 12px; letter-spacing: 4px; margin-bottom: 10px; display: block;}
        .ticket-title { font-size: 16px; font-weight: bold; border-bottom: 2px solid #000; display: inline-block; margin-bottom: 8px; }

        .divider { 
            border-top: 1px dashed #000; 
            margin: 8px 0; 
            width: 100%;
        }

        /* Tablas */
        .info-table, .items-table, .totals-table { 
            width: 100%; 
            font-size: 14px; 
            border-collapse: collapse; 
        }
        
        .info-table td { padding: 2px 0; }
        .items-table td { padding: 3px 0; }
        .totals-table td { padding: 3px 0; font-size: 15px; }

        /* Footer */
        .footer-text { 
            font-size: 12px; 
            text-align: center; 
            margin-top: 15px; 
            line-height: 1.3;
        }

        /* Botones (No imprimir) */
        .no-print {
            text-align: center;
            padding: 20px;
            background: #f0f0f0;
            border-bottom: 1px solid #ccc;
            margin-bottom: 15px;
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

        /* Contenedor del QR */
        #qrcode-container {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        @media print {
            body { margin: 0; padding: 0; }
            .ticket { width: 72mm; margin: 0 auto; } 
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
                <td class="right bold" style="font-size:16px;"><?php echo strtoupper($codigo_barras); ?></td>
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
                <td class="bold" style="padding-top: 5px;">Entrega estimada:</td>
                <td class="right bold" style="padding-top: 5px;"><?php echo $texto_entrega; ?></td>
            </tr>
            <?php endif; ?>

        </table>

        <div class="divider"></div>

        <div class="bold" style="font-size:14px;">CLIENTE:</div>
        <div style="font-size:14px; margin-bottom:4px;"><?php echo htmlspecialchars($cliente); ?></div>
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
            <div style="margin-bottom: 8px;">
                <div class="bold" style="font-size:14px;">
                    <?php echo $i++; ?>. <?php echo htmlspecialchars($item['tipo_reparacion']); ?>
                </div>
                <div style="font-size: 13px;">
                    <?php echo htmlspecialchars($item['marca_celular'] . " " . $item['modelo']); ?>
                </div>
                
                <?php if(!empty($item['info_extra']) && $item['info_extra'] !== 'Ninguna'): ?>
                    <div style="font-style:italic; font-size:12px; color: #333;">(<?php echo htmlspecialchars($item['info_extra']); ?>)</div>
                <?php endif; ?>

                <table class="items-table" style="margin-top:4px;">
                    <tr>
                        <td>Costo:</td>
                        <td class="right">$<?php echo number_format($item['monto'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Abono:</td>
                        <td class="right">-$<?php echo number_format($item['adelanto'], 2); ?></td>
                    </tr>
                    <tr style="font-weight:bold; font-size: 15px;">
                        <td>Resta:</td>
                        <td class="right">$<?php echo number_format($deuda_item, 2); ?></td>
                    </tr>
                </table>
            </div>
            <?php if ($i <= count($items)): ?><div style="border-top:1px dotted #ccc; margin:6px 0;"></div><?php endif; ?>
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
                <tr style="font-size:18px;">
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
        <div class="center" style="font-size:14px; font-weight:bold; letter-spacing:1px; margin-top:2px; margin-bottom: 15px;">
            <?php echo strtoupper($codigo_barras); ?>
        </div>

        <div class="divider"></div>
        
        <div class="center" style="margin-top: 10px;">
            <div style="font-size: 11px; font-weight: bold; margin-bottom: 5px;">ESCANEA PARA VER EL ESTADO:</div>
            <div id="qrcode-container"></div>
        </div>

        <div class="center" style="margin-top:15px; font-size:12px;">
            <strong>WhatsApp:</strong> 449 491 2164<br>
            Lunes a Sábado 09:00 am - 09:00 pm
        </div>
        <div class="center" style="margin-top:5px; margin-bottom: 10px;">--- 3M TECHNOLOGY ---</div>
    </div>

    <script>
        window.onload = function() {
            let codigoBarras = "<?php echo trim(preg_replace('/\s+/', '', $codigo_barras)); ?>";
            
            // 1. Dibujar Código de Barras (MÁS ANCHO Y ALTO PARA LECTOR LÁSER)
            if (codigoBarras !== "") {
                try {
                    JsBarcode("#barcode", codigoBarras, {
                        format: "CODE128",
                        width: 1.8,  // Más ancho para que el láser lo lea mejor (antes 1.2 o 1.5)
                        height: 70,  // Más alto (antes 40 o 60)
                        displayValue: false,
                        margin: 0
                    });
                } catch (error) {
                    console.error("Error al dibujar el código de barras:", error);
                }
            }

            // 2. Generar Código QR
            // Construimos la URL agregando el ?folio=...
            let urlRastreo = "<?php echo $baseUrlPaginaWeb; ?>?folio=" + encodeURIComponent(codigoBarras);
            
            new QRCode(document.getElementById("qrcode-container"), {
                text: urlRastreo,
                width: 100, // Tamaño del QR en píxeles
                height: 100,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.L
            });

            // 3. Imprimir automáticamente (comentado temporalmente para que puedas ver el resultado en pantalla)
            // setTimeout(function() {
            //     window.print();
            //     setTimeout(function() { window.close(); }, 1000);
            // }, 500); 
        };

        function cerrarTicket() {
            window.close();
        }
    </script>
</body>
</html>