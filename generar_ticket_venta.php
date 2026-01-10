<?php
session_start();
include 'config/conexion.php';

$idTx = $_GET['id_transaccion'] ?? null;
if (!$idTx) die("Error: Falta ID");

// Obtener items de la venta
$sql = "SELECT v.*, p.nombre_producto, p.precio_producto 
        FROM ventas v 
        JOIN productos p ON v.id_producto = p.id_productos 
        WHERE v.id_transaccion = :id";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $idTx]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) die("Venta no encontrada");

$fecha = $items[0]['fecha'];
$usuario = $items[0]['usuario'];
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
        td { vertical-align: top; }
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
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr style="border-bottom:1px solid #000;">
                <th style="text-align:left;">Cant.</th>
                <th style="text-align:left;">Prod.</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): 
                $subtotal = $item['precio_producto'] * $item['cantidad'];
                $total += $subtotal;
            ?>
            <tr>
                <td><?= $item['cantidad'] ?></td>
                <td><?= htmlspecialchars($item['nombre_producto']) ?></td>
                <td class="right">$<?= number_format($subtotal, 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="right bold" style="font-size:14px;">
        TOTAL: $<?= number_format($total, 2) ?>
    </div>

    <div class="center" style="margin-top:15px; font-size:10px;">
        ¡Gracias por su compra!<br>
        No hay cambios ni devoluciones.
    </div>
    
    <script>
        function cerrarTicket() {
            window.close();
            // Redirigir a venta.php con ruta absoluta si no se cierra
            setTimeout(function() {
                window.location.href = '/local3M/venta.php';
            }, 300);
        }
        
        // window.onload = () => window.print();
    </script>
</body>
</html>