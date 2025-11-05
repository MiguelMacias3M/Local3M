<?php
// 1. Incluimos al "guardia de seguridad" (header.php)
// Él se encarga de session_start(), anti-caché y comprobar el login.
include 'templates/header.php';

// ---- DATOS DE EJEMPLO (eventualmente vendrán de la BD) ----
$reparaciones_abiertas = 7;
$ingresos_dia = 320.50;
$alertas_pendientes = 1;
$entregas_hoy = 3; 
$monto_por_cobrar = 1480.00; 
// -----------------------------------------------------------
?>

<!--
=====================================
CONTENIDO PRINCIPAL DEL DASHBOARD
=====================================
-->

<!-- Título de la Página -->
<div class="page-title">
    <h1>Panel Principal</h1>
    <p>Resumen general de tu taller.</p>
</div>

<!-- 
  SECCIÓN DE ESTADÍSTICAS
-->
<div class="stats-grid">

    <!-- Reparaciones Abiertas -->
    <div class="stat-card">
        <div class="stat-icon icon-repair">
            <i class="fas fa-tools"></i>
        </div>
        <div class="stat-info">
            <h2><?php echo $reparaciones_abiertas; ?></h2>
            <p>Reparaciones Abiertas</p>
        </div>
    </div>

    <!-- Ingresos del Día -->
    <div class="stat-card">
        <div class="stat-icon icon-money">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-info">
            <h2>$<?php echo number_format($ingresos_dia, 2); ?></h2>
            <p>Ingresos del Día</p>
        </div>
    </div>

    <!-- Entregas para Hoy -->
    <div class="stat-card">
        <div class="stat-icon icon-entrega">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h2><?php echo $entregas_hoy; ?></h2>
            <p>Entregas para Hoy</p>
        </div>
    </div>

    <!-- Pendiente por Cobrar -->
    <div class="stat-card">
        <div class="stat-icon icon-cobrar">
            <i class="fas fa-hand-holding-usd"></i>
        </div>
        <div class="stat-info">
            <h2>$<?php echo number_format($monto_por_cobrar, 2); ?></h2>
            <p>Pendiente por Cobrar</p>
        </div>
    </div>

    <!-- Alertas Pendientes -->
    <div class="stat-card">
        <div class="stat-icon icon-alert">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h2><?php echo $alertas_pendientes; ?></h2>
            <p>Alertas Pendientes</p>
        </div>
    </div>

</div>

<!-- 
  SECCIÓN DE REPARACIONES RECIENTES
-->
<div class="content-box">
    <h2>Reparaciones Abiertas (Vista Rápida)</h2>
    <table class="repair-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Equipo</th>
                <th>Problema</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <!-- Los datos vendrían de un loop de PHP -->
            <tr>
                <td>1024</td>
                <td>Ana García</td>
                <td>iPhone 12</td>
                <td>Pantalla rota</td>
                <td><span class="status status-progress">En Progreso</span></td>
            </tr>
            <tr>
                <td>1023</td>
                <td>Carlos López</td>
                <td>Dell XPS 15</td>
                <td>No enciende</td>
                <td><span class="status status-pending">En Diagnóstico</span></td>
            </tr>
            <tr>
                <td>1022</td>
                <td>María Fernández</td>
                <td>Samsung S21</td>
                <td>Batería no carga</td>
                <td><span class="status status-wait">Esperando Pieza</span></td>
            </tr>
        </tbody>
    </table>
</div>

<?php
// Incluimos el footer
include 'templates/footer.php';
?>

