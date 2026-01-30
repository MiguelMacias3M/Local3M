<?php
// 1. Incluimos header y conexión
include 'templates/header.php';
include 'config/conexion.php';

try {
    // --- CONSULTAS GENERALES ---
    
    // A) Reparaciones Abiertas (Pendientes en taller)
    $sqlAbiertas = "SELECT COUNT(*) FROM reparaciones WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar')";
    $reparaciones_abiertas = $conn->query($sqlAbiertas)->fetchColumn();

    // B) Ingresos del Día
    $sqlIngresos = "SELECT COALESCE(SUM(ingreso), 0) FROM caja_movimientos WHERE DATE(fecha) = CURDATE()";
    $ingresos_dia = $conn->query($sqlIngresos)->fetchColumn();

    // C) Entregas para Hoy (Prometidas para hoy)
    $sqlEntregas = "SELECT COUNT(*) FROM reparaciones 
                    WHERE estado NOT IN ('Entregado', 'Cancelado') 
                    AND DATE(fecha_estimada) = CURDATE()";
    $entregas_hoy = $conn->query($sqlEntregas)->fetchColumn();


    // --- SISTEMA DE ALERTAS (NUEVO) ---

    // D1. Reparaciones VENCIDAS (Fecha estimada ya pasó y NO se ha entregado)
    $sqlVencidas = "SELECT id, nombre_cliente, modelo, fecha_estimada 
                    FROM reparaciones 
                    WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar') 
                    AND fecha_estimada IS NOT NULL 
                    AND fecha_estimada < NOW()";
    $lista_vencidas = $conn->query($sqlVencidas)->fetchAll(PDO::FETCH_ASSOC);

    // D2. Stock Bajo (Menos de 3 piezas)
    $sqlStock = "SELECT descripcion, cantidad FROM mercancia WHERE cantidad < 3";
    $lista_stock = $conn->query($sqlStock)->fetchAll(PDO::FETCH_ASSOC);

    // Total de Alertas Activas
    $total_alertas = count($lista_vencidas) + count($lista_stock);


    // E) Lista Reciente (Últimos movimientos)
    $sqlRecientes = "SELECT id, nombre_cliente, modelo, tipo_reparacion, estado 
                     FROM reparaciones 
                     WHERE estado NOT IN ('Entregado', 'Cancelado')
                     ORDER BY id DESC LIMIT 5";
    $lista_recientes = $conn->query($sqlRecientes)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Evitar errores visibles si falla la BD
    $reparaciones_abiertas = 0; $ingresos_dia = 0; $entregas_hoy = 0; $total_alertas = 0;
    $lista_vencidas = []; $lista_stock = []; $lista_recientes = [];
}
?>

<div class="page-title">
    <h1>Panel Principal</h1>
    <p>Resumen general de tu taller.</p>
</div>

<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon icon-repair">
            <i class="fas fa-tools"></i>
        </div>
        <div class="stat-info">
            <h2><?php echo $reparaciones_abiertas; ?></h2>
            <p>En Taller</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon icon-money">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-info">
            <h2>$<?php echo number_format($ingresos_dia, 2); ?></h2>
            <p>Ingresos Hoy</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon icon-entrega">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-info">
            <h2><?php echo $entregas_hoy; ?></h2>
            <p>Para Hoy</p>
        </div>
    </div>

    <div class="stat-card" style="<?php echo ($total_alertas > 0) ? 'border: 2px solid #dc3545;' : ''; ?>">
        <div class="stat-icon icon-alert" style="<?php echo ($total_alertas > 0) ? 'background:#ffe6e6; color:#dc3545;' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h2 style="<?php echo ($total_alertas > 0) ? 'color:#dc3545;' : ''; ?>">
                <?php echo $total_alertas; ?>
            </h2>
            <p>Alertas Activas</p>
        </div>
    </div>

</div>

<?php if ($total_alertas > 0): ?>
<div class="content-box" style="border-left: 5px solid #dc3545;">
    <h2 style="color: #dc3545; margin-bottom: 1rem;"><i class="fas fa-bell"></i> Atención Requerida</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        
        <?php if (count($lista_vencidas) > 0): ?>
        <div>
            <h3 style="font-size: 1rem; margin-bottom: 10px; color: #721c24;">🚨 Reparaciones Atrasadas</h3>
            <table class="repair-table" style="font-size: 0.9rem;">
                <thead>
                    <tr style="background: #ffe6e6;">
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Venció</th>
                        <th>Ir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_vencidas as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['nombre_cliente']) ?></td>
                        <td><?= htmlspecialchars($v['modelo']) ?></td>
                        <td style="color: #dc3545; font-weight: bold;">
                            <?= date('d/m H:i', strtotime($v['fecha_estimada'])) ?>
                        </td>
                        <td>
                            <a href="editar_reparacion.php?id=<?= $v['id'] ?>" class="btn-small">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (count($lista_stock) > 0): ?>
        <div>
            <h3 style="font-size: 1rem; margin-bottom: 10px; color: #856404;">📦 Stock Crítico</h3>
            <table class="repair-table" style="font-size: 0.9rem;">
                <thead>
                    <tr style="background: #fff3cd;">
                        <th>Producto</th>
                        <th>Quedan</th>
                        <th>Ir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_stock as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['descripcion']) ?></td>
                        <td style="font-weight: bold; color: #856404; text-align: center;">
                            <?= $s['cantidad'] ?>
                        </td>
                        <td style="text-align:center;">
                            <a href="mercancia.php" class="btn-small">Surtir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>
<?php endif; ?>

<div class="content-box">
    <h2>Reparaciones Recientes</h2>
    <?php if (count($lista_recientes) > 0): ?>
    <table class="repair-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Equipo</th>
                <th>Problema</th>
                <th>Estado</th>
                <th>Ver</th>
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
                        $estadoClass = 'status-unknown';
                        $est = strtolower($fila['estado']);
                        if (strpos($est, 'espera') !== false) $estadoClass = 'status-wait';
                        elseif (strpos($est, 'revision') !== false || strpos($est, 'diagnosticado') !== false) $estadoClass = 'status-pending';
                        elseif (strpos($est, 'progreso') !== false) $estadoClass = 'status-progress';
                        elseif (strpos($est, 'reparado') !== false) $estadoClass = 'status-ready';
                    ?>
                    <span class="status <?php echo $estadoClass; ?>"><?php echo htmlspecialchars($fila['estado']); ?></span>
                </td>
                <td><a href="editar_reparacion.php?id=<?= $fila['id'] ?>" class="btn-small"><i class="fas fa-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">No hay movimientos recientes.</p>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>