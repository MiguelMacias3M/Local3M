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
        // Buscar por grupo (transacción)
        $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id_transaccion = :id");
        $stmt->execute([':id' => $id_transaccion]);
    } else {
        // Buscar individual
        $stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
        $stmt->execute([':id' => $id_individual]);
    }

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        die("No se encontraron reparaciones con estos datos.");
    }

    // 2. Datos de cabecera (tomados del primer item)
    $cliente  = $items[0]['nombre_cliente'];
    $telefono = $items[0]['telefono'];
    $fecha    = $items[0]['fecha_hora']; // Formato string según tu BD
    $usuario  = $items[0]['usuario'];
    $folio    = $items[0]['id_transaccion']; // Usamos la transacción como folio visible

    // Formatear fecha si es posible
    $fecha_format = $fecha; 
    try {
        // Intentar convertir si viene como string ISO
        $dateObj = new DateTime($fecha);
        $fecha_format = $dateObj->format('d/m/Y h:i A');
    } catch (Exception $e) {
        // Si falla, dejar como está
    }

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Servicio - 3M</title>
    <style>
        /* Estilos generales para el ticket (58mm - 80mm aprox) */
        body {
            font-family: 'Courier New', Courier, monospace; /* Fuente tipo ticket */
            font-size: 12px;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0; /* Fondo gris para pantalla */
        }

        .ticket {
            width: 80mm; /* Ancho estándar de impresora térmica */
            margin: 20px auto;
            background: #fff;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* Encabezado */
        .header { text-align: center; margin-bottom: 10px; }
        .logo { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .info { font-size: 10px; color: #333; }
        
        /* Separadores */
        .line { border-top: 1px dashed #000; margin: 8px 0; }

        /* Detalles del Cliente */
        .client-info { margin-bottom: 10px; font-size: 11px; }
        .client-info div { margin-bottom: 3px; }

        /* Tabla de items */
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { text-align: left; border-bottom: 1px solid #000; }
        td { padding: 4px 0; vertical-align: top; }
        .col-monto { text-align: right; }
        
        /* Totales */
        .totals { margin-top: 10px; text-align: right; font-size: 12px; }
        .totals div { margin-bottom: 3px; }
        .total-big { font-weight: bold; font-size: 14px; margin-top: 5px; }

        /* Pie de página */
        .footer { text-align: center; margin-top: 20px; font-size: 10px; }
        .barcode { margin-top: 10px; text-align: center; }

        /* Botones de acción (No se imprimen) */
        .no-print {
            text-align: center;
            margin-top: 20px;
            padding-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            background: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-family: sans-serif;
            cursor: pointer;
            border: none;
        }
        .btn:hover { background: #555; }

        /* Reglas de impresión */
        @media print {
            body { background: none; }
            .ticket { width: 100%; margin: 0; box-shadow: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="ticket">
        <!-- Encabezado -->
        <div class="header">
            <div class="logo">3M TECHNOLOGY</div>
            <div class="info">Servicio Técnico Especializado</div>
            <div class="info">Fecha: <?php echo $fecha_format; ?></div>
            <div class="info">Folio: #<?php echo substr($folio, -6); ?></div>
        </div>

        <div class="line"></div>

        <!-- Datos Cliente -->
        <div class="client-info">
            <div><strong>Cliente:</strong> <?php echo htmlspecialchars($cliente); ?></div>
            <div><strong>Teléfono:</strong> <?php echo htmlspecialchars($telefono); ?></div>
            <div><strong>Atendió:</strong> <?php echo htmlspecialchars($usuario); ?></div>
        </div>

        <div class="line"></div>

        <!-- Lista de Equipos -->
        <table>
            <thead>
                <tr>
                    <th style="width: 55%;">Descripción</th>
                    <th class="col-monto">Importe</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_monto = 0;
                $total_adelanto = 0;
                $total_deuda = 0;

                foreach ($items as $item): 
                    $total_monto += $item['monto'];
                    $total_adelanto += $item['adelanto'];
                    $total_deuda += $item['deuda'];
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($item['tipo_reparacion']); ?></strong><br>
                        <?php echo htmlspecialchars($item['marca_celular'] . " " . $item['modelo']); ?><br>
                        <small>Nota: <?php echo htmlspecialchars($item['info_extra']); ?></small>
                    </td>
                    <td class="col-monto">
                        $<?php echo number_format($item['monto'], 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="line"></div>

        <!-- Totales -->
        <div class="totals">
            <div>Total: $<?php echo number_format($total_monto, 2); ?></div>
            <div>Abonado: $<?php echo number_format($total_adelanto, 2); ?></div>
            <div class="total-big">Resta: $<?php echo number_format($total_deuda, 2); ?></div>
        </div>

        <!-- Pie de página -->
        <div class="footer">
            <p>IMPORTANTE</p>
            <p>
                Al recibir este ticket, el cliente acepta que después de 30 días 
                no nos hacemos responsables por equipos olvidados.
                <br><br>
                Garantía válida solo con este ticket.
                <br>
                No cubre equipos mojados o golpeados.
            </p>
            <p>¡Gracias por su confianza!</p>
            
            <div class="barcode">
                <svg id="barcode"></svg>
            </div>
        </div>
    </div>

    <!-- Botones para pantalla -->
    <div class="no-print">
        <button onclick="window.print()" class="btn">Imprimir Ticket</button>
        <!-- CAMBIO: Llamamos a la nueva función cerrarTicket() -->
        <button onclick="cerrarTicket()" class="btn" style="background:#dc3545;">Cerrar</button>
    </div>

    <!-- Generador de código de barras simple -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        // Generar código de barras con el ID de transacción
        try {
            JsBarcode("#barcode", "<?php echo $folio; ?>", {
                format: "CODE128",
                width: 1.5,
                height: 40,
                displayValue: true,
                fontSize: 10,
                margin: 0
            });
        } catch (e) {
            console.error("Error generando código de barras", e);
        }

        // Imprimir automáticamente (Opcional)
        // window.onload = function() { window.print(); }

        // --- FUNCIÓN INTELIGENTE PARA CERRAR ---
        function cerrarTicket() {
            // 1. Intentamos cerrar la ventana (funciona si es popup)
            window.close();
            
            // 2. Si el navegador no la cerró en 200ms, redirigimos al panel de control
            // Esto asegura que el botón SIEMPRE haga algo útil.
            setTimeout(function() {
                // Usamos ruta absoluta por seguridad
                window.location.href = '/local3M/control.php';
            }, 200);
        }
    </script>

</body>
</html>