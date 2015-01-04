<?php

namespace Fpdf;

class FpdfTest extends \PHPUnit_Framework_TestCase {
    function testPagesAdded() {
        $pdf = new Fpdf();
        $pdf->AddPage();
        $this->assertSame(1, $pdf->pageNo());
    }
}
