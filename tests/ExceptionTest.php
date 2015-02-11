<?php

namespace Fpdf;

class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    private $_pref = 'Fpdf Error: ';

    function testAddInvalidFont() {
        $message = $this->_pref . 'Could not load font definition Foobar.php';
        $this->setExpectedException('Exception', $message);

        $pdf = new Fpdf();
        $pdf->addFont('Foobar', '', 'Foobar');
    }

    function testLoadInvalidFont() {
        $message = $this->_pref . 'Invalid font definition file';
        $this->setExpectedException('Exception', $message);

        $pdf = new Fpdf();
        $pdf->addFont('foobar-font', '', $_SERVER['SAMPLESDIR'] . 'foobar-font');
    }
}
