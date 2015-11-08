<?php
namespace Fpdf\Tests;

// use Fpdf\Fpdf;

class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    private $_pref = 'Fpdf Error: ';

    function testAddInvalidFont() {
        $message = $this->_pref . 'Could not load font definition Foobar.php';
        $this->setExpectedException('Fpdf\Exception', $message);

        $pdf = new \Fpdf\Fpdf();
        $pdf->addFont('Foobar', '', 'Foobar');
    }

    function testLoadInvalidFont() {
        $message = $this->_pref . 'Invalid font definition file';
        $this->setExpectedException('Fpdf\Exception', $message);

        $pdf = new \Fpdf\Fpdf();
        $pdf->addFont('foobar-font', '', $_SERVER['SAMPLESDIR'] . 'foobar-font');
    }
}
