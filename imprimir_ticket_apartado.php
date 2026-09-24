<?php
session_start();
if (!isset($_SESSION['nombre'])) {
    die("Acceso denegado");
}

require 'config/conexion.php';
require 'lib/fpdf185/fpdf.php'; 

$id_equipo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener los datos del equipo desde la vitrina
$stmt = $conn->prepare("SELECT * FROM vitrina WHERE id = ?");
$stmt->execute([$id_equipo]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    die("Error: Equipo no encontrado.");
}

// Matemáticas del apartado
$precio_total = (float)$equipo['precio_venta'];
$saldo_restante = (float)$equipo['saldo_restante'];
// Lo que ha abonado hasta ahora es el Precio Total menos lo que aún debe
$total_abonado = $precio_total - $saldo_restante;

// Nombre ensamblado del equipo
$nombre_equipo = trim($equipo['marca'] . ' ' . $equipo['modelo'] . ' ' . $equipo['color']);

// Configuración para Impresora Térmica de 58mm
$pdf = new FPDF('P', 'mm', array(58, 200)); 
$pdf->AddPage();
$pdf->SetMargins(3, 2, 3);
$pdf->SetAutoPageBreak(true, 2);

// --- ENCABEZADO ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 5, utf8_decode('3M TECHNOLOGY'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 4, utf8_decode('TICKET DE APARTADO'), 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, utf8_decode('Fecha: ' . date('d/m/Y h:i A')), 0, 1, 'C');
$pdf->Cell(0, 4, utf8_decode('Atendió: ' . $_SESSION['nombre']), 0, 1, 'C');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C'); // Línea separadora
$pdf->Ln(2);

// --- DATOS DEL CLIENTE ---
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 4, utf8_decode('DATOS DEL CLIENTE:'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 4, utf8_decode('Nombre: ' . ($equipo['cliente_nombre'] ? $equipo['cliente_nombre'] : 'Mostrador')), 0, 'L');
$pdf->Cell(0, 4, utf8_decode('Teléfono: ' . ($equipo['cliente_telefono'] ? $equipo['cliente_telefono'] : 'N/A')), 0, 1, 'L');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C');
$pdf->Ln(2);

// --- DATOS DEL EQUIPO ---
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 4, utf8_decode('ARTÍCULO APARTADO:'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 4, utf8_decode($nombre_equipo), 0, 'L');
$pdf->Cell(0, 4, utf8_decode('IMEI/Serie: ' . $equipo['imei_serie']), 0, 1, 'L');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C');
$pdf->Ln(2);

// --- ESTADO DE CUENTA ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, utf8_decode('ESTADO DE CUENTA'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);

$pdf->Cell(28, 4, utf8_decode('Precio Total:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($precio_total, 2), 0, 1, 'R');

$pdf->Cell(28, 4, utf8_decode('Pagado/Abonado:'), 0, 0, 'L');
$pdf->Cell(24, 4, '$' . number_format($total_abonado, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(28, 5, utf8_decode('SALDO PENDIENTE:'), 0, 0, 'L');
$pdf->Cell(24, 5, '$' . number_format($saldo_restante, 2), 0, 1, 'R');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C');
$pdf->Ln(3);

// --- TÉRMINOS Y CONDICIONES ---
$pdf->SetFont('Arial', 'I', 7);
$terminos = "Nota: Este es un comprobante de apartado. Se requiere conservar este ticket para futuras aclaraciones o pagos.\n\nTiene un plazo maximo de 30 dias para liquidar el equipo. Pasado este tiempo, el equipo podria ser puesto a la venta nuevamente.";
$pdf->MultiCell(0, 3, utf8_decode($terminos), 0, 'C');

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 4, utf8_decode('¡Gracias por tu preferencia!'), 0, 1, 'C');

// Imprimir PDF
$pdf->Output('I', 'Ticket_Apartado_' . $equipo['imei_serie'] . '.pdf');
?>