<?php

namespace Fpdf;

class FpdfTest extends \PHPUnit_Framework_TestCase {
    function testPagesAdded() {
        $pdf = new Fpdf();
        $pdf->addPage();
        $this->assertSame(1, $pdf->pageNo());
    }
}
