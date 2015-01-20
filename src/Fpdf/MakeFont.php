<?php

namespace Fpdf;

class MakeFont extends AbstractFpdf
{
    protected
        $fontfile,
        $fontMap,
        $fontInfo = array();

    public function __construct($fontfile, $encoding = 'cp1252') {
        // Some checks
        $this->_validateRuntime();

        if (!file_exists($fontfile)) {
            $this->error('Font file not found: ' . $fontfile);
        }

        // Font path
        $this->_validateFontpath();

        $this->fontfile = $fontfile;
        $this->encoding = $encoding;
        $this->loadMap($this->encoding);
    }

    public static function make($fontfile, $encoding = 'cp1252', $embed = true) {
        $self = new self($fontfile, $encoding);
        return $self->toFile($embed);
    }

    public function toFile($embed = true) {
        $basename = substr(basename($this->fontfile), 0, -4);
        $ext = strtolower(substr($this->fontfile, -3));
        $msg = '';

        if ($ext == 'ttf' || $ext == 'otf') {
            $type = 'TrueType';
            $this->getTrueTypeInfo($embed);
        } elseif ($ext == 'pfb') {
            $type = 'Type1';
            $this->getType1Info($embed);
        } else {
            $this->error('Unrecognized font file extension: ' . $ext);
        }

        if ($embed) {
            if (function_exists('gzcompress')) {
                $file = $basename . '.z';
                $this->saveToFile($file, gzcompress($this->fontInfo['Data']), 'b');
                $this->fontInfo['File'] = $file;
                $msg .= 'Font file compressed: ' . $file . PHP_EOL;
            } else {
                $this->fontInfo['File'] = basename($this->fontfile);
                $msg .= 'Font file could not be compressed (zlib extension not available)' . PHP_EOL;
            }
        }

        if ($this->makeDefinitionFile($basename . '.php', $type, $embed)) {
            $msg .= 'Font definition file generated: ' . $basename . '.php' . PHP_EOL;
            return $msg;
        }

        return false;
    }

    protected function loadMap($encoding) {
        $file = dirname(__FILE__) . '/../maps/' . strtolower($encoding) . '.map';
        $a = file($file);
        if (empty($a)) {
            $this->error('Encoding not found: ' . $encoding);
        }

        $map = array_fill(0, 256, array('uv' => -1, 'name' => '.notdef'));
        foreach ($a as $line) {
            $e       = explode(' ', rtrim($line));
            $c       = hexdec(substr($e[0], 1));
            $uv      = hexdec(substr($e[1], 2));
            $name    = $e[2];
            $map[$c] = array('uv' => $uv, 'name' => $name);
        }

        $this->fontMap = $map;
    }

    protected function getTrueTypeInfo($embed) {
        // Return informations from a TrueType font
        $ttf = new TTFParser($this->fontfile);

        if ($embed) {
            if (!$ttf->Embeddable) {
                $this->error('Font license does not allow embedding');
            }

            $this->fontInfo['Data'] = file_get_contents($this->fontfile);
            $this->fontInfo['OriginalSize'] = filesize($this->fontfile);
        }

        $k = 1000 / $ttf->unitsPerEm;
        $this->fontInfo['FontName'] = $ttf->postScriptName;
        $this->fontInfo['Bold'] = $ttf->Bold;
        $this->fontInfo['ItalicAngle'] = $ttf->italicAngle;
        $this->fontInfo['IsFixedPitch'] = $ttf->isFixedPitch;
        $this->fontInfo['Ascender'] = round($k * $ttf->typoAscender);
        $this->fontInfo['Descender'] = round($k * $ttf->typoDescender);
        $this->fontInfo['UnderlineThickness'] = round($k * $ttf->underlineThickness);
        $this->fontInfo['UnderlinePosition'] = round($k * $ttf->underlinePosition);
        $this->fontInfo['FontBBox'] = array(
            round($k * $ttf->xMin),
            round($k * $ttf->yMin),
            round($k * $ttf->xMax),
            round($k * $ttf->yMax),
        );
        $this->fontInfo['CapHeight'] = round($k * $ttf->capHeight);
        $this->fontInfo['MissingWidth'] = round($k * $ttf->widths[0]);
        $widths = array_fill(0, 256, $this->fontInfo['MissingWidth']);

        for ($c = 0; $c <= 255; $c++) {
            if ($this->fontMap[$c]['name'] != '.notdef') {
                $uv = $this->fontMap[$c]['uv'];

                if (isset($ttf->chars[$uv])) {
                    $w = $ttf->widths[$ttf->chars[$uv]];
                    $widths[$c] = round($k * $w);
                } else {
                    $this->error('Character ' . $this->fontMap[$c]['name'] . ' is missing');
                }
            }
        }

        $this->fontInfo['Widths'] = $widths;
    }

