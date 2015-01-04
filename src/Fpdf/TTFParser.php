<?php

namespace Fpdf;

class TTFParser extends AbstractFpdf {
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
            $this->Error('Can\'t open file: ' . $file);
        }

        if (($version = $this->Read(4)) == 'OTTO') {
            $this->Error('OpenType fonts based on PostScript outlines are not supported');
        }

        if ($version != "\x00\x01\x00\x00") {
            $this->Error('Unrecognized file format');
        }

        $numTables = $this->ReadUShort();
        $this->Skip(3 * 2); // searchRange, entrySelector, rangeShift

        for ($i = 0; $i < $numTables; $i++) {
            $tag = $this->Read(4);
            $this->Skip(4); // checkSum
            $offset = $this->ReadULong();
            $this->Skip(4); // length
            $this->tables[$tag] = $offset;
        }

        $this->ParseHead();
        $this->ParseHhea();
        $this->ParseMaxp();
        $this->ParseHmtx();
        $this->ParseCmap();
        $this->ParseName();
        $this->ParseOS2();
        $this->ParsePost();

        fclose($this->f);
    }

    protected function ParseHead() {
        $this->Seek('head');
        $this->Skip(3 * 4); // version, fontRevision, checkSumAdjustment
        $magicNumber = $this->ReadULong();

        if ($magicNumber != 0x5F0F3CF5) {
            $this->Error('Incorrect magic number');
        }

        $this->Skip(2); // flags
        $this->unitsPerEm = $this->ReadUShort();
        $this->Skip(2 * 8); // created, modified
        $this->xMin = $this->ReadShort();
        $this->yMin = $this->ReadShort();
        $this->xMax = $this->ReadShort();
        $this->yMax = $this->ReadShort();
    }

    protected function ParseHhea() {
        $this->Seek('hhea');
        $this->Skip(4 + 15 * 2);
        $this->numberOfHMetrics = $this->ReadUShort();
    }

    protected function ParseMaxp() {
        $this->Seek('maxp');
        $this->Skip(4);
        $this->numGlyphs = $this->ReadUShort();
    }

    protected function ParseHmtx() {
        $this->Seek('hmtx');

        for ($i = 0; $i < $this->numberOfHMetrics; $i++) {
            $advanceWidth = $this->ReadUShort();
            $this->Skip(2); // lsb
            $this->widths[$i] = $advanceWidth;
        }

        if ($this->numberOfHMetrics < $this->numGlyphs) {
            $lastWidth = $this->widths[$this->numberOfHMetrics - 1];
            $this->widths = array_pad($this->widths, $this->numGlyphs, $lastWidth);
        }
    }

    protected function ParseCmap() {
        $this->Seek('cmap');
        $this->Skip(2); // version
        $numTables = $this->ReadUShort();
        $offset31 = 0;

        for ($i = 0; $i < $numTables; $i++) {
            $platformID = $this->ReadUShort();
            $encodingID = $this->ReadUShort();
            $offset = $this->ReadULong();

            if ($platformID == 3 && $encodingID == 1) {
                $offset31 = $offset;
            }
        }

        if ($offset31 == 0) {
            $this->Error('No Unicode encoding found');
        }

        $startCount = $endCount = $idDelta = $idRangeOffset = array();

        fseek($this->f, $this->tables['cmap'] + $offset31, SEEK_SET);
        if (($format = $this->ReadUShort()) != 4) {
            $this->Error('Unexpected subtable format: ' . $format);
        }

        $this->Skip(2 * 2); // length, language
        $segCount = $this->ReadUShort() / 2;
        $this->Skip(3 * 2); // searchRange, entrySelector, rangeShift

        for ($i = 0; $i < $segCount; $i++) {
            $endCount[$i] = $this->ReadUShort();
        }

        $this->Skip(2); // reservedPad
        for ($i = 0; $i < $segCount; $i++) {
            $startCount[$i] = $this->ReadUShort();
        }

        for ($i = 0; $i < $segCount; $i++) {
            $idDelta[$i] = $this->ReadShort();
        }

        $offset = ftell($this->f);
        for ($i = 0; $i < $segCount; $i++) {
            $idRangeOffset[$i] = $this->ReadUShort();
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
                    if (($gid = $this->ReadUShort()) > 0) {
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

    protected function ParseName() {
        $this->Seek('name');
        $tableOffset = ftell($this->f);
        $this->postScriptName = '';
        $this->Skip(2); // format
        $count = $this->ReadUShort();
        $stringOffset = $this->ReadUShort();

        for ($i = 0; $i < $count; $i++) {
            $this->Skip(3 * 2); // platformID, encodingID, languageID
            $nameID = $this->ReadUShort();
            $length = $this->ReadUShort();
            $offset = $this->ReadUShort();

            if ($nameID == 6) {
                // PostScript name
                fseek($this->f, $tableOffset + $stringOffset + $offset, SEEK_SET);
                $s = $this->Read($length);
                $s = str_replace(chr(0), '', $s);
                $s = preg_replace('|[ \[\](){}<>/%]|', '', $s);
                $this->postScriptName = $s;
                break;
            }
        }

        if ($this->postScriptName == '') {
            $this->Error('PostScript name not found');
        }
    }

    protected function ParseOS2() {
        $this->Seek('OS/2');
        $version = $this->ReadUShort();
        $this->Skip(3 * 2); // xAvgCharWidth, usWeightClass, usWidthClass
        $fsType = $this->ReadUShort();
        $this->Embeddable = ($fsType != 2) && ($fsType & 0x200) == 0;
        $this->Skip(11 * 2 + 10 + 4 * 4 + 4);
        $fsSelection = $this->ReadUShort();
        $this->Bold = ($fsSelection & 32) != 0;
        $this->Skip(2 * 2); // usFirstCharIndex, usLastCharIndex
        $this->typoAscender = $this->ReadShort();
        $this->typoDescender = $this->ReadShort();

        if ($version >= 2) {
            $this->Skip(3 * 2 + 2 * 4 + 2);
            $this->capHeight = $this->ReadShort();
        } else {
            $this->capHeight = 0;
        }
    }

    protected function ParsePost() {
        $this->Seek('post');
        $this->Skip(4); // version
        $this->italicAngle = $this->ReadShort();
        $this->Skip(2); // Skip decimal part
        $this->underlinePosition = $this->ReadShort();
        $this->underlineThickness = $this->ReadShort();
        $this->isFixedPitch = ($this->ReadULong() != 0);
    }

    protected function Seek($tag) {
        if (!isset($this->tables[$tag])) {
            $this->Error('Table not found: ' . $tag);
        }

        fseek($this->f, $this->tables[$tag], SEEK_SET);
    }

    protected function Skip($n) {
        fseek($this->f, $n, SEEK_CUR);
    }

    protected function Read($n) {
        return fread($this->f, $n);
    }

    protected function ReadUShort() {
        $a = unpack('nn', fread($this->f, 2));

        return $a['n'];
    }

    protected function ReadShort() {
        $a = unpack('nn', fread($this->f, 2));
        if (($v = $a['n']) >= 0x8000) {
            $v -= 65536;
        }

        return $v;
    }

    protected function ReadULong() {
        $a = unpack('NN', fread($this->f, 4));

        return $a['N'];
    }
}
