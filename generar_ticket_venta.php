<?php
session_start();
include 'config/conexion.php';

$idTx = $_GET['id_transaccion'] ?? null;
if (!$idTx) die("Error: Falta ID de transacción");

// Obtenemos los items desde la caja
$sql = "SELECT * FROM caja_movimientos WHERE id_transaccion = :id AND ingreso > 0";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $idTx]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) die("Venta no encontrada en los registros de caja.");

$fecha = $items[0]['fecha'];
$usuario = $items[0]['usuario'];
$cliente = $items[0]['cliente'] !== 'Público General' ? $items[0]['cliente'] : 'Público General';

// Variables para el cálculo financiero del ticket
$total = 0;
$total_descuento = 0;
$pagos = [
    'Efectivo' => 0,
    'Terminal' => 0,
    'Transferencia' => 0
];

// Procesamos cada artículo para separar pagos, sumar totales y extraer ahorros
foreach ($items as &$item) {
    $subtotal = (float)$item['ingreso'];
    $total += $subtotal;
    
    // Sumar por método de pago (si no existe, por defecto es Efectivo)
    $metodo = $item['metodo_pago'] ?? 'Efectivo';
    if (isset($pagos[$metodo])) {
        $pagos[$metodo] += $subtotal;
    } else {
        $pagos['Efectivo'] += $subtotal;
    }

    // Extraer descuento de la descripción con una expresión regular
    if (preg_match('/\(Desc:\s*-\$([0-9,.]+)\)/i', $item['descripcion'], $matches)) {
        $total_descuento += (float)str_replace(',', '', $matches[1]);
    }
    
    // Limpiar la descripción para que el ticket se vea elegante (sin etiquetas del sistema)
    $desc_limpia = preg_replace('/\(Desc:\s*-\$[0-9,.]+\)/i', '', $item['descripcion']);
    $desc_limpia = preg_replace('/\[Pagado c\/ .*?\]/i', '', $desc_limpia);
    $item['descripcion_limpia'] = trim($desc_limpia);
}

// Variables del Cambio
$paga_con = isset($_GET['paga_con']) ? (float)$_GET['paga_con'] : $total;
$cambio = $paga_con - $total;

// Lógica de Efectivo Entregado Real: 
// El sistema guarda el cobro "Neto". Si hubo cambio, el cliente entregó billetes extra, así que lo sumamos para el desglose.
$efectivo_entregado = $pagos['Efectivo'];
if ($cambio > 0) {
    $efectivo_entregado += $cambio; 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de Venta - 3M TECHNOLOGY</title>
    <style>
        @page { margin: 0; size: 54.5mm auto; }
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 5px; width: 54.5mm; box-sizing: border-box;}
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; font-size: 11px; border-collapse: collapse; }
        td { vertical-align: top; padding: 2px 0; }
        .no-print { display: none; }
        @media screen { 
            body { margin: 20px auto; box-shadow: 0 0 10px #ccc; } 
            .no-print { display: block; text-align: center; margin-bottom: 10px; }
            .btn { padding: 5px 10px; background: #007aff; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;}
            .btn-close { background: #ff3b30; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">Imprimir Ticket</button>
        <button class="btn btn-close" onclick="window.close()">Cerrar</button>
    </div>

    <div class="center">
        <div style="font-size:18px; font-weight:900;">3M TECHNOLOGY</div>
        <div>Nota de Venta</div>
        <div style="font-size:10px; margin-top:3px;">Folio: <?= substr($idTx, -6) ?></div>
        <div style="font-size:10px;"><?= date('d/m/Y h:i A', strtotime($fecha)) ?></div>
        <div style="font-size:10px;">Atendió: <?= htmlspecialchars($usuario) ?></div>
        <div style="font-size:10px;">Cliente: <?= htmlspecialchars($cliente) ?></div>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr style="border-bottom:1px solid #000;">
                <th style="text-align:left; width: 15%;">Cant.</th>
                <th style="text-align:left; width: 55%;">Concepto</th>
                <th style="text-align:right; width: 30%;">Importe</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <td><?= $item['cantidad'] ?></td>
                <td><?= htmlspecialchars($item['descripcion_limpia']) ?></td>
                <td class="right">$<?= number_format($item['ingreso'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="right bold" style="font-size:15px; margin-bottom: 5px;">
        TOTAL: $<?= number_format($total, 2) ?>
    </div>

    <div style="font-size:11px; margin-top:5px; border-top: 1px dashed #ccc; padding-top: 5px;">
        <table style="width: 100%;">
            <?php if ($efectivo_entregado > 0): ?>
            <tr>
                <td>Pago en Efectivo:</td>
                <td class="right">$<?= number_format($efectivo_entregado, 2) ?></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($pagos['Terminal'] > 0): ?>
            <tr>
                <td>Pago con Tarjeta:</td>
                <td class="right">$<?= number_format($pagos['Terminal'], 2) ?></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($pagos['Transferencia'] > 0): ?>
            <tr>
                <td>Pago Transferencia:</td>
                <td class="right">$<?= number_format($pagos['Transferencia'], 2) ?></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($total_descuento > 0): ?>
            <tr>
                <td class="bold">Ahorro / Descuento:</td>
                <td class="right bold" style="text-transform: uppercase;">-$<?= number_format($total_descuento, 2) ?></td>
            </tr>
            <?php endif; ?>

            <tr>
                <td class="bold">Cambio Entregado:</td>
                <td class="right bold">$<?= number_format(max(0, $cambio), 2) ?></td>
            </tr>
        </table>
    </div>

    <div class="center" style="margin-top:15px; font-size:10px;">
        ¡Gracias por su preferencia!<br>
        No hay cambios ni devoluciones.
    </div>
    
    <script>
        window.onload = function() {
            // Le damos medio segundo de respiro para renderizar antes de imprimir
            setTimeout(function() {
                window.print();
                
                // Cerramos un segundo después de mandar a imprimir
                setTimeout(function() {
                    window.close();
                }, 1000);
            }, 500); 
        };
    </script>
</body>
</html>