    protected function getType1Info($embed) {
        // Return informations from a Type1 font
        if ($embed) {
            if (!$f = fopen($this->fontfile, 'rb')) {
                $this->error('Can\'t open font file');
            }

            // Read first segment
            $a = unpack('Cmarker/Ctype/Vsize', fread($f, 6));
            if ($a['marker'] != 128) {
                $this->error('Font file is not a valid binary Type1');
            }

            $size1 = $a['size'];
            $data = fread($f, $size1);
            // Read second segment
            $a = unpack('Cmarker/Ctype/Vsize', fread($f, 6));
            if ($a['marker'] != 128) {
                $this->error('Font file is not a valid binary Type1');
            }

            $size2 = $a['size'];
            $data .= fread($f, $size2);
            fclose($f);
            $this->fontInfo['Data'] = $data;
            $this->fontInfo['Size1'] = $size1;
            $this->fontInfo['Size2'] = $size2;
        }

        if (!file_exists($afm = substr($this->fontfile, 0, -3) . 'afm')) {
            $this->error('AFM font file not found: ' . $afm);
        }

        $a = file($afm);
        if (empty($a)) {
            $this->error('AFM file empty or not readable');
        }

        foreach ($a as $line) {
            $e = explode(' ', rtrim($line));
            if (count($e) < 2) {
                continue;
            }

            $entry = $e[0];
            if ($entry == 'C') {
                $w         = $e[4];
                $name      = $e[7];
                $cw[$name] = $w;
            } elseif ($entry == 'FontName') {
                $this->fontInfo['FontName'] = $e[1];
            } elseif ($entry == 'Weight') {
                $this->fontInfo['Weight'] = $e[1];
            } elseif ($entry == 'ItalicAngle') {
                $this->fontInfo['ItalicAngle'] = (int) $e[1];
            } elseif ($entry == 'Ascender') {
                $this->fontInfo['Ascender'] = (int) $e[1];
            } elseif ($entry == 'Descender') {
                $this->fontInfo['Descender'] = (int) $e[1];
            } elseif ($entry == 'UnderlineThickness') {
                $this->fontInfo['UnderlineThickness'] = (int) $e[1];
            } elseif ($entry == 'UnderlinePosition') {
                $this->fontInfo['UnderlinePosition'] = (int) $e[1];
            } elseif ($entry == 'IsFixedPitch') {
                $this->fontInfo['IsFixedPitch'] = ($e[1] == 'true');
            } elseif ($entry == 'FontBBox') {
                $this->fontInfo['FontBBox'] = array((int) $e[1], (int) $e[2], (int) $e[3], (int) $e[4]);
            } elseif ($entry == 'CapHeight') {
                $this->fontInfo['CapHeight'] = (int) $e[1];
            } elseif ($entry == 'StdVW') {
                $this->fontInfo['StdVW'] = (int) $e[1];
            }
        }

        if (!isset($this->fontInfo['FontName'])) {
            $this->error('FontName missing in AFM file');
        }

        $this->fontInfo['Bold'] = isset($this->fontInfo['Weight']) && preg_match('/bold|black/i', $this->fontInfo['Weight']);
        if (isset($cw['.notdef'])) {
            $this->fontInfo['MissingWidth'] = $cw['.notdef'];
        } else {
            $this->fontInfo['MissingWidth'] = 0;
        }

        $widths = array_fill(0, 256, $this->fontInfo['MissingWidth']);

        for ($c = 0; $c <= 255; $c++) {
            if (($name = $this->fontMap[$c]['name']) != '.notdef') {
                if (isset($cw[$name])) {
                    $widths[$c] = $cw[$name];
                } else {
                    $this->error('Character ' . $name . ' is missing');
                }
            }
        }

        $this->fontInfo['Widths'] = $widths;
    }

