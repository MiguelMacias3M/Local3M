document.addEventListener('DOMContentLoaded', () => {
    cargarEquipos();

    const formEquipo = document.getElementById('formEquipo');
    
    if(formEquipo) {
        formEquipo.addEventListener('submit', async (e) => {
            e.preventDefault(); 
            
            const formData = new FormData();
            
            // Verificamos si hay un ID oculto (Si hay, es Edición. Si no, es Nuevo)
            const idEquipo = document.getElementById('equipo_id').value;
            const accion = idEquipo ? 'editar' : 'registrar';
            
            formData.append('accion', accion);
            if (idEquipo) formData.append('id', idEquipo);

            formData.append('tipo', document.getElementById('eq_tipo').value);
            formData.append('marca', document.getElementById('eq_marca').value);
            formData.append('modelo', document.getElementById('eq_modelo').value);
            formData.append('imei_serie', document.getElementById('eq_serie').value);
            formData.append('color', document.getElementById('eq_color').value);
            formData.append('costo', document.getElementById('eq_costo').value);
            formData.append('precio_venta', document.getElementById('eq_precio').value);

            try {
                const response = await fetch('/local3M/api/equipos.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    Swal.fire({ icon: 'success', title: '¡Hecho!', text: data.message, timer: 1500, showConfirmButton: false });
                    cerrarModalEquipo(); 
                    cargarEquipos(); 
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Problema de conexión con el servidor', 'error');
            }
        });
    }
});

// Reseteamos el modal al darle a "Nuevo"
// ESTO SE CAMBIÓ PARA EVITAR QUE SE QUEDEN DATOS VIEJOS AL ABRIR EL MODAL
const btnNuevo = document.querySelector('.glass-btn.primary[onclick="abrirModalEquipo()"]');
if(btnNuevo) {
    btnNuevo.onclick = () => {
        document.getElementById('formEquipo').reset();
        document.getElementById('equipo_id').value = '';
        document.getElementById('tituloModalEquipo').innerText = 'Registrar Nuevo Equipo';
        
        const modal = document.getElementById('modalEquipo');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show-modal'), 10);
    };
}

// ----------------------------------------------------
// DIBUJAR LA TABLA Y LOS BOTONES
// ----------------------------------------------------
async function cargarEquipos() {
    try {
        const response = await fetch('/local3M/api/equipos.php?accion=listar');
        const data = await response.json();
        const tbody = document.getElementById('tabla-equipos');
        tbody.innerHTML = ''; 

        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No hay equipos en vitrina.</td></tr>';
            return;
        }

        data.forEach(eq => {
            let badgeColor = eq.estado === 'Disponible' ? '#34c759' : (eq.estado === 'Apartado' ? '#ff9500' : '#ff3b30');
            let precioFormateado = parseFloat(eq.precio_venta).toLocaleString('es-MX', {minimumFractionDigits: 2});

            // ===============================================
            // MAGIA DE SEGURIDAD: BOTONES DE ADMIN
            // ===============================================
            let adminButtons = '';
            // ROL_USUARIO viene desde equipos.php
            if (typeof ROL_USUARIO !== 'undefined' && ROL_USUARIO === 'admin') {
                adminButtons = `
                    <button class="glass-btn" style="padding: 6px 12px; font-size: 13px; color: #ff9500;" onclick="editarEquipo(${eq.id})" title="Editar Detalles">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="glass-btn" style="padding: 6px 12px; font-size: 13px; color: #ff3b30;" onclick="eliminarEquipo(${eq.id}, '${eq.marca} ${eq.modelo}')" title="Eliminar del Sistema">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                `;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><b>#${eq.id}</b></td>
                <td>${eq.tipo}</td>
                <td><strong>${eq.marca}</strong> ${eq.modelo} <br><small style="color: #888;">${eq.color}</small></td>
                <td style="font-family: monospace; font-size: 15px;">${eq.imei_serie}</td>
                <td style="color: #1d1d1f; font-weight: bold;">$${precioFormateado}</td>
                <td><span style="background: ${badgeColor}; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px;">${eq.estado}</span></td>
                <td style="text-align: center; display: flex; gap: 8px; justify-content: center;">
                    
                    <button class="glass-btn primary" style="padding: 6px 12px; font-size: 13px;" onclick="abrirModalAccion(${eq.id}, '${eq.marca} ${eq.modelo}', ${eq.precio_venta})" title="Realizar Venta o Apartado">
                        <i class="fas fa-shopping-cart"></i>
                    </button>

                    <button class="glass-btn" style="padding: 6px 12px; font-size: 13px;" onclick="imprimirEtiquetaEquipo('${eq.imei_serie}', '${eq.marca} ${eq.modelo}')" title="Imprimir Etiqueta Xprinter">
                        <i class="fas fa-barcode"></i>
                    </button>
                    
                    ${adminButtons} </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) { console.error(error); }
}

// ----------------------------------------------------
// FUNCIONES NUEVAS: EDITAR Y ELIMINAR
// ----------------------------------------------------
async function editarEquipo(id) {
    try {
        const response = await fetch(`/local3M/api/equipos.php?accion=obtener&id=${id}`);
        const data = await response.json();

        // Llenamos el modal con los datos
        document.getElementById('equipo_id').value = data.id;
        document.getElementById('eq_tipo').value = data.tipo;
        document.getElementById('eq_marca').value = data.marca;
        document.getElementById('eq_modelo').value = data.modelo;
        document.getElementById('eq_serie').value = data.imei_serie;
        document.getElementById('eq_color').value = data.color;
        document.getElementById('eq_costo').value = data.costo;
        document.getElementById('eq_precio').value = data.precio_venta;

        document.getElementById('tituloModalEquipo').innerText = 'Editar Equipo (#'+data.id+')';

        // Mostramos el modal
        const modal = document.getElementById('modalEquipo');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show-modal'), 10);
    } catch (error) {
        Swal.fire('Error', 'No se pudieron obtener los datos.', 'error');
    }
}

function eliminarEquipo(id, nombreEquipo) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Vas a eliminar: ${nombreEquipo}. Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#86868b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id', id);

            try {
                const response = await fetch('/local3M/api/equipos.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if(data.success) {
                    Swal.fire('Eliminado!', data.message, 'success');
                    cargarEquipos();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Hubo un error de conexión', 'error');
            }
        }
    });
}

function imprimirEtiquetaEquipo(codigo, nombre) {
    const url = `/local3M/imprimir_etiqueta.php?codigo=${encodeURIComponent(codigo)}&nombre=${encodeURIComponent(nombre)}`;
    window.open(url, '_blank', 'width=400,height=400');
}

function cerrarModalEquipo() {
    const modal = document.getElementById('modalEquipo');
    modal.classList.remove('show-modal');
    setTimeout(() => modal.style.display = 'none', 300);
}

// --- FUNCIONES DEL MODAL DE ACCIÓN (VENTA/APARTADO) ---
function abrirModalAccion(id, nombre, precio) {
    document.getElementById('accion_equipo_id').value = id;
    document.getElementById('accion_equipo_nombre').value = nombre;
    document.getElementById('accion_equipo_precio').value = precio;
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

    const itemEquipo = { id: id, nombre: nombre, precio: precio, cantidad: 1, tipo: 'equipo' };

    if (typeof agregarAlCarritoGlobal === 'function') {
        agregarAlCarritoGlobal(itemEquipo);
    } else {
        Swal.fire('Error', 'No se pudo conectar con el carrito.', 'error');
    }
    cerrarModalAccion();
}

function abrirModalApartado() {
    alert("Próximamente: Abriendo contrato de apartado...");
}