<?php
$codigo = isset($_GET['codigo']) ? $_GET['codigo'] : '000000';
// Si no nos mandan nombre, lo dejamos vacío
$nombre = isset($_GET['nombre']) ? $_GET['nombre'] : ''; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta - 3M TECHNOLOGY</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    
    <style>
        @page {
            size: 57mm 40mm; /* NUEVO TAMAÑO DE ETIQUETA */
            margin: 0mm;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 57mm !important; /* NUEVO TAMAÑO */
            height: 40mm !important; /* NUEVO TAMAÑO */
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
            
            /* Ajustamos los márgenes para la impresora (dejamos 4mm a la izquierda por el rodillo) */
            padding: 2mm 2mm 2mm 4mm; 
        }

        .marca-negocio {
            font-size: 9pt; /* Letra crecida */
            font-weight: 900;
            margin-bottom: 2px;
            color: #000;
        }

        .nombre-producto {
            font-size: 10pt; /* Letra crecida */
            font-weight: bold;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 52mm; 
            color: #000;
        }

        /* BARRAS MAXIMIZADAS AL NUEVO TAMAÑO */
        #barcode {
            max-width: 52mm;  
            max-height: 22mm; 
            margin: 0;
            display: block;
        }

        .codigo-texto {
            font-size: 11pt; /* Números más grandes y legibles */
            font-weight: 900; 
            color: #000;
            letter-spacing: 3px; 
            margin-top: 3px;
        }

        @media print {
            .no-print { display: none !important; }
        }

        .btn-flotante {
            position: fixed;
            top: 5px;
            right: 5px;
            background: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10px;
            z-index: 1000;
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
        
        <img id="barcode">
        <div class="codigo-texto"><?php echo htmlspecialchars($codigo); ?></div>
    </div>

    <script>
        JsBarcode("#barcode", "<?php echo $codigo; ?>", {
            format: "CODE128",
            width: 2.2,       /* BARRAS MÁS GRUESAS: Al tener 57mm de ancho, la pistola lo leerá rapidísimo */
            height: 90,       /* BARRAS MÁS ALTAS: Aprovechamos los 40mm de altura */
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