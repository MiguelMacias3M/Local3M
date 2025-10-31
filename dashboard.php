<?php
// Incluimos la cabecera (sesión, menú, HTML head)
include 'templates/header.php';

// (Aquí irá la lógica para consultar la base de datos en el futuro)
// --- DATOS DE EJEMPLO ---
$reparaciones_abiertas = 12;
$ganancias_hoy = 1850.50;
$equipos_entregados_hoy = 8;
$alertas_pendientes = 3; // ¡Nueva variable para las alertas!
// --- FIN DATOS DE EJEMPLO ---

?>

<h1>Panel de Control</h1>

<!-- Tarjetas de Estadísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon icon-open">
            <i class="fas fa-tools"></i>
        </div>
        <div class="info">
            <h3>Reparaciones Abiertas</h3>
            <p class="number"><?php echo $reparaciones_abiertas; ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="icon icon-money">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="info">
            <h3>Ganancias de Hoy (Caja)</h3>
            <p class="number">$<?php echo number_format($ganancias_hoy, 2); ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="icon icon-done">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="info">
            <h3>Equipos Entregados Hoy</h3>
            <p class="number"><?php echo $equipos_entregados_hoy; ?></p>
        </div>
    </div>
    
    <!-- Tarjeta de Clientes eliminada y reemplazada por Alertas -->
    <div class="stat-card">
        <div class="icon icon-alert"> <!-- Nueva clase de icono -->
            <i class="fas fa-bell"></i> <!-- Icono de campana -->
        </div>
        <div class="info">
            <h3>Alertas Pendientes</h3>
            <p class="number"><?php echo $alertas_pendientes; ?></p>
        </div>
    </div>

</div>

<!-- Nuevas secciones del Dashboard -->
<div class="dashboard-grid">
    <div class="content-box">
        <h2>Entradas del Día</h2>
        <p>Aquí irá una lista de equipos recibidos hoy...</p>
        <!-- (Próximamente: Lista de equipos) -->
    </div>
    <div class="content-box">
        <h2>Tareas Pendientes</h2>
        <p>Aquí irá una lista de tareas (ej. llamar cliente, pedir pieza)...</p>
        <!-- (Próximamente: Lista de tareas) -->
    </div>
</div>


<?php
// Incluimos el pie de página
include 'templates/footer.php';
?>

