<?php
// 1. Incluimos header y conexión
include 'templates/header.php';
include 'config/conexion.php';

try {
    // --- CONSULTAS GENERALES ---
    $sqlAbiertas = "SELECT COUNT(*) FROM reparaciones WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar')";
    $reparaciones_abiertas = $conn->query($sqlAbiertas)->fetchColumn();

    $sqlIngresos = "SELECT COALESCE(SUM(ingreso), 0) FROM caja_movimientos WHERE DATE(fecha) = CURDATE()";
    $ingresos_dia = $conn->query($sqlIngresos)->fetchColumn();

    $sqlEntregas = "SELECT COUNT(*) FROM reparaciones 
                    WHERE estado NOT IN ('Entregado', 'Cancelado') 
                    AND DATE(fecha_estimada) = CURDATE()";
    $entregas_hoy = $conn->query($sqlEntregas)->fetchColumn();

    // --- SISTEMA DE ALERTAS ---
    $sqlVencidas = "SELECT id, nombre_cliente, modelo, fecha_estimada 
                    FROM reparaciones 
                    WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar') 
                    AND fecha_estimada IS NOT NULL 
                    AND fecha_estimada < NOW()";
    $lista_vencidas = $conn->query($sqlVencidas)->fetchAll(PDO::FETCH_ASSOC);

    $sqlPorVencer = "SELECT id, nombre_cliente, modelo, fecha_estimada 
                     FROM reparaciones 
                     WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar') 
                     AND fecha_estimada > NOW() 
                     AND fecha_estimada <= DATE_ADD(NOW(), INTERVAL 48 HOUR)
                     ORDER BY fecha_estimada ASC";
    $lista_por_vencer = $conn->query($sqlPorVencer)->fetchAll(PDO::FETCH_ASSOC);

    // Alerta AMARILLA: Stock Bajo (Solo Productos, no mercancías)
    $sqlStock = "SELECT id_productos, nombre_producto, cantidad_piezas 
                 FROM productos 
                 WHERE cantidad_piezas < 3";
    $lista_stock = $conn->query($sqlStock)->fetchAll(PDO::FETCH_ASSOC);

    $total_alertas = count($lista_vencidas) + count($lista_por_vencer) + count($lista_stock);

    $sqlRecientes = "SELECT id, nombre_cliente, modelo, tipo_reparacion, estado 
                     FROM reparaciones 
                     WHERE estado NOT IN ('Entregado', 'Cancelado')
                     ORDER BY id DESC LIMIT 5";
    $lista_recientes = $conn->query($sqlRecientes)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en el Dashboard: " . $e->getMessage());
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

    <div class="stat-card <?php echo ($total_alertas > 0) ? 'stat-card-alert' : ''; ?>">
        <div class="stat-icon icon-alert <?php echo ($total_alertas > 0) ? 'stat-icon-alert-active' : ''; ?>">
            <i class="fas fa-bell"></i>
        </div>
        <div class="stat-info">
            <h2 class="<?php echo ($total_alertas > 0) ? 'stat-text-alert-active' : ''; ?>">
                <?php echo $total_alertas; ?>
            </h2>
            <p>Alertas Activas</p>
        </div>
    </div>
</div>

<?php if ($total_alertas > 0): ?>
<div class="content-box alert-center-box">
    <h2 class="alert-center-title"><i class="fas fa-exclamation-circle"></i> Centro de Alertas</h2>
    
    <div class="alert-center-grid">
        
        <?php if (count($lista_vencidas) > 0): ?>
        <div class="alert-block-danger">
            <h3>🚨 ¡Atención! Vencidas</h3>
            <table class="repair-table alert-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Venció</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_vencidas as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['nombre_cliente']) ?></td>
                        <td><?= htmlspecialchars($v['modelo']) ?></td>
                        <td class="text-danger-bold">
                            <?= date('d/m H:i', strtotime($v['fecha_estimada'])) ?>
                        </td>
                        <td class="text-align-right">
                            <a href="editar_reparacion.php?id=<?= $v['id'] ?>" class="btn-small">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (count($lista_por_vencer) > 0): ?>
        <div class="alert-block-warning">
            <h3>⏳ Próximas a Entregar</h3>
            <table class="repair-table alert-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Entrega</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_por_vencer as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre_cliente']) ?></td>
                        <td><?= htmlspecialchars($p['modelo']) ?></td>
                        <td class="text-warning-bold">
                            <?php 
                                $fecha = strtotime($p['fecha_estimada']);
                                if (date('Ymd') == date('Ymd', $fecha)) {
                                    echo "Hoy " . date('H:i', $fecha);
                                } else {
                                    echo date('d/m H:i', $fecha);
                                }
                            ?>
                        </td>
                        <td class="text-align-right">
                            <a href="editar_reparacion.php?id=<?= $p['id'] ?>" class="btn-small" style="background-color: #dd6b20;">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (count($lista_stock) > 0): ?>
        <div class="alert-block-info">
            <h3>📦 Productos Agotándose</h3>
            
            <div class="stock-alert-container">
                <p>Hay <strong><?= count($lista_stock) ?></strong> productos con poco inventario.</p>
                <button id="btn-ver-stock" class="btn-small btn-stock-view" onclick="toggleStock()">
                    Ver Lista <i class="fas fa-chevron-down"></i>
                </button>
            </div>

            <div id="tabla-stock" style="display: none;">
                <table class="repair-table alert-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Stock</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista_stock as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['nombre_producto']) ?></td>
                            <td class="text-warning-bold text-align-center">
                                <?= $s['cantidad_piezas'] ?>
                            </td>
                            <td class="text-align-right">
                                <a href="productos.php" class="btn-small" style="background-color: #d69e2e;">Surtir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="stock-alert-container">
                    <button class="btn-small btn-stock-hide" onclick="toggleStock()">
                        Ocultar Lista <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
            </div>
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
        <p class="text-align-center" style="color: #666; padding: 20px;">No hay movimientos recientes.</p>
    <?php endif; ?>
</div>

<script>
function toggleStock() {
    var tabla = document.getElementById('tabla-stock');
    var btn = document.getElementById('btn-ver-stock');
    
    if (tabla.style.display === 'none') {
        tabla.style.display = 'block';
        btn.style.display = 'none';
    } else {
        tabla.style.display = 'none';
        btn.style.display = 'inline-block';
    }
}
</script>

<?php include 'templates/footer.php'; ?>