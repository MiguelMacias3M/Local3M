<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de Conteo de Inventario - 3M TECHNOLOGY</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f6f9; /* Fondo grisecito para la pantalla */
            color: black; 
            margin: 0;
            padding: 20px; 
            font-size: 12px;
        }
        
        /* Contenedor principal que simula una hoja de papel en la pantalla */
        #documento-pdf {
            background: white;
            padding: 30px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        h1 { margin: 0 0 5px 0; font-size: 24px; text-transform: uppercase; }
        p.subtitle { margin: 0; color: #555; font-size: 14px; }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        th, td { 
            border: 1px solid #000; 
            padding: 8px; 
            text-align: left; 
            vertical-align: middle;
        }
        th { 
            background-color: #f0f0f0; 
            font-weight: bold;
            text-transform: uppercase;
        }
        .text-center { text-align: center; }
        .col-blanco { width: 120px; } 
        
        .fila-desordenada { background-color: #fafafa; }
        .texto-sin-asignar { color: #888; font-style: italic; }

        /* Estilos de los botones superiores */
        .controles-superior {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-accion {
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: 0.3s;
        }
        .btn-accion:hover { opacity: 0.9; }
        .btn-imprimir { background-color: #007bff; }
        .btn-pdf { background-color: #dc3545; }

        /* Ocultar elementos cuando se imprime físicamente o se hace el PDF */
        @media print {
            body { background: white; padding: 0; }
            #documento-pdf { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            .fila-desordenada { background-color: transparent; }
        }
    </style>
</head>
<body>

    <div class="no-print controles-superior">
        <button class="btn-accion btn-imprimir" onclick="window.print()">🖨️ Imprimir en Papel</button>
        <button class="btn-accion btn-pdf" onclick="descargarPDF()">📄 Descargar PDF</button>
    </div>

    <div id="documento-pdf">
        <div class="header">
            <h1>3M TECHNOLOGY</h1>
            <p class="subtitle">Hoja de Conteo Físico de Inventario | Fecha de revisión: <strong><span id="fechaActual"></span></strong></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Ubicación</th>
                    <th>Producto</th>
                    <th>Código / Modelo</th>
                    <th class="text-center">Stock Sistema</th>
                    <th class="text-center col-blanco">Conteo Físico</th>
                    <th class="col-blanco">Notas / Diferencias</th>
                </tr>
            </thead>
            <tbody id="tablaReporte">
                <tr><td colspan="6" class="text-center">Cargando inventario completo...</td></tr>
            </tbody>
        </table>
    </div>

    <script>
        document.getElementById('fechaActual').textContent = new Date().toLocaleDateString('es-MX');

        async function cargarInventario() {
            try {
                // Aquí va la señal secreta &todo=1
                const res = await fetch('/local3M/api/productos.php?action=listar&todo=1');
                const json = await res.json();
                const tbody = document.getElementById('tablaReporte');
                tbody.innerHTML = '';

                if (json.success && json.data.length > 0) {
                    
                    const productosOrdenados = json.data.sort((a, b) => {
                        let ubiA = String(a.id_ubicacion || a.ubicacion || '').trim().toLowerCase();
                        let ubiB = String(b.id_ubicacion || b.ubicacion || '').trim().toLowerCase();
                        
                        let aVacio = (ubiA === '' || ubiA === 'null' || ubiA === 'undefined');
                        let bVacio = (ubiB === '' || ubiB === 'null' || ubiB === 'undefined');

                        if (aVacio && !bVacio) return 1;
                        if (!aVacio && bVacio) return -1;

                        if (!aVacio && !bVacio && ubiA !== ubiB) {
                            return ubiA.localeCompare(ubiB);
                        }

                        let nomA = String(a.nombre_producto || '').toLowerCase();
                        let nomB = String(b.nombre_producto || '').toLowerCase();
                        return nomA.localeCompare(nomB);
                    });

                    productosOrdenados.forEach(p => {
                        let rawUbi = p.id_ubicacion || p.ubicacion || '';
                        let textoUbi = String(rawUbi).trim();
                        let esVacio = (textoUbi === '' || textoUbi === 'null' || textoUbi === 'undefined');
                        
                        let displayUbi = esVacio ? '<span class="texto-sin-asignar">Sin asignar</span>' : `<strong>${textoUbi}</strong>`;
                        let claseFila = esVacio ? 'fila-desordenada' : '';

                        const tr = document.createElement('tr');
                        tr.className = claseFila;
                        tr.innerHTML = `
                            <td>${displayUbi}</td>
                            <td>${p.nombre_producto}</td>
                            <td>${p.codigo_barras || '--'}</td>
                            <td class="text-center" style="font-size: 14px;"><strong>${p.cantidad_piezas}</strong></td>
                            <td></td>
                            <td></td>
                        `;
                        tbody.appendChild(tr);
                    });

                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay productos registrados en el sistema.</td></tr>';
                }
            } catch (e) {
                console.error("Error cargando datos:", e);
                document.getElementById('tablaReporte').innerHTML = '<tr><td colspan="6" class="text-center" style="color: red;">Error al cargar la base de datos.</td></tr>';
            }
        }

        // ==========================================
        // FUNCIÓN PARA GENERAR EL PDF
        // ==========================================
        function descargarPDF() {
            // Seleccionamos el div que queremos convertir (ignorando los botones)
            const elemento = document.getElementById('documento-pdf');
            
            // Le damos formato a la fecha para el nombre del archivo (Ej: Inventario_3M_10-04-2026)
            let fechaHoy = new Date().toLocaleDateString('es-MX').replace(/\//g, '-');
            
            // Configuramos la calidad y el tamaño de la hoja (A4)
            const opciones = {
                margin:       10,
                filename:     `Inventario_3M_${fechaHoy}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 }, // El scale 2 mejora la resolución de las letras
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Ejecutamos la librería
            html2pdf().set(opciones).from(elemento).save();
        }

        cargarInventario();
    </script>
</body>
</html>