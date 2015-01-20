<?php

namespace Fpdf;

abstract class AbstractFpdf
{
    const
        DRAW_COLOR = '0 G',
        FILL_COLOR = '0 g',
        TEXT_COLOR = '0 g',
        FONT_PATH  = '../fonts';

    protected
        // path containing fonts
        $fontpath,
        $_validZoomMode = array('fullpage', 'fullwidth', 'real', 'default'),
        $_validLayoutMode = array('single', 'continuous', 'two', 'default');

    protected function _validateRuntime() {
        // Check availability of %F
        if (sprintf('%.1F', 1.0) != '1.0') {
            $this->error('This version of PHP is not supported');
        }

        // Check mbstring overloading
        if (ini_get('mbstring.func_overload') & 2) {
            $this->error('mbstring overloading must be disabled');
        }

        // Ensure runtime magic quotes are disabled
        if (get_magic_quotes_runtime()) {
            @set_magic_quotes_runtime(0);
        }

        ini_set('auto_detect_line_endings', 1);
    }

    protected function _validateFontpath() {
        $_ds = DIRECTORY_SEPARATOR;
        $_fp = dirname(__FILE__) . $_ds . self::FONT_PATH;

        if ($path = getenv('FPDF_FONTPATH')) {
            $fontpath = $path;
        } elseif (defined('FPDF_FONTPATH')) {
            $fontpath = FPDF_FONTPATH;
        } elseif (is_dir($_fp)) {
            $fontpath = $_fp;
        } else {
            $fontpath = '';
        }

        if (is_dir($fontpath)) {
            $fontpath = str_replace('/', $_ds, $fontpath);
            $fontpath = rtrim($fontpath, $_ds) . $_ds;
        } else {
            $this->error('Invalid font path');
        }

        $this->fontpath = $fontpath;
    }

    // Fatal error
    public function error($message) {
        $heading = str_replace('\\', '::', get_class($this)) . ' error:';
        if (PHP_SAPI != 'cli') {
            $heading = "<b>{$heading}</b><br>";
        }

        throw new \RuntimeException($heading . PHP_EOL . $message);
    }
}
