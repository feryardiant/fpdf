<?php

namespace Fpdf;

class FpdfTest extends \PHPUnit_Framework_TestCase {
    private $samplePath;
    private $sampleFont = 'Arial';

    function setUp() {
        $this->samplePath = dirname(__FILE__) . '/samples/';
    }

    function testMakeFont() {
        $fontpath = $this->samplePath;
        $basename = $fontpath . $this->sampleFont;
        $fontfile = $basename . '.ttf';

        unlink($basename . '.php');
        unlink($basename . '.z');

        defined('FPDF_FONTPATH') || define('FPDF_FONTPATH', $fontpath);
        $msg = MakeFont::make($fontfile);

        $this->assertTrue(file_exists($fontfile));
        $this->assertTrue(file_exists($basename . '.php'));

        if (function_exists('gzcompress')) {
            $this->assertTrue(file_exists($basename . '.z'));
        } else {
            $this->assertFalse(file_exists($basename . '.z'));
        }
    }

    function testPagesAdded() {
        $pdf = new Fpdf();
        $pdf->addPage();
        $this->assertSame(1, $pdf->pageNo());
        $pdf->addPage();
        $this->assertSame(2, $pdf->pageNo());
    }

    function testPdfFileUsingCoreFont() {
        if (file_exists($filepath = $this->samplePath . 'test.pdf')) {
            unlink($filepath);
        }

        $pdf = new Fpdf();
        $pdf->addPage();
        $pdf->cell(40, 10, 'Hello World!');
        $pdf->output($filepath, 'F');

        // 4eb42801d40fd3dc4ceeb0737e237298
        $this->assertSame(1, $pdf->pageNo());
        $this->assertTrue(file_exists($filepath), $filepath);
    }

    function testPdfFileUsingSampleFont() {
        if (file_exists($filepath = $this->samplePath . 'test.pdf')) {
            unlink($filepath);
        }

        defined('FPDF_FONTPATH') || define('FPDF_FONTPATH', $fontpath);
        $pdf = new Fpdf();
        $pdf->addFont($this->sampleFont, '', $this->sampleFont . '.php');
        $pdf->addPage();
        $pdf->setFont($this->sampleFont);
        $pdf->cell(40, 10, 'Hello World!');
        $pdf->output($filepath, 'F');

        // 4eb42801d40fd3dc4ceeb0737e237298
        $this->assertSame(1, $pdf->pageNo());
        $this->assertTrue(file_exists($filepath), $filepath);
    }
}
