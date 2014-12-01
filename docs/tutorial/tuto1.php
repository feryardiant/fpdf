<?php
require '../../Fpdf.php';

$pdf = new Fpdf();
$pdf->addPage();
$pdf->setFont('Arial','B',16);
$pdf->cell(40,10,'Hello World!');
$pdf->output();
?>
