<?php

namespace Fpdf\Tests;

use Fpdf\MakeFont;

class MakeFontTest extends \PHPUnit_Framework_TestCase
{
    function testMakeFont() {
        $basename = $_SERVER['SAMPLESDIR'] . $_SERVER['SAMPLESFONT'];
        $fontfile = $basename . '.ttf';

        unlink($basename . '.php');
        unlink($basename . '.z');

        defined('FPDF_FONTPATH') || define('FPDF_FONTPATH', $_SERVER['SAMPLESDIR']);
        $msg = MakeFont::make($fontfile);

        $this->assertTrue(file_exists($fontfile));
        $this->assertTrue(file_exists($basename . '.php'));

        if (function_exists('gzcompress')) {
            $this->assertTrue(file_exists($basename . '.z'));
        } else {
            $this->assertFalse(file_exists($basename . '.z'));
        }
    }

    function testValidationShoudBeInvokedAtConstructor()
    {
        $basename = $_SERVER['SAMPLESDIR'] . $_SERVER['SAMPLESFONT'];
        $fontfile = $basename . '.ttf';

        $mockRuntime = $this->getMock('Fpdf\MakeFont', array('_validateRuntime'), array($fontfile));

        $mockRuntime->expects($this->never())
            ->method('_validateRuntime');

        $mockRuntime = $this->getMock('Fpdf\MakeFont', array('_validateFontpath'), array($fontfile));

        $mockRuntime->expects($this->never())
            ->method('_validateFontpath');
    }
}
