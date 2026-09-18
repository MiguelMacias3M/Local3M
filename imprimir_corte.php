<?php
session_start();
if (!isset($_SESSION['nombre'])) {
    die("Acceso denegado");
}

require 'config/conexion.php';
// Requerimos la librería FPDF que ya tienes en tu sistema
require 'lib/fpdf185/fpdf.php'; 

$id_cierre = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener los datos del cierre
$stmt = $conn->prepare("SELECT * FROM caja_cierres WHERE id = ?");
$stmt->execute([$id_cierre]);
$cierre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cierre) {
    die("Error: Corte de caja no encontrado.");
}

// Configuración para Impresora Térmica de 58mm
// Ancho 58mm, alto automático (ponemos 150 y si sobra lo corta)
$pdf = new FPDF('P', 'mm', array(58, 150)); 
$pdf->AddPage();
$pdf->SetMargins(3, 2, 3);
$pdf->SetAutoPageBreak(true, 2);

// --- ENCABEZADO ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 5, utf8_decode('3M TECHNOLOGY'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, utf8_decode('Corte de Turno #' . $cierre['id']), 0, 1, 'C');
$pdf->Cell(0, 4, utf8_decode('Fecha: ' . date('d/m/Y h:i A', strtotime($cierre['fecha_cierre']))), 0, 1, 'C');
$pdf->Cell(0, 4, utf8_decode('Cajero: ' . $cierre['usuario_cierre']), 0, 1, 'C');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C'); // Línea punteada/continua
$pdf->Ln(2);

// --- RESUMEN DEL SISTEMA ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, utf8_decode('RESUMEN DEL SISTEMA'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);

$pdf->Cell(28, 4, utf8_decode('Fondo Inicial:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($cierre['saldo_inicial'], 2), 0, 1, 'R');

$pdf->Cell(28, 4, utf8_decode('(+) Ingresos Fís.:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($cierre['ingresos_sistema'], 2), 0, 1, 'R');

$pdf->Cell(28, 4, utf8_decode('(-) Salidas/Ret.:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($cierre['egresos_sistema'], 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(28, 5, utf8_decode('Efectivo Teórico:'), 0, 0, 'L');
$pdf->Cell(24, 5, '$' . number_format($cierre['saldo_teorico'], 2), 0, 1, 'R');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C');
$pdf->Ln(2);

// --- ARQUEO FÍSICO ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, utf8_decode('ARQUEO FÍSICO'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);

$pdf->Cell(28, 4, utf8_decode('Efectivo Contado:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($cierre['saldo_real_contado'], 2), 0, 1, 'R');

$diferencia = (float)$cierre['diferencia'];
$textoDif = ($diferencia < 0) ? 'FALTANTE:' : (($diferencia > 0) ? 'SOBRANTE:' : 'Diferencia:');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(28, 4, utf8_decode($textoDif), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($diferencia, 2), 0, 1, 'R');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C');
$pdf->Ln(2);

// --- DISTRIBUCIÓN FINAL ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, utf8_decode('DISTRIBUCIÓN FINAL'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);

$pdf->Cell(28, 4, utf8_decode('Fondo p/ Mañana:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($cierre['fondo_siguiente_dia'], 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(28, 4, utf8_decode('Retiro Ganancia:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($cierre['retiro_ganancia'], 2), 0, 1, 'R');

if (!empty($cierre['notas'])) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->MultiCell(0, 3, utf8_decode('Nota: ' . $cierre['notas']), 0, 'C');
}

$pdf->Ln(6);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(0, 4, utf8_decode('*** TURNO CERRADO ***'), 0, 1, 'C');

// Imprimir PDF
$pdf->Output('I', 'Corte_Turno_' . $cierre['id'] . '.pdf');
?>