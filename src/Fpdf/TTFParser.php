<?php

namespace Fpdf;

class TTFParser extends AbstractFpdf
{
    public $f;
    public $tables = array();
    public $unitsPerEm;
    public $xMin, $yMin, $xMax, $yMax;
    public $numberOfHMetrics;
    public $numGlyphs;
    public $widths = array();
    public $chars = array();
    public $postScriptName;
    public $Embeddable;
    public $Bold;
    public $typoAscender;
    public $typoDescender;
    public $capHeight;
    public $italicAngle;
    public $underlinePosition;
    public $underlineThickness;
    public $isFixedPitch;

    public function __construct($file) {
        if (!$this->f = fopen($file, 'rb')) {
            throw new Exception('Can\'t open file: ' . $file);
        }

        if (($version = $this->read(4)) == 'OTTO') {
            throw new Exception('OpenType fonts based on PostScript outlines are not supported');
        }

        if ($version != "\x00\x01\x00\x00") {
            throw new Exception('Unrecognized file format');
        }

        $numTables = $this->readUShort();
        $this->skip(3 * 2); // searchRange, entrySelector, rangeShift

        for ($i = 0; $i < $numTables; $i++) {
            $tag = $this->read(4);
            $this->skip(4); // checkSum
            $offset = $this->readULong();
            $this->skip(4); // length
            $this->tables[$tag] = $offset;
        }

        $this->head();
        $this->hhea();
        $this->maxp();
        $this->hmtx();
        $this->cmap();
        $this->name();
        $this->os2();
        $this->post();

        fclose($this->f);
    }

    protected function head() {
        $this->seek('head');
        $this->skip(3 * 4); // version, fontRevision, checkSumAdjustment

        if (($magicNumber = $this->readULong()) != 0x5F0F3CF5) {
            throw new Exception('Incorrect magic number');
        }

        $this->skip(2); // flags
        $this->unitsPerEm = $this->readUShort();
        $this->skip(2 * 8); // created, modified
        $this->xMin = $this->readShort();
        $this->yMin = $this->readShort();
        $this->xMax = $this->readShort();
        $this->yMax = $this->readShort();
    }

    protected function hhea() {
        $this->seek('hhea');
        $this->skip(4 + 15 * 2);
        $this->numberOfHMetrics = $this->readUShort();
    }

    protected function maxp() {
        $this->seek('maxp');
        $this->skip(4);
        $this->numGlyphs = $this->readUShort();
    }

    protected function hmtx() {
        $this->seek('hmtx');

        for ($i = 0; $i < $this->numberOfHMetrics; $i++) {
            $advanceWidth = $this->readUShort();
            $this->skip(2); // lsb
            $this->widths[$i] = $advanceWidth;
        }

        if ($this->numberOfHMetrics < $this->numGlyphs) {
            $lastWidth = $this->widths[$this->numberOfHMetrics - 1];
            $this->widths = array_pad($this->widths, $this->numGlyphs, $lastWidth);
        }
    }

