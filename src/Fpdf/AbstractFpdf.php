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
        $fontpath;

    protected function _validateFontpath() {
        if (defined('FPDF_FONTPATH')) {
            $fontpath = FPDF_FONTPATH;
        } elseif (is_dir(dirname(__FILE__) . DIRECTORY_SEPARATOR . self::FONT_PATH)) {
            $fontpath = dirname(__FILE__) . DIRECTORY_SEPARATOR . self::FONT_PATH;
        } else {
            $fontpath = '';
        }

        if ($fontpath != '') {
            $fontpath = str_replace('/', DIRECTORY_SEPARATOR, $fontpath);
            $this->fontpath = rtrim($fontpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
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
