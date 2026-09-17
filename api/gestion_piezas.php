<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['nombre'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/conexion.php';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // 1. BUSCAR PIEZA EN EL INVENTARIO (AHORA TRAE EL CÓDIGO)
    if ($action === 'buscar_mercancia') {
        $q = $_GET['q'] ?? '';
        // Agregamos codigo_barras al SELECT
        $stmt = $conn->prepare("SELECT id, tipo_repuesto, marca, modelo, costo, cantidad, codigo_barras FROM mercancia WHERE (estado = 'Activo' OR estado IS NULL) AND cantidad > 0 AND (LOWER(tipo_repuesto) LIKE ? OR LOWER(modelo) LIKE ? OR codigo_barras LIKE ?) LIMIT 15");
        $term = "%" . strtolower($q) . "%";
        $stmt->execute([$term, $term, $term]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    // 2. AGREGAR PIEZA A LA REPARACIÓN
    if ($action === 'agregar_pieza') {
        $id_rep = (int)$_POST['id_reparacion'];
        $id_mer = (int)$_POST['id_mercancia'];

        $conn->beginTransaction();

        // Agregamos codigo_barras al SELECT
        $stmtMer = $conn->prepare("SELECT tipo_repuesto, marca, modelo, costo, cantidad, codigo_barras FROM mercancia WHERE id = ?");
        $stmtMer->execute([$id_mer]);
        $pieza = $stmtMer->fetch(PDO::FETCH_ASSOC);

        if (!$pieza || $pieza['cantidad'] <= 0) {
            throw new Exception("Stock insuficiente.");
        }

        // Construimos el nombre con el código entre corchetes
        $codigoTexto = !empty($pieza['codigo_barras']) ? $pieza['codigo_barras'] : 'S/C';
        $nombreCompleto = $pieza['tipo_repuesto'] . ' ' . $pieza['marca'] . ' ' . $pieza['modelo'] . ' [' . $codigoTexto . ']';
        $costoReal = (float)$pieza['costo'];

        // Insertar en la tabla puente
        $stmtIns = $conn->prepare("INSERT INTO reparacion_piezas (id_reparacion, id_mercancia, nombre_pieza, costo_unitario) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$id_rep, $id_mer, $nombreCompleto, $costoReal]);

        // Descontar del inventario general
        $stmtStock = $conn->prepare("UPDATE mercancia SET cantidad = cantidad - 1 WHERE id = ?");
        $stmtStock->execute([$id_mer]);

        // Sumar el costo a la reparación
        $stmtCosto = $conn->prepare("UPDATE reparaciones SET costo_piezas = costo_piezas + ? WHERE id = ?");
        $stmtCosto->execute([$costoReal, $id_rep]);

        // Guardar en el historial
        $stmtHist = $conn->prepare("INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, (SELECT estado FROM reparaciones WHERE id = ?), ?, ?)");
        $stmtHist->execute([$id_rep, $id_rep, "Se agregó pieza: " . $nombreCompleto . " (Stock descontado)", $_SESSION['nombre']]);

        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }

    // 3. LISTAR PIEZAS USADAS EN ESTA REPARACIÓN
    if ($action === 'listar_piezas') {
        $id_rep = (int)$_GET['id_reparacion'];
        
        $stmt = $conn->prepare("SELECT * FROM reparacion_piezas WHERE id_reparacion = ?");
        $stmt->execute([$id_rep]);
        $piezas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtRep = $conn->prepare("SELECT monto, costo_piezas FROM reparaciones WHERE id = ?");
        $stmtRep->execute([$id_rep]);
        $finanzas = $stmtRep->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'piezas' => $piezas, 'finanzas' => $finanzas]);
        exit();
    }

    // 4. ELIMINAR PIEZA DE LA REPARACIÓN (Y DEVOLVER STOCK)
    if ($action === 'eliminar_pieza') {
        $id_puente = (int)$_POST['id_puente'];

        $conn->beginTransaction();

        // Obtener datos del registro a eliminar
        $stmtGet = $conn->prepare("SELECT id_reparacion, id_mercancia, nombre_pieza, costo_unitario FROM reparacion_piezas WHERE id = ?");
        $stmtGet->execute([$id_puente]);
        $registro = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            throw new Exception("El registro no existe.");
        }

        $id_rep = $registro['id_reparacion'];
        $id_mer = $registro['id_mercancia'];
        $costoReal = (float)$registro['costo_unitario'];
        $nombreCompleto = $registro['nombre_pieza'];

        // 1. Eliminar de la tabla puente
        $stmtDel = $conn->prepare("DELETE FROM reparacion_piezas WHERE id = ?");
        $stmtDel->execute([$id_puente]);

        // 2. Devolver stock al inventario general (+1)
        $stmtStock = $conn->prepare("UPDATE mercancia SET cantidad = cantidad + 1 WHERE id = ?");
        $stmtStock->execute([$id_mer]);

        // 3. Restar el costo a la reparación para recuperar la ganancia
        $stmtCosto = $conn->prepare("UPDATE reparaciones SET costo_piezas = costo_piezas - ? WHERE id = ?");
        $stmtCosto->execute([$costoReal, $id_rep]);

        // 4. Guardar en el historial que se corrigió el error
        $stmtHist = $conn->prepare("INSERT INTO historial_reparaciones (id_reparacion, estado_nuevo, comentario, usuario_responsable) VALUES (?, (SELECT estado FROM reparaciones WHERE id = ?), ?, ?)");
        $stmtHist->execute([$id_rep, $id_rep, "Se removió la pieza: " . $nombreCompleto . " (Stock devuelto)", $_SESSION['nombre']]);

        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>