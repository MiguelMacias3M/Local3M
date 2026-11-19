<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de Conteo de Inventario - 3M TECHNOLOGY</title>
    <!-- Librerías nativas para generación real de tablas PDF sin cortes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f6f9;
            color: black; 
            margin: 0;
            padding: 20px; 
            font-size: 12px;
        }
        
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
        .col-blanco { width: 110px; } 
        
        .fila-desordenada { background-color: #fafafa; }
        .texto-sin-asignar { color: #888; font-style: italic; }

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

        @media print {
            body { background: white; padding: 0; }
            #documento-pdf { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            .fila-desordenada { background-color: transparent; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
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

        <table id="tablaInventario">
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
        const fechaTexto = new Date().toLocaleDateString('es-MX');
        document.getElementById('fechaActual').textContent = fechaTexto;
        
        // Guardamos los datos ordenados globalmente para usarlos al generar el PDF
        let datosInventarioGlobal = [];

        async function cargarInventario() {
            try {
                const res = await fetch('/local3M/api/productos.php?action=listar&todo=1');
                const json = await res.json();
                const tbody = document.getElementById('tablaReporte');
                tbody.innerHTML = '';

                if (json.success && json.data.length > 0) {
                    
                    datosInventarioGlobal = json.data.sort((a, b) => {
                        let ubiA = String(a.id_ubicacion || a.ubicacion || '').trim().toLowerCase();
                        let ubiB = String(b.id_ubicacion || b.ubicacion || '').trim().toLowerCase();
                        
                        let aVacio = (ubiA === '' || ubiA === 'null' || ubiA === 'undefined');
                        let bVacio = (ubiB === '' || ubiB === 'null' || ubiB === 'undefined');

                        if (aVacio && !bVacio) return 1;
                        if (!aVacio && bVacio) return -1;

                        if (!aVacio && !bVacio && ubiA !== ubiB) {
                            return ubiA.localeCompare(ubiB, undefined, { numeric: true, sensitivity: 'base' });
                        }

                        let nomA = String(a.nombre_producto || '').toLowerCase();
                        let nomB = String(b.nombre_producto || '').toLowerCase();
                        return nomA.localeCompare(nomB);
                    });

                    datosInventarioGlobal.forEach(p => {
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
        // GENERACIÓN NATIVA DE PDF CON AUTOTABLE
        // ==========================================
        function descargarPDF() {
            if (datosInventarioGlobal.length === 0) {
                alert("Espera a que cargue el inventario antes de descargar.");
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            // Encabezado del documento en la primera página
            doc.setFont("helvetica", "bold");
            doc.setFontSize(18);
            doc.text("3M TECHNOLOGY", 105, 16, { align: "center" });

            doc.setFont("helvetica", "normal");
            doc.setFontSize(10);
            doc.setTextColor(80);
            doc.text(`Hoja de Conteo Físico de Inventario | Fecha de revisión: ${fechaTexto}`, 105, 22, { align: "center" });

            // Línea divisoria
            doc.setDrawColor(0);
            doc.setLineWidth(0.5);
            doc.line(10, 25, 200, 25);

            // Preparamos las filas limpias directamente desde el arreglo ordenado
            const filas = datosInventarioGlobal.map(p => {
                let rawUbi = p.id_ubicacion || p.ubicacion || '';
                let textoUbi = String(rawUbi).trim();
                let esVacio = (textoUbi === '' || textoUbi === 'null' || textoUbi === 'undefined');

                return [
                    esVacio ? 'Sin asignar' : textoUbi,
                    p.nombre_producto || '',
                    p.codigo_barras || '--',
                    String(p.cantidad_piezas ?? 0),
                    '', // Conteo físico
                    ''  // Notas / Diferencias
                ];
            });

            // Dibujamos la tabla con saltos de página automáticos y encabezados repetidos
            doc.autoTable({
                startY: 29,
                margin: { top: 12, right: 10, bottom: 15, left: 10 },
                head: [['UBICACIÓN', 'PRODUCTO', 'CÓDIGO / MODELO', 'STOCK SIST.', 'CONTEO FÍSICO', 'NOTAS / DIFERENCIAS']],
                body: filas,
                theme: 'grid',
                rowPageBreak: 'avoid', // Nunca corta una fila entre dos hojas
                headStyles: {
                    fillColor: [240, 240, 240],
                    textColor: [0, 0, 0],
                    fontStyle: 'bold',
                    fontSize: 8,
                    lineColor: [0, 0, 0],
                    lineWidth: 0.2,
                    halign: 'left'
                },
                bodyStyles: {
                    textColor: [0, 0, 0],
                    fontSize: 8.5,
                    lineColor: [0, 0, 0],
                    lineWidth: 0.2,
                    cellPadding: 2.2,
                    valign: 'middle'
                },
                columnStyles: {
                    0: { cellWidth: 20, fontStyle: 'bold' }, // Ubicación
                    1: { cellWidth: 64 },                     // Producto (se ajusta en múltiples líneas sin cortarse)
                    2: { cellWidth: 32 },                     // Código
                    3: { cellWidth: 20, halign: 'center', fontStyle: 'bold' }, // Stock
                    4: { cellWidth: 24 },                     // Conteo físico
                    5: { cellWidth: 30 }                      // Notas
                },
                didParseCell: function(data) {
                    // Estilo gris cursiva si no tiene ubicación asignada
                    if (data.section === 'body' && data.column.index === 0 && data.cell.raw === 'Sin asignar') {
                        data.cell.styles.textColor = [130, 130, 130];
                        data.cell.styles.fontStyle = 'italic';
                    }
                    // Centrar título de Stock Sistema y Conteo Físico
                    if (data.section === 'head' && (data.column.index === 3 || data.column.index === 4)) {
                        data.cell.styles.halign = 'center';
                    }
                },
                didDrawPage: function(data) {
                    // Pie de página con número de hoja en todas las páginas
                    const totalPaginas = doc.internal.getNumberOfPages();
                    doc.setFontSize(8);
                    doc.setTextColor(100);
                    doc.text(
                        `Página ${data.pageNumber} de ${totalPaginas}`,
                        200,
                        290,
                        { align: 'right' }
                    );
                }
            });

            let fechaArchivo = fechaTexto.replace(/\//g, '-');
            doc.save(`Inventario_3M_${fechaArchivo}.pdf`);
        }

        cargarInventario();
    </script>
</body>
</html>