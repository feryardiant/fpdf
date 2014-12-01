<?php
define('FPDF_FONTPATH','.');
require '../../Fpdf.php';

$pdf = new Fpdf();
$pdf->addFont('Calligrapher','','calligra.php');
$pdf->addPage();
$pdf->setFont('Calligrapher','',35);
$pdf->cell(0,10,'Enjoy new fonts with FPDF!');
$pdf->output();
?>
