<?php
require '../../Fpdf.php';

class PDF extends Fpdf
{
function Header()
{
	global $title;

	// Arial bold 15
	$this->setFont('Arial','B',15);
	// Calculate width of title and position
	$w = $this->getStringWidth($title)+6;
	$this->setX((210-$w)/2);
	// Colors of frame, background and text
	$this->setDrawColor(0,80,180);
	$this->setFillColor(230,230,0);
	$this->setTextColor(220,50,50);
	// Thickness of frame (1 mm)
	$this->setLineWidth(1);
	// Title
	$this->cell($w,9,$title,1,1,'C',true);
	// Line break
	$this->ln(10);
}

function Footer()
{
	// Position at 1.5 cm from bottom
	$this->setY(-15);
	// Arial italic 8
	$this->setFont('Arial','I',8);
	// Text color in gray
	$this->setTextColor(128);
	// Page number
	$this->cell(0,10,'Page '.$this->PageNo(),0,0,'C');
}

function ChapterTitle($num, $label)
{
	// Arial 12
	$this->setFont('Arial','',12);
	// Background color
	$this->setFillColor(200,220,255);
	// Title
	$this->cell(0,6,"Chapter $num : $label",0,1,'L',true);
	// Line break
	$this->ln(4);
}

function ChapterBody($file)
{
	// Read text file
	$txt = file_get_contents($file);
	// Times 12
	$this->setFont('Times','',12);
	// Output justified text
	$this->MultiCell(0,5,$txt);
	// Line break
	$this->ln();
	// Mention in italics
	$this->setFont('','I');
	$this->cell(0,5,'(end of excerpt)');
}

function PrintChapter($num, $title, $file)
{
	$this->addPage();
	$this->ChapterTitle($num,$title);
	$this->ChapterBody($file);
}
}

$pdf = new PDF();
$title = '20000 Leagues Under the Seas';
$pdf->setTitle($title);
$pdf->setAuthor('Jules Verne');
$pdf->PrintChapter(1,'A RUNAWAY REEF','20k_c1.txt');
$pdf->PrintChapter(2,'THE PROS AND CONS','20k_c2.txt');
$pdf->output();
?>
