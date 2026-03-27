<?php
session_start();
include 'config/conexion.php';

$idTx = $_GET['id_transaccion'] ?? null;
if (!$idTx) die("Error: Falta ID de transacción");

// AHORA OBTENEMOS LOS ITEMS DESDE LA CAJA (Así incluye reparaciones y productos juntos)
$sql = "SELECT * FROM caja_movimientos WHERE id_transaccion = :id AND ingreso > 0";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $idTx]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) die("Venta no encontrada en los registros de caja.");

$fecha = $items[0]['fecha'];
$usuario = $items[0]['usuario'];
$cliente = $items[0]['cliente'] !== 'Público General' ? $items[0]['cliente'] : 'Público General';
$total = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de Venta</title>
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
            .btn { padding: 5px 10px; background: #333; color: white; border: none; cursor: pointer; border-radius: 4px;}
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">Imprimir</button>
        <button class="btn" onclick="cerrarTicket()" style="background:#dc3545">Cerrar</button>
    </div>

    <div class="center">
        <div style="font-size:18px; font-weight:900;">3M TECHNOLOGY</div>
        <div>Nota de Venta</div>
        <div style="font-size:10px;">Folio: <?= substr($idTx, -6) ?></div>
        <div style="font-size:10px;"><?= date('d/m/Y H:i', strtotime($fecha)) ?></div>
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
            <?php foreach($items as $item): 
                $subtotal = $item['ingreso'];
                $total += $subtotal;
            ?>
            <tr>
                <td><?= $item['cantidad'] ?></td>
                <td><?= htmlspecialchars($item['descripcion']) ?></td>
                <td class="right">$<?= number_format($subtotal, 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<div class="divider"></div>

    <div class="right bold" style="font-size:14px;">
        TOTAL: $<?= number_format($total, 2) ?>
    </div>

    <?php 
    $paga_con = isset($_GET['paga_con']) ? (float)$_GET['paga_con'] : 0;
    $cambio = $paga_con - $total;
    // Solo lo mostramos si el cliente pagó con algo mayor o igual al total
    if ($paga_con > 0 && $cambio >= 0): 
    ?>
    <div class="right" style="font-size:12px; margin-top:5px; border-top: 1px dashed #ccc; padding-top: 5px;">
        Su Pago: $<?= number_format($paga_con, 2) ?><br>
        Cambio: $<?= number_format($cambio, 2) ?>
    </div>
    <?php endif; ?>
    <div class="center" style="margin-top:15px; font-size:10px;">
        ¡Gracias por su preferencia!<br>
        No hay cambios ni devoluciones.
    </div>
    
<script>
        window.onload = function() {
            // Le damos medio segundo de respiro para que el sistema abra el segundo ticket
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