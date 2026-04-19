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
            size: 50mm 25mm;
            margin: 0mm;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 50mm !important;
            height: 25mm !important;
            background-color: white;
            color: black;
            font-family: Arial, sans-serif;
            overflow: hidden !important; 
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .etiqueta-container {
            width: 50mm;
            height: 25mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-sizing: border-box;
            
            /* Margen izquierdo de 4mm para centrar en la impresora */
            padding: 1mm 1mm 1mm 4mm; 
        }

        .marca-negocio {
            font-size: 7pt;
            font-weight: bold;
            margin-bottom: 1px;
            color: #000;
        }

        .nombre-producto {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 45mm; 
            color: #000;
        }

        /* BARRAS MAXIMIZADAS */
        #barcode {
            max-width: 45mm;  /* Abarca todo lo ancho disponible */
            max-height: 17mm; /* Mucho más alto al no haber texto estorbando */
            margin: 0;
            display: block;
        }

        .codigo-texto {
            font-size: 9pt; 
            font-weight: 900; 
            color: #000;
            letter-spacing: 2px; 
            margin-top: 1px;
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
            width: 1.8,       /* BARRAS MÁS GRUESAS PARA LECTURA INMEDIATA */
            height: 70,       /* BARRAS MUCHO MÁS ALTAS */
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