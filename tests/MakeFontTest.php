<?php

namespace Fpdf;

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
}
