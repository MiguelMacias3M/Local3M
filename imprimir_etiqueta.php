<?php
$codigo = isset($_GET['codigo']) ? $_GET['codigo'] : '000000';
$marca_modelo = isset($_GET['nombre']) ? $_GET['nombre'] : ''; 
$tipo_reparacion = isset($_GET['detalles']) ? $_GET['detalles'] : ''; 

$es_reparacion = (!empty($tipo_reparacion));
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
            padding: 2mm 1mm 1mm 4mm; /* 4mm izquierdo para el rodillo */
        }

        .marca-negocio {
            font-size: 10pt;
            font-weight: 900;
            margin-bottom: 2px;
            color: #000;
            letter-spacing: 1px;
        }

        /* PASTILLA NEGRA PARA EL TIPO DE REPARACIÓN */
        .pastilla-reparacion {
            font-size: 8.5pt;
            font-weight: bold;
            background-color: #000;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            margin-bottom: 3px;
            max-width: 50mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }

        /* MARCA Y MODELO EN UNA SOLA LÍNEA */
        .marca-modelo {
            font-size: 11pt;
            font-weight: 900;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 52mm; 
            color: #000;
        }

        #barcode {
            max-width: 52mm;  
            margin: 0;
            display: block;
        }

        .codigo-texto {
            font-size: 11pt; 
            font-weight: 900; 
            color: #000;
            letter-spacing: 3px; 
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
        
        <?php if (!empty($tipo_reparacion)): ?>
            <div class="pastilla-reparacion"><?php echo htmlspecialchars($tipo_reparacion); ?></div>
        <?php endif; ?>

        <?php if (!empty($marca_modelo)): ?>
            <div class="marca-modelo"><?php echo htmlspecialchars($marca_modelo); ?></div>
        <?php endif; ?>
        
        <img id="barcode">
        <div class="codigo-texto"><?php echo htmlspecialchars($codigo); ?></div>
    </div>

    <script>
        // Si la etiqueta tiene reparación, acortamos un poquito las barras. Si es de inventario, las hacemos enormes.
        let alturaBarras = <?php echo $es_reparacion ? '80' : '90'; ?>;

        JsBarcode("#barcode", "<?php echo $codigo; ?>", {
            format: "CODE128",
            width: 2.6,       
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