    protected function descriptor($info) {
        $fd = "array('Ascent' => " . $info['Ascender'] // Ascent
            . ", 'Descent' => " . $info['Descender'];  // Descent

        // CapHeight
        if (!empty($info['CapHeight'])) {
            $fd .= ", 'CapHeight' => " . $info['CapHeight'];
        } else {
            $fd .= ", 'CapHeight' => " . $info['Ascender'];
        }

        // Flags
        $flags = 0;
        if ($info['IsFixedPitch']) {
            $flags += 1 << 0;
        }

        $flags += 1 << 5;
        if ($info['ItalicAngle'] != 0) {
            $flags += 1 << 6;
        }

        $fd .= ", 'Flags' => " . $flags;
        // FontBBox
        $fbb = $info['FontBBox'];
        $fd .= ", 'FontBBox' => '[" . $fbb[0] . ' ' . $fbb[1] . ' ' . $fbb[2] . ' ' . $fbb[3] . "]'";
        // ItalicAngle
        $fd .= ", 'ItalicAngle' => " . $info['ItalicAngle'];
        // StemV
        if (isset($info['StdVW'])) {
            $stemv = $info['StdVW'];
        } elseif ($info['Bold']) {
            $stemv = 120;
        } else {
            $stemv = 70;
        }

        $fd .= ", 'StemV' => " . $stemv;
        // MissingWidth
        $fd .= ", 'MissingWidth' => " . $info['MissingWidth'] . ')';

        return $fd;
    }

    protected function makeWidthArray($widths) {
        $s = "array(\n\t\t";
        for ($c = 0; $c <= 255; $c++) {
            if (chr($c) == "'") {
                $s .= "'\\''";
            } elseif (chr($c) == "\\") {
                $s .= "'\\\\'";
            } elseif ($c >= 32 && $c <= 126) {
                $s .= "'" . chr($c) . "'";
            } else {
                $s .= "chr($c)";
            }

            $s .= ' => ' . $widths[$c];
            if ($c < 255) {
                $s .= ', ';
            }

            if (($c + 1) % 22 == 0) {
                $s .= "\n\t\t";
            }
        }

        $s .= "\n\t)";

        return $s;
    }

    protected function encoding($map) {
        // Build differences from reference encoding
        $this->loadMap('cp1252');
        $s = '';
        $last = 0;
        for ($c = 32; $c <= 255; $c++) {
            if ($map[$c]['name'] != $this->fontMap[$c]['name']) {
                if ($c != $last + 1) {
                    $s .= $c . ' ';
                }

                $last = $c;
                $s .= '/' . $map[$c]['name'] . ' ';
            }
        }
        return rtrim($s);
    }

    protected function saveToFile($file, $s, $mode) {
        if (!$f = fopen($this->fontpath . $file, 'w' . $mode)) {
            $this->error('Can\'t write to file ' . $file);
        }

        fwrite($f, $s, strlen($s));
        fclose($f);

        return true;
    }

    protected function makeDefinitionFile($file, $type, $embed) {
        $s = '<?php'.PHP_EOL
           . 'return array('.PHP_EOL
           . "\t".'\'type\' => \''.$type.'\','.PHP_EOL
           . "\t".'\'name\' => \''.$this->fontInfo['FontName'].'\','.PHP_EOL
           . "\t".'\'desc\' => '.$this->descriptor($this->fontInfo).','.PHP_EOL
           . "\t".'\'up\'   => '.$this->fontInfo['UnderlinePosition'].','.PHP_EOL
           . "\t".'\'ut\'   => '.$this->fontInfo['UnderlineThickness'].','.PHP_EOL
           . "\t".'\'cw\'   => '.$this->makeWidthArray($this->fontInfo['Widths']).','.PHP_EOL
           . "\t".'\'enc\'  => \''.$this->encoding.'\','.PHP_EOL;

        if ($diff = $this->encoding($this->fontMap)) {
            $s .= "\t".'\'diff\' => \''.$diff.'\','.PHP_EOL;
        }

        if ($embed) {
            $s .= "\t".'\'file\' => \''.$this->fontInfo['File'].'\','.PHP_EOL;
            if ($type == 'Type1') {
                $s .= "\t".'\'size1\' => '.$this->fontInfo['Size1'].','.PHP_EOL;
                $s .= "\t".'\'size2\' => '.$this->fontInfo['Size2'].','.PHP_EOL;
            } else {
                $s .= "\t".'\'originalsize\' => '.$this->fontInfo['OriginalSize'].','.PHP_EOL;
            }
        }

        $s .= ');'.PHP_EOL;

        return $this->saveToFile($file, $s, 't');
    }
}
