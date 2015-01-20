<?php
require '../../fpdf.php';

$pdf = new FPDF();
$pdf->addPage()
    ->setFont('Arial','B',16)
    ->cell(40,10,'Hello World!')
    ->output('Doc.pdf', 'D');
