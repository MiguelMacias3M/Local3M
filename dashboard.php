<?php
// 1. Incluimos header y conexión
include 'templates/header.php';
include 'config/conexion.php';

try {
    // --- CONSULTAS GENERALES ---
    
    // A) Reparaciones en Taller
    $sqlAbiertas = "SELECT COUNT(*) FROM reparaciones WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar')";
    $reparaciones_abiertas = $conn->query($sqlAbiertas)->fetchColumn();

    // B) Ingresos del Día
    $sqlIngresos = "SELECT COALESCE(SUM(ingreso), 0) FROM caja_movimientos WHERE DATE(fecha) = CURDATE()";
    $ingresos_dia = $conn->query($sqlIngresos)->fetchColumn();

    // C) Entregas para Hoy
    $sqlEntregas = "SELECT COUNT(*) FROM reparaciones 
                    WHERE estado NOT IN ('Entregado', 'Cancelado') 
                    AND DATE(fecha_estimada) = CURDATE()";
    $entregas_hoy = $conn->query($sqlEntregas)->fetchColumn();


    // --- SISTEMA DE ALERTAS ---

    // 1. Alerta ROJA: Vencidas
    $sqlVencidas = "SELECT id, nombre_cliente, modelo, fecha_estimada 
                    FROM reparaciones 
                    WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar') 
                    AND fecha_estimada IS NOT NULL 
                    AND fecha_estimada < NOW()";
    $lista_vencidas = $conn->query($sqlVencidas)->fetchAll(PDO::FETCH_ASSOC);

    // 2. Alerta NARANJA: Por Vencer (48h)
    $sqlPorVencer = "SELECT id, nombre_cliente, modelo, fecha_estimada 
                     FROM reparaciones 
                     WHERE estado NOT IN ('Entregado', 'Cancelado', 'No se pudo reparar') 
                     AND fecha_estimada > NOW() 
                     AND fecha_estimada <= DATE_ADD(NOW(), INTERVAL 48 HOUR)
                     ORDER BY fecha_estimada ASC";
    $lista_por_vencer = $conn->query($sqlPorVencer)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Alerta AMARILLA: Stock Bajo (Productos)
    // Buscamos productos con menos de 3 piezas
    $sqlStock = "SELECT id_productos, nombre_producto, cantidad_piezas 
                 FROM productos 
                 WHERE cantidad_piezas < 3";
    $lista_stock = $conn->query($sqlStock)->fetchAll(PDO::FETCH_ASSOC);

    // Total de Alertas
    $total_alertas = count($lista_vencidas) + count($lista_por_vencer) + count($lista_stock);


    // E) Lista Reciente
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

    <div class="stat-card" style="<?php echo ($total_alertas > 0) ? 'border: 2px solid #dc3545;' : ''; ?>">
        <div class="stat-icon icon-alert" style="<?php echo ($total_alertas > 0) ? 'background:#ffe6e6; color:#dc3545;' : ''; ?>">
            <i class="fas fa-bell"></i>
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
    <h2 style="color: #333; margin-bottom: 1.5rem;"><i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> Centro de Alertas</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        
        <?php if (count($lista_vencidas) > 0): ?>
        <div style="background: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px; padding: 15px;">
            <h3 style="font-size: 1rem; margin-top:0; margin-bottom: 10px; color: #c53030;">
                🚨 ¡Atención! Vencidas
            </h3>
            <table class="repair-table" style="font-size: 0.85rem; background: white;">
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
                        <td style="color: #c53030; font-weight: bold;">
                            <?= date('d/m H:i', strtotime($v['fecha_estimada'])) ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="editar_reparacion.php?id=<?= $v['id'] ?>" class="btn-small">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (count($lista_por_vencer) > 0): ?>
        <div style="background: #fffaf0; border: 1px solid #feebc8; border-radius: 8px; padding: 15px;">
            <h3 style="font-size: 1rem; margin-top:0; margin-bottom: 10px; color: #c05621;">
                ⏳ Próximas a Entregar
            </h3>
            <table class="repair-table" style="font-size: 0.85rem; background: white;">
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
                        <td style="color: #c05621; font-weight: bold;">
                            <?php 
                                $fecha = strtotime($p['fecha_estimada']);
                                if (date('Ymd') == date('Ymd', $fecha)) {
                                    echo "Hoy " . date('H:i', $fecha);
                                } else {
                                    echo date('d/m H:i', $fecha);
                                }
                            ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="editar_reparacion.php?id=<?= $p['id'] ?>" class="btn-small" style="background-color: #dd6b20;">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (count($lista_stock) > 0): ?>
        <div style="background: #fffff0; border: 1px solid #fefcbf; border-radius: 8px; padding: 15px;">
            <h3 style="font-size: 1rem; margin-top:0; margin-bottom: 10px; color: #744210;">
                📦 Productos Agotándose
            </h3>
            
            <div style="text-align: center; padding: 10px;">
                <p style="color: #744210; margin-bottom: 10px;">
                    Hay <strong><?= count($lista_stock) ?></strong> productos con poco inventario.
                </p>
                <button id="btn-ver-stock" class="btn-small" style="background-color: #d69e2e; border:none; cursor:pointer;" onclick="toggleStock()">
                    Ver Lista <i class="fas fa-chevron-down"></i>
                </button>
            </div>

            <div id="tabla-stock" style="display: none; margin-top: 15px;">
                <table class="repair-table" style="font-size: 0.85rem; background: white;">
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
                            <td style="font-weight: bold; color: #c05621; text-align: center;">
                                <?= $s['cantidad_piezas'] ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="productos.php" class="btn-small" style="background-color: #d69e2e;">Surtir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 10px;">
                    <button class="btn-small" style="background-color: #ccc; color: #333; border:none; cursor:pointer;" onclick="toggleStock()">
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
        <p style="text-align: center; color: #666; padding: 20px;">No hay movimientos recientes.</p>
    <?php endif; ?>
</div>

<script>
function toggleStock() {
    var tabla = document.getElementById('tabla-stock');
    var btn = document.getElementById('btn-ver-stock');
    
    if (tabla.style.display === 'none') {
        tabla.style.display = 'block';
        btn.style.display = 'none'; // Ocultamos el botón principal al abrir
    } else {
        tabla.style.display = 'none';
        btn.style.display = 'inline-block'; // Mostramos el botón de nuevo
    }
}
</script>

<?php include 'templates/footer.php'; ?>