document.addEventListener('DOMContentLoaded', () => {
    // Al cargar la página, traemos los equipos de la base de datos
    cargarEquipos();

    // Interceptamos el formulario cuando le dan a "Guardar Equipo"
    const formEquipo = document.getElementById('formEquipo');
    
    if(formEquipo) {
        formEquipo.addEventListener('submit', async (e) => {
            e.preventDefault(); // Evitamos que la página recargue
            
            // Recolectamos los datos
            const formData = new FormData();
            formData.append('accion', 'registrar');
            formData.append('tipo', document.getElementById('eq_tipo').value);
            formData.append('marca', document.getElementById('eq_marca').value);
            formData.append('modelo', document.getElementById('eq_modelo').value);
            formData.append('imei_serie', document.getElementById('eq_serie').value);
            formData.append('color', document.getElementById('eq_color').value);
            formData.append('costo', document.getElementById('eq_costo').value);
            formData.append('precio_venta', document.getElementById('eq_precio').value);

            try {
                // Enviamos los datos al PHP
                const response = await fetch('/local3M/api/equipos.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();

                if (data.success) {
                    // Alerta bonita de éxito
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: 'El equipo ya está en tu vitrina.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    cerrarModalEquipo(); // Escondemos la ventana
                    formEquipo.reset(); // Limpiamos el formulario
                    cargarEquipos(); // Refrescamos la tabla
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Hubo un problema de conexión con el servidor', 'error');
            }
        });
    }
});

// Función para traer los equipos y dibujarlos
async function cargarEquipos() {
    try {
        const response = await fetch('/local3M/api/equipos.php?accion=listar');
        const data = await response.json();
        
        const tbody = document.getElementById('tabla-equipos');
        tbody.innerHTML = ''; // Limpiamos antes de dibujar

        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No hay equipos registrados aún.</td></tr>';
            return;
        }

        data.forEach(eq => {
            // Colores estilo Apple para los estados
            let badgeColor = eq.estado === 'Disponible' ? '#34c759' : (eq.estado === 'Apartado' ? '#ff9500' : '#ff3b30');
            
            // Formatear precio para que se vea como dinero ($1,500.00)
            let precioFormateado = parseFloat(eq.precio_venta).toLocaleString('es-MX', {minimumFractionDigits: 2});

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><b>#${eq.id}</b></td>
                <td>${eq.tipo}</td>
                <td><strong>${eq.marca}</strong> ${eq.modelo} <br><small style="color: #888;">${eq.color}</small></td>
                <td style="font-family: monospace; font-size: 15px;">${eq.imei_serie}</td>
                <td style="color: #1d1d1f; font-weight: bold;">$${precioFormateado}</td>
                <td><span style="background: ${badgeColor}; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px;">${eq.estado}</span></td>
                <td style="text-align: center; display: flex; gap: 8px; justify-content: center;">
                    
                    <!-- Botón para Vender/Apartar -->
                    <button class="glass-btn primary" style="padding: 6px 12px; font-size: 13px;" onclick="abrirModalAccion(${eq.id}, '${eq.marca} ${eq.modelo}', ${eq.precio_venta})" title="Realizar Venta o Apartado">
                        <i class="fas fa-shopping-cart"></i>
                    </button>

                    <!-- Botón mágico de Etiqueta Térmica -->
                    <button class="glass-btn" style="padding: 6px 12px; font-size: 13px;" onclick="imprimirEtiquetaEquipo('${eq.imei_serie}', '${eq.marca} ${eq.modelo}')" title="Imprimir Etiqueta Xprinter">
                        <i class="fas fa-barcode"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) {
        console.error('Error al cargar equipos:', error);
    }
}

// Llama al archivo que afinamos milímetro a milímetro
function imprimirEtiquetaEquipo(codigo, nombre) {
    const url = `/local3M/imprimir_etiqueta.php?codigo=${encodeURIComponent(codigo)}&nombre=${encodeURIComponent(nombre)}`;
    window.open(url, '_blank', 'width=400,height=400');
}

// --- FUNCIONES PARA EL MENÚ DE ACCIÓN ---
function abrirModalAccion(id, nombre, precio) {
    // Llenamos los datos invisibles
    document.getElementById('accion_equipo_id').value = id;
    document.getElementById('accion_equipo_nombre').value = nombre;
    document.getElementById('accion_equipo_precio').value = precio;
    
    // Mostramos el texto al usuario
    document.getElementById('texto-accion-equipo').innerText = `Equipo: ${nombre}\nPrecio: $${parseFloat(precio).toLocaleString('es-MX', {minimumFractionDigits: 2})}`;
    
    const modal = document.getElementById('modalAccionEquipo');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show-modal'), 10);
}

function cerrarModalAccion() {
    const modal = document.getElementById('modalAccionEquipo');
    modal.classList.remove('show-modal');
    setTimeout(() => modal.style.display = 'none', 300);
}

function mandarAlCarritoGlobal() {
    const id = document.getElementById('accion_equipo_id').value;
    const nombre = document.getElementById('accion_equipo_nombre').value;
    const precio = parseFloat(document.getElementById('accion_equipo_precio').value);

    const itemEquipo = {
        id: id,
        nombre: nombre,
        precio: precio,
        cantidad: 1, 
        tipo: 'equipo'
    };

    // --- DEBUG 1: Verificamos qué estamos enviando ---

    if (typeof agregarAlCarritoGlobal === 'function') {
        agregarAlCarritoGlobal(itemEquipo);
    } else {
        Swal.fire('Error', 'No se pudo conectar con el carrito de ventas.', 'error');
    }

    cerrarModalAccion();
}

function abrirModalApartado() {
    // Aquí abriremos el formulario para pedir enganche y fecha límite
    alert("Próximamente: Abriendo contrato de apartado...");
}