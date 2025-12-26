<?php
// 1. Incluimos al "guardia de seguridad" (header.php)
include 'templates/header.php';
// 2. Incluimos la conexión a la base de datos
include 'config/conexion.php';

// ---- OBTENER DATOS REALES DE LA BASE DE DATOS ----

try {
    // A) Reparaciones Abiertas (Cualquiera que NO esté Entregada o Cancelada)
    $sqlAbiertas = "SELECT COUNT(*) FROM reparaciones WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar')";
    $stmtAbiertas = $conn->query($sqlAbiertas);
    $reparaciones_abiertas = $stmtAbiertas->fetchColumn();

    // B) Ingresos del Día (Suma de ingresos en caja_movimientos HOY)
    $sqlIngresos = "SELECT COALESCE(SUM(ingreso), 0) FROM caja_movimientos WHERE DATE(fecha) = CURDATE()";
    $stmtIngresos = $conn->query($sqlIngresos);
    $ingresos_dia = $stmtIngresos->fetchColumn();

    // C) Entregas para Hoy (Reparaciones marcadas como 'Entregado' HOY)
    // OJO: Si usas un campo 'fecha_prometida', cambia 'fecha_entrega' por 'fecha_prometida'
    // Aquí asumo que quieres ver cuántas se entregaron hoy.
    $sqlEntregas = "SELECT COUNT(*) FROM reparaciones WHERE estado = 'Entregado' AND DATE(fecha_entrega) = CURDATE()";
    $stmtEntregas = $conn->query($sqlEntregas);
    $entregas_hoy = $stmtEntregas->fetchColumn();

    // D) Alertas Pendientes (Ejemplo: Stock bajo en mercancía)
    // Contamos productos con menos de 3 piezas
    $sqlAlertas = "SELECT COUNT(*) FROM mercancia WHERE cantidad < 3";
    $stmtAlertas = $conn->query($sqlAlertas);
    $alertas_pendientes = $stmtAlertas->fetchColumn();


    // E) Lista de Reparaciones Recientes (Últimas 5 no entregadas)
    $sqlRecientes = "SELECT id, nombre_cliente, modelo, tipo_reparacion, estado 
                     FROM reparaciones 
                     WHERE estado NOT IN ('Entregado', 'Cancelado')
                     ORDER BY id DESC LIMIT 5";
    $stmtRecientes = $conn->query($sqlRecientes);
    $lista_recientes = $stmtRecientes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Si falla la BD, mostramos ceros para no romper la página
    $reparaciones_abiertas = 0;
    $ingresos_dia = 0;
    $entregas_hoy = 0;
    $alertas_pendientes = 0;
    $lista_recientes = [];
    // Opcional: mostrar error en log
}
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
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h2><?php echo $entregas_hoy; ?></h2>
            <p>Entregadas Hoy</p>
        </div>
    </div>

    <!-- Alertas Pendientes (Stock Bajo) -->
    <div class="stat-card">
        <div class="stat-icon icon-alert">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h2><?php echo $alertas_pendientes; ?></h2>
            <p>Alertas de Stock</p>
        </div>
    </div>

</div>

<!-- 
  SECCIÓN DE REPARACIONES RECIENTES
-->
<div class="content-box">
    <h2>Reparaciones Pendientes Recientes</h2>
    <?php if (count($lista_recientes) > 0): ?>
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
            <?php foreach ($lista_recientes as $fila): ?>
            <tr>
                <td><?php echo $fila['id']; ?></td>
                <td><?php echo htmlspecialchars($fila['nombre_cliente']); ?></td>
                <td><?php echo htmlspecialchars($fila['modelo']); ?></td>
                <td><?php echo htmlspecialchars($fila['tipo_reparacion']); ?></td>
                <td>
                    <?php 
                        // Asignar clase de color según el estado
                        $estadoClass = 'status-unknown';
                        $est = strtolower($fila['estado']);
                        if (strpos($est, 'espera') !== false) $estadoClass = 'status-wait';
                        elseif (strpos($est, 'revision') !== false || strpos($est, 'diagnosticado') !== false) $estadoClass = 'status-pending';
                        elseif (strpos($est, 'progreso') !== false) $estadoClass = 'status-progress';
                        elseif (strpos($est, 'reparado') !== false) $estadoClass = 'status-ready';
                    ?>
                    <span class="status <?php echo $estadoClass; ?>"><?php echo htmlspecialchars($fila['estado']); ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">No hay reparaciones pendientes por ahora.</p>
    <?php endif; ?>
</div>

<?php
// Incluimos el footer
include 'templates/footer.php';
?>