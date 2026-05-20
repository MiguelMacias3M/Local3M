<?php
$codigo = isset($_GET['codigo']) ? $_GET['codigo'] : '000000';
$nombre = isset($_GET['nombre']) ? $_GET['nombre'] : ''; 

// NUEVOS PARAMETROS PARA EL CONTROL DE TALLER
$cliente  = isset($_GET['cliente']) ? $_GET['cliente'] : ''; 
$detalles = isset($_GET['detalles']) ? $_GET['detalles'] : ''; 
$es_reparacion = (!empty($cliente) || !empty($detalles));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta - 3M TECHNOLOGY</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    
    <style>
        @page {
            size: 57mm 40mm;
            margin: 0mm;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 57mm !important;
            height: 40mm !important;
            background-color: white;
            color: black;
            font-family: Arial, sans-serif;
            overflow: hidden !important; 
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .etiqueta-container {
            width: 57mm;
            height: 40mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-sizing: border-box;
            padding: 1.5mm 1mm 1.5mm 4mm; /* 4mm izquierdo para rodillo */
        }

        .marca-negocio {
            font-size: 8pt;
            font-weight: 900;
            margin-bottom: 2px;
            color: #000;
            text-transform: uppercase;
        }

        .nombre-producto {
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 52mm; 
            color: #000;
        }

        /* ESTILOS EXTRAS PARA TALLER */
        .linea-taller {
            font-size: 7.5pt;
            font-weight: 500;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 52mm;
            color: #000;
            text-align: left; /* Alineado a la izquierda para mayor orden */
        }

        #barcode {
            max-width: 52mm;  
            margin: 0;
            display: block;
        }

        .codigo-texto {
            font-size: 10pt; 
            font-weight: 900; 
            color: #000;
            letter-spacing: 2.5px; 
            margin-top: 2px;
        }

        @media print {
            .no-print { display: none !important; }
        }

        .btn-flotante {
            position: fixed; top: 5px; right: 5px; background: #007aff; color: white;
            border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 10px; z-index: 1000;
        }
    </style>
</head>
<body>

    <button class="no-print btn-flotante" onclick="window.print()">🖨️ Imprimir</button>

    <div class="etiqueta-container">
        <div class="marca-negocio">3M TECHNOLOGY</div>
        
        <?php if (!empty($nombre)): ?>
            <div class="nombre-producto"><?php echo htmlspecialchars($nombre); ?></div>
        <?php endif; ?>

        <?php if (!empty($cliente)): ?>
            <div class="linea-taller"><strong>👤 Cli:</strong> <?php echo htmlspecialchars($cliente); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($detalles)): ?>
            <div class="linea-taller"><strong>🔧 Falla:</strong> <?php echo htmlspecialchars($detalles); ?></div>
        <?php endif; ?>
        
        <img id="barcode">
        <div class="codigo-texto"><?php echo htmlspecialchars($codigo); ?></div>
    </div>

    <script>
        // Si la etiqueta lleva datos de taller, bajamos la altura de las barras a 60 para que quepa todo limpio.
        // Si es una etiqueta normal de producto, aprovecha toda la altura en 90.
        let alturaBarras = <?php echo $es_reparacion ? '60' : '90'; ?>;

        JsBarcode("#barcode", "<?php echo $codigo; ?>", {
            format: "CODE128",
            width: 2.0,       
            height: alturaBarras,       
            displayValue: false, 
            margin: 0,
            background: "#ffffff",
            lineColor: "#000000"
        });

        setTimeout(function() {
            window.print();
        }, 500);
    </script>
</body>
</html>