document.addEventListener('DOMContentLoaded', () => {
    cargarApartados();

    const formAbono = document.getElementById('formAbono');
    if (formAbono) {
        formAbono.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const idApartado = document.getElementById('abono_id_apartado').value;
            const monto = parseFloat(document.getElementById('abono_monto').value);
            const cliente = formAbono.dataset.cliente;
            const equipo = formAbono.dataset.equipo;

            // Empaquetamos el abono para el carrito global
            const itemAbono = {
                id: idApartado,
                nombre: `Abono: ${equipo}`,
                precio: monto,
                cantidad: 1,
                tipo: 'abono_apartado',
                cliente_nombre: cliente // Dato extra para mostrar en el ticket
            };

            // Lo mandamos al carrito
            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal(itemAbono);
            } else {
                Swal.fire('Error', 'No se encontró el carrito de compras.', 'error');
            }

            cerrarModalAbono();
        });
    }
});

async function cargarApartados() {
    try {
        const response = await fetch('/local3M/api/apartados.php?accion=listar');
        const data = await response.json();
        const tbody = document.getElementById('tabla-apartados');
        tbody.innerHTML = ''; 

        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No hay apartados activos.</td></tr>';
            return;
        }

        data.forEach(ap => {
            let colorEstado = ap.estado === 'Activo' ? '#ff9500' : (ap.estado === 'Liquidado' ? '#34c759' : '#ff3b30');
            let fecha = new Date(ap.fecha_limite).toLocaleDateString('es-MX');
            let btnDisabled = ap.estado === 'Liquidado' ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><b>#${ap.id}</b></td>
                <td><strong>${ap.cliente_nombre}</strong><br><small><i class="fab fa-whatsapp"></i> ${ap.cliente_telefono}</small></td>
                <td>${ap.marca} ${ap.modelo}</td>
                <td style="color: ${ap.estado === 'Vencido' ? '#ff3b30' : '#1d1d1f'}; font-weight: bold;">
                    <i class="far fa-calendar-alt"></i> ${fecha}
                </td>
                <td style="color: #1d1d1f; font-weight: 800; font-size: 15px;">
                    $${parseFloat(ap.restante).toLocaleString('es-MX', {minimumFractionDigits: 2})}
                </td>
                <td>
                    <span style="background: ${colorEstado}; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                        ${ap.estado}
                    </span>
                </td>
                <td style="text-align: center;">
                    <button class="glass-btn primary" onclick="abrirModalAbono(${ap.id}, ${ap.restante}, '${ap.cliente_nombre}', '${ap.marca} ${ap.modelo}')" ${btnDisabled} title="Registrar Pago">
                        <i class="fas fa-hand-holding-usd"></i> Abonar
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) { console.error(error); }
}

function abrirModalAbono(id, restante, cliente, equipo) {
    document.getElementById('abono_id_apartado').value = id;
    document.getElementById('abono_deuda_actual').innerText = '$' + parseFloat(restante).toLocaleString('es-MX', {minimumFractionDigits: 2});
    document.getElementById('abono_monto').value = ''; 
    document.getElementById('abono_monto').max = restante; 
    
    // Guardamos los nombres en secreto en el formulario para usarlos al guardar
    const formAbono = document.getElementById('formAbono');
    formAbono.dataset.cliente = cliente;
    formAbono.dataset.equipo = equipo;

    const modal = document.getElementById('modalAbono');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show-modal'), 10);
}

function cerrarModalAbono() {
    const modal = document.getElementById('modalAbono');
    modal.classList.remove('show-modal');
    setTimeout(() => modal.style.display = 'none', 300);
}