<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// BLOQUEO DE SEGURIDAD: Solo Admin
if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'admin') {
    header("Location: /local3M/dashboard.php");
    exit();
}

include 'templates/header.php';
?>

<div class="page-title" style="margin-bottom: 20px;">
    <h1><i class="fas fa-trophy" style="color: #f1c40f;"></i> Rendimiento y Bonos</h1>
    <p>Mide el desempeño de tus empleados y define los ganadores del mes.</p>
</div>

<div class="content-box" style="margin-bottom: 20px;">
    <div style="display: flex; gap: 15px; align-items: center;">
        <label style="font-weight: bold;">Seleccionar Mes:</label>
        <input type="month" id="mesFiltro" class="form-input" style="width: 200px;" onchange="cargarRankings()">
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    
    <div class="content-box" style="border-top: 4px solid #2ecc71;">
        <h2 style="text-align: center; color: #2ecc71; margin-bottom: 20px;">
            <i class="fas fa-money-bill-wave"></i> Mayor Ingreso Generado
        </h2>
        <table class="repair-table">
            <thead>
                <tr>
                    <th>Posición</th>
                    <th>Empleado</th>
                    <th class="text-right">Total Generado</th>
                </tr>
            </thead>
            <tbody id="tablaDinero">
                </tbody>
        </table>
    </div>

    <div class="content-box" style="border-top: 4px solid #3498db;">
        <h2 style="text-align: center; color: #3498db; margin-bottom: 20px;">
            <i class="fas fa-shopping-bag"></i> Mayor Cantidad de Ventas
        </h2>
        <table class="repair-table">
            <thead>
                <tr>
                    <th>Posición</th>
                    <th>Empleado</th>
                    <th class="text-right">Operaciones</th>
                </tr>
            </thead>
            <tbody id="tablaVentas">
                </tbody>
        </table>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Poner el mes actual por defecto
    const fecha = new Date();
    const mes = fecha.getFullYear() + '-' + String(fecha.getMonth() + 1).padStart(2, '0');
    document.getElementById('mesFiltro').value = mes;
    
    cargarRankings();
});

async function cargarRankings() {
    const mes = document.getElementById('mesFiltro').value;
    if(!mes) return;

    try {
        const res = await fetch(`/local3M/api/bonos.php?mes=${mes}`);
        const json = await res.json();

        if (json.success) {
            dibujarTabla('tablaDinero', json.dinero, 'dinero');
            dibujarTabla('tablaVentas', json.ventas, 'ventas');
        }
    } catch (e) {
        console.error("Error al cargar rankings", e);
    }
}

function dibujarTabla(idTabla, datos, tipo) {
    const tbody = document.getElementById(idTabla);
    tbody.innerHTML = '';

    if (datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No hay registros este mes.</td></tr>';
        return;
    }

    datos.forEach((item, index) => {
        let medalla = '';
        if (index === 0) medalla = '<i class="fas fa-medal" style="color: gold; font-size: 1.5em;"></i>';
        else if (index === 1) medalla = '<i class="fas fa-medal" style="color: silver; font-size: 1.2em;"></i>';
        else if (index === 2) medalla = '<i class="fas fa-medal" style="color: #cd7f32; font-size: 1.1em;"></i>';
        else medalla = `<b>#${index + 1}</b>`;

        const valor = tipo === 'dinero' 
            ? `<strong style="color: #2ecc71;">$${parseFloat(item.total_dinero).toFixed(2)}</strong>`
            : `<strong style="color: #3498db;">${item.total_ventas} ventas</strong>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align: center; width: 60px;">${medalla}</td>
            <td style="font-size: 1.1em; font-weight: bold;">${item.usuario}</td>
            <td class="text-right">${valor}</td>
        `;
        tbody.appendChild(tr);
    });
}
</script>

<?php include 'templates/footer.php'; ?>