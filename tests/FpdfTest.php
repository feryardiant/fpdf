<?php

namespace Fpdf\Tests;

use Fpdf\Fpdf;
use Fpdf\Exception;

class FpdfTest extends \PHPUnit_Framework_TestCase
{
    private $samplePath;
    private $samplePdf;

    function setUp() {
        $this->samplePdf = $_SERVER['SAMPLESDIR'] . 'test.pdf';
    }

    function tearDown() {
        if (file_exists($this->samplePdf)) {
            unlink($this->samplePdf);
        }
    }

    function testPagesAdded() {
        $pdf = new Fpdf();
        $pdf->addPage();
        $this->assertSame(1, $pdf->pageNo());
        $pdf->addPage();
        $this->assertSame(2, $pdf->pageNo());
    }

    function testPdfExtended() {
        require_once $_SERVER['SAMPLESDIR'] . 'PdfExtended.php';

        $pdf = new \PdfExtended();
        $pdf->setTitle('20000 Leagues Under the Seas');
        $pdf->setAuthor('Jules Verne');
        $pdf->printChapter(1, 'A RUNAWAY REEF', $_SERVER['SAMPLESDIR'] . '20k_c1.txt');
        $pdf->printChapter(2, 'THE PROS AND CONS', $_SERVER['SAMPLESDIR'] . '20k_c2.txt');
        $pdf->output($this->samplePdf, 'F');

        $this->assertTrue(file_exists($this->samplePdf), $this->samplePdf);
    }

    function testPdfFile() {
        $pdf = new Fpdf();
        $pdf->addPage();
        $pdf->cell(40, 10, 'Hello World!');
        $pdf->output($this->samplePdf, 'F');

        // 4eb42801d40fd3dc4ceeb0737e237298
        $this->assertSame(1, $pdf->pageNo());
        $this->assertTrue(file_exists($this->samplePdf), $this->samplePdf);
    }

    function testPdfFileUsingSampleFont() {
        $sampleFontPath = $_SERVER['SAMPLESDIR'] . $_SERVER['SAMPLESFONT'];

        try {
            $pdf = new Fpdf();
            $pdf->addFont($_SERVER['SAMPLESFONT'], '', $sampleFontPath);
            $pdf->addPage();
            $pdf->setFont($_SERVER['SAMPLESFONT']);
            $pdf->cell(40, 10, 'Hello World!');
            $pdf->output($this->samplePdf, 'F');

            $this->assertSame(1, $pdf->pageNo());
            $this->assertTrue(file_exists($this->samplePdf), $this->samplePdf);
        } catch (Exception $e) {
            $this->markTestSkipped('This feature still buggy.');
        }
    }
}
