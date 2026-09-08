<?php
session_start();
if (!isset($_SESSION['nombre'])) {
    die("Acceso denegado. Inicia sesión primero.");
}

// Verificar conexión
if (!file_exists('config/conexion.php')) {
    die("Error: Archivo de conexión no encontrado.");
}
include 'config/conexion.php';

// Forzar UTF-8
if (isset($conn)) {
    try { $conn->exec("SET NAMES 'utf8'"); } catch (Exception $e) {}
}

// Obtener la mercancía Activa ordenada por ubicación y luego por marca
try {
    $sql = "SELECT ubicacion, tipo_repuesto, marca, modelo, codigo_barras, cantidad 
            FROM mercancia 
            WHERE estado = 'Activo' OR estado IS NULL 
            ORDER BY ubicacion ASC, marca ASC, modelo ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $mercancia = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error al cargar la mercancía: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteo Físico Mercancía - 3M TECHNOLOGY</title>
    <style>
        /* Estilos optimizados para hoja tamaño carta */
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            color: #000; 
            font-size: 11px; 
            margin: 0;
            padding: 20px;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 { 
            margin: 0 0 5px 0; 
            font-size: 22px; 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }
        .header p { 
            margin: 3px 0; 
            color: #333; 
            font-size: 13px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        th, td { 
            border: 1px solid #000; 
            padding: 8px 6px; 
            text-align: left; 
            vertical-align: middle; 
        }
        th { 
            background-color: #f2f2f2; 
            font-weight: bold; 
            text-transform: uppercase; 
            font-size: 10px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        
        /* Celdas para escribir a mano */
        .blank-cell { width: 80px; }
        .notes-cell { width: 140px; }
        
        /* Estilos del botón de impresión */
        .btn-print {
            display: block; 
            width: 220px; 
            margin: 0 auto 20px auto; 
            padding: 12px;
            background: #007aff; 
            color: white; 
            text-align: center;
            border-radius: 8px; 
            font-weight: bold; 
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        .btn-print:hover { background: #0056b3; }

        /* Magia para cuando se mande a la impresora */
        @media print {
            @page { margin: 1cm; }
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Botón que desaparecerá al imprimir -->
    <button class="btn-print no-print" onclick="window.print()">
        🖨️ Imprimir Formato de Conteo
    </button>

    <div class="header">
        <h1>3M TECHNOLOGY</h1>
        <p class="text-bold">Hoja de Conteo Físico de Inventario | Mercancía y Refacciones</p>
        <p>Fecha de impresión y revisión: <strong><?= date('d/m/Y') ?></strong></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Ubicación</th>
                <th>Refacción / Equipo</th>
                <th>Código / Modelo</th>
                <th class="text-center">Stock<br>Sistema</th>
                <th class="text-center blank-cell">Conteo<br>Físico</th>
                <th class="notes-cell">Notas / Diferencia</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($mercancia) > 0): ?>
                <?php foreach ($mercancia as $item): ?>
                <tr>
                    <td class="text-center"><?= htmlspecialchars($item['ubicacion'] ?: 'Sin asignar') ?></td>
                    <td>
                        <strong style="text-transform: uppercase; font-size: 10px; color:#555;"><?= htmlspecialchars($item['tipo_repuesto']) ?></strong><br>
                        <span style="font-size: 12px;"><?= htmlspecialchars($item['marca'] . ' ' . $item['modelo']) ?></span>
                    </td>
                    <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($item['codigo_barras']) ?></td>
                    <td class="text-center text-bold" style="font-size: 14px;"><?= htmlspecialchars($item['cantidad']) ?></td>
                    <!-- Espacio en blanco para pluma -->
                    <td></td> 
                    <!-- Espacio en blanco para pluma -->
                    <td></td> 
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px;">No hay mercancía activa registrada en el sistema.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Auto-abrir la ventana de impresión al cargar (Opcional, pero ahorra clics) -->
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>