    protected function cmap() {
        $this->seek('cmap');
        $this->skip(2); // version
        $numTables = $this->readUShort();
        $offset31 = 0;

        for ($i = 0; $i < $numTables; $i++) {
            $platformID = $this->readUShort();
            $encodingID = $this->readUShort();
            $offset = $this->readULong();

            if ($platformID == 3 && $encodingID == 1) {
                $offset31 = $offset;
            }
        }

        if ($offset31 == 0) {
            throw new Exception('No Unicode encoding found');
        }

        $startCount = $endCount = $idDelta = $idRangeOffset = array();
        fseek($this->f, $this->tables['cmap'] + $offset31, SEEK_SET);

        if (($format = $this->readUShort()) != 4) {
            throw new Exception('Unexpected subtable format: ' . $format);
        }

        $this->skip(2 * 2); // length, language
        $segCount = $this->readUShort() / 2;
        $this->skip(3 * 2); // searchRange, entrySelector, rangeShift

        for ($i = 0; $i < $segCount; $i++) {
            $endCount[$i] = $this->readUShort();
        }

        $this->skip(2); // reservedPad

        for ($i = 0; $i < $segCount; $i++) {
            $startCount[$i] = $this->readUShort();
        }

        for ($i = 0; $i < $segCount; $i++) {
            $idDelta[$i] = $this->readShort();
        }

        $offset = ftell($this->f);

        for ($i = 0; $i < $segCount; $i++) {
            $idRangeOffset[$i] = $this->readUShort();
        }

        for ($i = 0; $i < $segCount; $i++) {
            $c1 = $startCount[$i];
            $c2 = $endCount[$i];
            $d  = $idDelta[$i];

            if (($ro = $idRangeOffset[$i]) > 0) {
                fseek($this->f, $offset + 2 * $i + $ro, SEEK_SET);
            }

            for ($c = $c1; $c <= $c2; $c++) {
                if ($c == 0xFFFF) {
                    break;
                }

                if ($ro > 0) {
                    if (($gid = $this->readUShort()) > 0) {
                        $gid += $d;
                    }
                } else {
                    $gid = $c + $d;
                }

                if ($gid >= 65536) {
                    $gid -= 65536;
                }

                if ($gid > 0) {
                    $this->chars[$c] = $gid;
                }
            }
        }
    }

    protected function name() {
        $this->seek('name');
        $tableOffset = ftell($this->f);
        $this->postScriptName = '';
        $this->skip(2); // format
        $count = $this->readUShort();
        $stringOffset = $this->readUShort();

        for ($i = 0; $i < $count; $i++) {
            $this->skip(3 * 2); // platformID, encodingID, languageID
            $nameID = $this->readUShort();
            $length = $this->readUShort();
            $offset = $this->readUShort();

            if ($nameID == 6) {
                // PostScript name
                fseek($this->f, $tableOffset + $stringOffset + $offset, SEEK_SET);
                $s = $this->read($length);
                $s = str_replace(chr(0), '', $s);
                $s = preg_replace('|[ \[\](){}<>/%]|', '', $s);
                $this->postScriptName = $s;
                break;
            }
        }

        if ($this->postScriptName == '') {
            throw new Exception('PostScript name not found');
        }
    }

    protected function os2() {
        $this->seek('OS/2');
        $version = $this->readUShort();
        $this->skip(3 * 2); // xAvgCharWidth, usWeightClass, usWidthClass
        $fsType = $this->readUShort();
        $this->Embeddable = ($fsType != 2) && ($fsType & 0x200) == 0;
        $this->skip(11 * 2 + 10 + 4 * 4 + 4);
        $fsSelection = $this->readUShort();
        $this->Bold = ($fsSelection & 32) != 0;
        $this->skip(2 * 2); // usFirstCharIndex, usLastCharIndex
        $this->typoAscender = $this->readShort();
        $this->typoDescender = $this->readShort();

        if ($version >= 2) {
            $this->skip(3 * 2 + 2 * 4 + 2);
            $this->capHeight = $this->readShort();
        } else {
            $this->capHeight = 0;
        }
    }

    protected function post() {
        $this->seek('post');
        $this->skip(4); // version
        $this->italicAngle = $this->readShort();
        $this->skip(2); // Skip decimal part
        $this->underlinePosition = $this->readShort();
        $this->underlineThickness = $this->readShort();
        $this->isFixedPitch = ($this->readULong() != 0);
    }

    protected function seek($tag) {
        if (!isset($this->tables[$tag])) {
            throw new Exception('Table not found: ' . $tag);
        }

        fseek($this->f, $this->tables[$tag], SEEK_SET);
    }

    protected function skip($n) {
        fseek($this->f, $n, SEEK_CUR);
    }

    protected function read($n) {
        return fread($this->f, $n);
    }

    protected function readUShort() {
        $a = unpack('nn', fread($this->f, 2));

        return $a['n'];
    }

    protected function readShort() {
        $a = unpack('nn', fread($this->f, 2));
        if (($v = $a['n']) >= 0x8000) {
            $v -= 65536;
        }

        return $v;
    }

    protected function readULong() {
        $a = unpack('NN', fread($this->f, 4));

        return $a['N'];
    }
}
