<?php

namespace Fpdf;

class FpdfTest extends \PHPUnit_Framework_TestCase {
    private $samplePath;
    private $samplePdf;
    private $sampleFont = 'Arial';

    function setUp() {
        $this->samplePath = dirname(__FILE__) . '/samples/';
        $this->samplePdf = $this->samplePath . 'test.pdf';
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
        require_once $this->samplePath . 'PdfExtended.php';

        $pdf = new \PdfExtended();
        $pdf->setTitle('20000 Leagues Under the Seas');
        $pdf->setAuthor('Jules Verne');
        $pdf->printChapter(1, 'A RUNAWAY REEF', $this->samplePath . '20k_c1.txt');
        $pdf->printChapter(2, 'THE PROS AND CONS', $this->samplePath . '20k_c2.txt');
        $pdf->output($this->samplePdf, 'F');

        $this->assertTrue(file_exists($this->samplePdf), $this->samplePdf);
    }

    function testMakeFont() {
        $basename = $this->samplePath . $this->sampleFont;
        $fontfile = $basename . '.ttf';

        unlink($basename . '.php');
        unlink($basename . '.z');

        defined('FPDF_FONTPATH') || define('FPDF_FONTPATH', $this->samplePath);
        $msg = MakeFont::make($fontfile);

        $this->assertTrue(file_exists($fontfile));
        $this->assertTrue(file_exists($basename . '.php'));

        if (function_exists('gzcompress')) {
            $this->assertTrue(file_exists($basename . '.z'));
        } else {
            $this->assertFalse(file_exists($basename . '.z'));
        }
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
        $pdf = new Fpdf();
        $pdf->addFont($this->sampleFont, '', $this->sampleFont . '.php');
        $pdf->addPage();
        $pdf->setFont($this->sampleFont);
        $pdf->cell(40, 10, 'Hello World!');
        $pdf->output($this->samplePdf, 'F');

        // 4eb42801d40fd3dc4ceeb0737e237298
        $this->assertSame(1, $pdf->pageNo());
        $this->assertTrue(file_exists($this->samplePdf), $this->samplePdf);
    }

    function testAddInvalidFont() {
        $message = 'Could not load font definition ' . $this->samplePath . 'Foobar.php';
        $this->setExpectedException('\RuntimeException', $message);

        $pdf = new Fpdf();
        $pdf->addFont('Foobar', '', 'Foobar.php');
    }

    function testLoadInvalidFont() {
        $this->setExpectedException('\RuntimeException', 'Invalid font definition file');

        $pdf = new Fpdf();
        $pdf->addFont('foobar-font', '', 'foobar-font.php');
    }
}
