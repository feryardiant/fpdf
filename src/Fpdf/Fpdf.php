<?php

namespace Fpdf;

class Fpdf extends AbstractFpdf {
    const
        VERSION = '1.7.0';

    protected
        // current page number
        $page = 0,
        // current object number
        $objNum = 2,
        // array of object offsets
        $offsets,
        // buffer holding in-memory PDF
        $buffer = '',
        // array containing pages
        $pages = array(),
        // current document state
        $state = 0,
        // compression flag
        $compress,
        // scale factor (number of points in user unit)
        $scaleFactor,
        // default and current orientation
        $defOrientation, $curOrientation,
        // standard page sizes
        $stdPageSizes = array(
            'a3'     => array( 841.89, 1190.55 ),
            'a4'     => array( 595.28, 841.89  ),
            'a5'     => array( 420.94, 595.28  ),
            'letter' => array( 612,    792     ),
            'legal'  => array( 612,    1008    ),
        ),
        // default and current page size
        $defPageSize, $curPageSize,
        $pageSizes = array(), // used for pages with non default sizes or orientations
        // dimensions of current page in points and unit
        $widthPt, $heightPt, $width, $height,
        // left, top, right, bottom and cell margin
        $lMargin, $tMargin, $rMargin, $bMargin, $cMargin,
        // current position in user unit
        $x, $y,
        // height of last printed cell
        $lasth = 0,
        // line width in user unit
        $lineWidth,
        // array of core font names
        $coreFonts = array(
            'courier',
            'helvetica',
            'times',
            'symbol',
            'zapfdingbats',
        ),
        // array of used fonts
        $fonts = array(),
        // array of font files
        $fontFiles = array(),
        // array of encoding differences
        $diffs = array(),
        // current font family
        $fontFamily = '',
        // current font style
        $fontStyle = '',
        // underlining flag
        $underline = false,
        // current font info
        $curFont,
        // current font size in points
        $fontSizePt = 12,
        // current font size in user unit
        $fontSize,
        // commands for drawing color
        $drawColor = self::DRAW_COLOR,
        // commands for filling color
        $fillColor = self::FILL_COLOR,
        // commands for text color
        $textColor = self::TEXT_COLOR,
        // indicates whether fill and text colors are different
        $colorFlag = false,
        // word spacing
        $ws = 0,
        // array of used images
        $images = array(),
        // array of links in pages
        $pageLinks = array(),
        // array of internal links
        $links = array(),
        // Page breaking
        $autoPageBreak, $pageBreakTrigger,
        // flag set when processing header
        $inHeader = false,
        // flag set when processing footer
        $inFooter = false,
        // zoom display mode
        $zoomMode = 'default',
        // layout display mode
        $layoutMode = 'default',
        // title
        $title, $subject, $author, $keywords, $creator,
        // alias for total number of pages
        $aliasNbPages,
        // PDF version number
        $pdfVersion = '1.3';

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
        // Some checks
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

        // Font path
        $this->_validateFontpath();

        // Scale factor
        if ($unit == 'pt') {
            $this->scaleFactor = 1;
        } elseif ($unit == 'mm') {
            $this->scaleFactor = 72 / 25.4;
        } elseif ($unit == 'cm') {
            $this->scaleFactor = 72 / 2.54;
        } elseif ($unit == 'in') {
            $this->scaleFactor = 72;
        } else {
            $this->error('Incorrect unit: ' . $unit);
        }

        // Page sizes
        $size = $this->_getPageSize($size);
        $this->defPageSize = $size;
        $this->curPageSize = $size;

        // Page orientation
        $orientation = strtolower($orientation);
        if (in_array($orientation, array('p', 'portrait'))) {
            $this->defOrientation = 'P';
            $this->width = $size[0];
            $this->height = $size[1];
        } elseif (in_array($orientation, array('l', 'landscape'))) {
            $this->defOrientation = 'L';
            $this->width = $size[1];
            $this->height = $size[0];
        } else {
            $this->error('Incorrect orientation: ' . $orientation);
        }

        $this->curOrientation = $this->defOrientation;
        $this->widthPt = $this->width * $this->scaleFactor;
        $this->heightPt = $this->height * $this->scaleFactor;
        // Page margins (1 cm)
        $margin = 28.35 / $this->scaleFactor;
        $this->setMargins($margin, $margin);
        // Interior cell margin (1 mm)
        $this->cMargin = $margin / 10;
        // Line width (0.2 mm)
        $this->lineWidth = .567 / $this->scaleFactor;
        // Automatic page break
        $this->setAutoPageBreak(true, 2 * $margin);
        // Default display mode
        $this->setDisplayMode('default');
        // Enable compression
        $this->setCompression(true);
    }

    // Set left, top and right margins
    public function setMargins($left, $top, $right = null) {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right ?: $left;
    }

    // Set left margin
    public function setLeftMargin($margin) {
        $this->lMargin = $margin;
        if ($this->page > 0 && $this->x < $margin) {
            $this->x = $margin;
        }
    }

    // Set top margin
    public function setTopMargin($margin) {
        $this->tMargin = $margin;
    }

    // Set right margin
    public function setRightMargin($margin) {
        $this->rMargin = $margin;
    }

    // Set auto page break mode and triggering margin
    public function setAutoPageBreak($auto, $margin = 0) {
        $this->autoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->pageBreakTrigger = $this->height - $margin;
    }

    // Set display mode in viewer
    public function setDisplayMode($zoom, $layout = 'default') {
        if (in_array($zoom, array('fullpage', 'fullwidth', 'real', 'default')) || !is_string($zoom)) {
            $this->zoomMode = $zoom;
        } else {
            $this->error('Incorrect zoom display mode: ' . $zoom);
        }

        if (in_array($zoom, array('single', 'continuous', 'two', 'default')) || !is_string($zoom)) {
            $this->layoutMode = $layout;
        } else {
            $this->error('Incorrect layout display mode: ' . $layout);
        }
    }

    // Set page compression
    public function setCompression($compress) {
        $this->compress = function_exists('gzcompress') ? $compress : false;
    }

    // Title of document
    public function setTitle($title, $isUTF8 = false) {
        $this->_setMeta('title', $title, $isUTF8);
    }

    // Subject of document
    public function setSubject($subject, $isUTF8 = false) {
        $this->_setMeta('subject', $subject, $isUTF8);
    }

    // Author of document
    public function setAuthor($author, $isUTF8 = false) {
        $this->_setMeta('author', $author, $isUTF8);
    }

    // Keywords of document
    public function setKeywords($keywords, $isUTF8 = false) {
        $this->_setMeta('keywords', $keywords, $isUTF8);
    }

    // Creator of document
    public function setCreator($creator, $isUTF8 = false) {
        $this->_setMeta('creator', $creator, $isUTF8);
    }

    // Define an alias for total number of pages
    public function sliasNbPages($alias = '{nb}') {
        $this->aliasNbPages = $alias;
    }

    // Begin document
    public function open() {
        $this->state = 1;
    }

    // Terminate document
    public function close() {
        if ($this->state == 3) {
            return;
        }

        if ($this->page == 0) {
            $this->addPage();
        }

        // Page footer
        $this->inFooter = true;
        $this->footer();
        $this->inFooter = false;
        // Close page
        $this->_endPage();
        // Close document
        $this->_endDoc();
    }

    // Start a new page
    public function addPage($orientation = '', $size = '') {
        if ($this->state == 0) {
            $this->open();
        }

        $family = $this->fontFamily;
        $style = $this->fontStyle . ($this->underline ? 'U' : '');
        $fontsize = $this->fontSizePt;
        $lw = $this->lineWidth;
        $dc = $this->drawColor;
        $fc = $this->fillColor;
        $tc = $this->textColor;
        $cf = $this->colorFlag;

        if ($this->page > 0) {
            // Page footer
            $this->inFooter = true;
            $this->footer();
            $this->inFooter = false;
            // Close page
            $this->_endPage();
        }

        // Start new page
        $this->_beginPage($orientation, $size);
        // Set line cap style to square
        $this->_out('2 J');
        // Set line width
        $this->lineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->scaleFactor));
        // Set font
        if ($family) {
            $this->setFont($family, $style, $fontsize);
        }

        // Set colors
        $this->drawColor = $dc;
        if ($dc != self::DRAW_COLOR) {
            $this->_out($dc);
        }

        $this->fillColor = $fc;
        if ($fc != self::FILL_COLOR) {
            $this->_out($fc);
        }

        $this->textColor = $tc;
        $this->colorFlag = $cf;

        // Page header
        $this->inHeader = true;
        $this->header();
        $this->inHeader = false;

        // Restore line width
        if ($this->lineWidth != $lw) {
            $this->lineWidth = $lw;
            $this->_out(sprintf('%.2F w', $lw * $this->scaleFactor));
        }
        // Restore font
        if ($family) {
            $this->setFont($family, $style, $fontsize);
        }

        // Restore colors
        if ($this->drawColor != $dc) {
            $this->drawColor = $dc;
            $this->_out($dc);
        }
        if ($this->fillColor != $fc) {
            $this->fillColor = $fc;
            $this->_out($fc);
        }
        $this->textColor = $tc;
        $this->colorFlag = $cf;
    }

    // To be implemented in your own inherited class
    public function header() {}

    // To be implemented in your own inherited class
    public function footer() {}

    // Get current page number
    public function pageNo() {
        return $this->page;
    }

    // Set color for all stroking operations
    public function setDrawColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->drawColor = sprintf('%.3F G', $r / 255);
        } else {
            $this->drawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }

        if ($this->page > 0) {
            $this->_out($this->drawColor);
        }
    }

    // Set color for all filling operations
    public function setFillColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->fillColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->fillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }

        $this->colorFlag = ($this->fillColor != $this->textColor);
        if ($this->page > 0) {
            $this->_out($this->fillColor);
        }
    }

    // Set color for text
    public function setTextColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->textColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->textColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }

        $this->colorFlag = ($this->fillColor != $this->textColor);
    }

    // Get width of a string in the current font
    public function getStringWidth($s) {
        $s = (string) $s;
        $cw = &$this->curFont['cw'];
        $w = 0;
        $l = strlen($s);
        for ($i = 0; $i < $l; $i++) {
            $w += $cw[$s[$i]];
        }

        return $w * $this->fontSize / 1000;
    }

    // Set line width
    public function setLineWidth($width) {
        $this->lineWidth = $width;
        if ($this->page > 0) {
            $this->_out(sprintf('%.2F w', $width * $this->scaleFactor));
        }
    }

    // Draw a line
    public function line($x1, $y1, $x2, $y2) {
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->scaleFactor, ($this->height - $y1) * $this->scaleFactor, $x2 * $this->scaleFactor, ($this->height - $y2) * $this->scaleFactor));
    }

    // Draw a rectangle
    public function rect($x, $y, $w, $h, $style = '') {
        if ($style == 'F') {
            $op = 'f';
        } elseif (in_array($style, array('FD', 'DF'))) {
            $op = 'B';
        } else {
            $op = 'S';
        }

        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->scaleFactor, ($this->height - $y) * $this->scaleFactor, $w * $this->scaleFactor, -$h * $this->scaleFactor, $op));
    }

    // Add a TrueType, OpenType or Type1 font
    public function addFont($family, $style = '', $file = '') {
        $family = strtolower($family);
        if ($file == '') {
            $file = str_replace(' ', '', $family) . strtolower($style) . '.php';
        }

        if (($style = strtoupper($style)) == 'IB') {
            $style = 'BI';
        }

        $fontkey = $family . $style;
        if (isset($this->fonts[$fontkey])) {
            return;
        }

        $info = $this->_loadFont($file);
        $info['i'] = count($this->fonts) + 1;

        if (!empty($info['diff'])) {
            // Search existing encodings
            if (!($n = array_search($info['diff'], $this->diffs))) {
                $n = count($this->diffs) + 1;
                $this->diffs[$n] = $info['diff'];
            }
            $info['diffn'] = $n;
        }

        if (!empty($info['file'])) {
            // Embedded font
            if ($info['type'] == 'TrueType') {
                $this->fontFiles[$info['file']] = array('length1' => $info['originalsize']);
            } else {
                $this->fontFiles[$info['file']] = array('length1' => $info['size1'], 'length2' => $info['size2']);
            }
        }

        $this->fonts[$fontkey] = $info;
    }

    // Select a font; size given in points
    public function setFont($family, $style = '', $size = 0) {
        $family || $family = $this->fontFamily;
        $size || $size = $this->fontSizePt;
        $family = strtolower($family);
        $style = strtoupper($style);

        if (strpos($style, 'U') !== false) {
            $this->underline = true;
            $style = str_replace('U', '', $style);
        } else {
            $this->underline = false;
        }

        if ($style == 'IB') {
            $style = 'BI';
        }

        // Test if font is already selected
        if ($this->fontFamily == $family && $this->fontStyle == $style && $this->fontSizePt == $size) {
            return;
        }

        // Test if font is already loaded
        $fontkey = $family . $style;
        if (!isset($this->fonts[$fontkey])) {
            // Test if one of the core fonts
            if ($family == 'arial') {
                $family = 'helvetica';
            }

            if (in_array($family, $this->coreFonts)) {
                if (in_array($family, array('symbol', 'zapfdingbats'))) {
                    $style = '';
                }

                $fontkey = $family . $style;
                if (!isset($this->fonts[$fontkey])) {
                    $this->addFont($family, $style);
                }
            } else {
                $this->error('Undefined font: ' . $family . ' ' . $style);
            }
        }

        // Select it
        $this->fontFamily = $family;
        $this->fontStyle = $style;
        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->scaleFactor;
        $this->curFont = &$this->fonts[$fontkey];

        if ($this->page > 0) {
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->curFont['i'], $this->fontSizePt));
        }
    }

    // Set font size in points
    public function setFontSize($size) {
        if ($this->fontSizePt == $size) {
            return;
        }

        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->scaleFactor;

        if ($this->page > 0) {
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->curFont['i'], $this->fontSizePt));
        }
    }

    // Create a new internal link
    public function addLink() {
        $n = count($this->links) + 1;
        $this->links[$n] = array(0, 0);

        return $n;
    }

    // Set destination of internal link
    public function setLink($link, $y = 0, $page = -1) {
        if ($y == -1) {
            $y = $this->y;
        }

        if ($page == -1) {
            $page = $this->page;
        }

        $this->links[$link] = array($page, $y);
    }

    // Put a link on the page
    public function link($x, $y, $w, $h, $link) {
        $this->pageLinks[$this->page][] = array(
            $x * $this->scaleFactor,
            $this->heightPt - $y * $this->scaleFactor,
            $w * $this->scaleFactor,
            $h * $this->scaleFactor,
            $link
        );
    }

    // Output a string
    public function text($x, $y, $txt) {
        $s = sprintf(
            'BT %.2F %.2F Td (%s) Tj ET',
            $x * $this->scaleFactor,
            ($this->height - $y) * $this->scaleFactor,
            $this->_escape($txt)
        );

        if ($this->underline && $txt != '') {
            $s .= ' ' . $this->_doUnderline($x, $y, $txt);
        }

        if ($this->colorFlag) {
            $s = 'q ' . $this->textColor . ' ' . $s . ' Q';
        }

        $this->_out($s);
    }

    // Accept automatic page break or not
    public function acceptPageBreak() {
        return $this->autoPageBreak;
    }

    // Output a cell
    public function cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        $k = $this->scaleFactor;

        if (
            $this->y + $h > $this->pageBreakTrigger &&
            !$this->inHeader &&
            !$this->inFooter &&
            $this->acceptPageBreak()
        ) {
            // Automatic page break
            $x = $this->x;

            if (($ws = $this->ws) > 0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }

            $this->addPage($this->curOrientation, $this->curPageSize);
            $this->x = $x;

            if ($ws > 0) {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
            }
        }

        if ($w == 0) {
            $w = $this->width - $this->rMargin - $this->x;
        }

        $s = '';
        if ($fill || $border == 1) {
            if ($fill) {
                $op = ($border == 1) ? 'B' : 'f';
            } else {
                $op = 'S';
            }

            $s = sprintf(
                '%.2F %.2F %.2F %.2F re %s ',
                $this->x * $k,
                ($this->height - $this->y) * $k,
                $w * $k,
                -$h * $k,
                $op
            );
        }
        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;

            if (strpos($border, 'L') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k,
                    ($this->height - $y) * $k,
                    $x * $k,
                    ($this->height - ($y + $h)) * $k
                );
            }

            if (strpos($border, 'T') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k,
                    ($this->height - $y) * $k,
                    ($x + $w) * $k,
                    ($this->height - $y) * $k
                );
            }

            if (strpos($border, 'R') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    ($x + $w) * $k,
                    ($this->height - $y) * $k,
                    ($x + $w) * $k,
                    ($this->height - ($y + $h)) * $k
                );
            }

            if (strpos($border, 'B') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k,
                    ($this->height - ($y + $h)) * $k,
                    ($x + $w) * $k,
                    ($this->height - ($y + $h)) * $k
                );
            }
        }

        if ($txt !== '') {
            if ($align == 'R') {
                $dx = $w - $this->cMargin - $this->getStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($w - $this->getStringWidth($txt)) / 2;
            } else {
                $dx = $this->cMargin;
            }

            if ($this->colorFlag) {
                $s .= 'q ' . $this->textColor . ' ';
            }

            $txt2 = str_replace(')', '\\)', str_replace('(', '\\(', str_replace('\\', '\\\\', $txt)));
            $s .= sprintf(
                'BT %.2F %.2F Td (%s) Tj ET',
                ($this->x + $dx) * $k,
                ($this->height - ($this->y + .5 * $h + .3 * $this->fontSize)) * $k,
                $txt2
            );

            if ($this->underline) {
                $s .= ' ' . $this->_doUnderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->fontSize, $txt);
            }

            if ($this->colorFlag) {
                $s .= ' Q';
            }

            if ($link) {
                $this->link(
                    $this->x + $dx,
                    $this->y + .5 * $h - .5 * $this->fontSize,
                    $this->getStringWidth($txt),
                    $this->fontSize,
                    $link
                );
            }
        }

        if ($s) {
            $this->_out($s);
        }

        $this->lasth = $h;
        if ($ln > 0) {
            // Go to next line
            $this->y += $h;
            if ($ln == 1) {
                $this->x = $this->lMargin;
            }
        } else {
            $this->x += $w;
        }
    }

    // Output text with automatic or explicit line breaks
    public function multiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false) {
        $cw = &$this->curFont['cw'];
        if ($w == 0) {
            $w = $this->width - $this->rMargin - $this->x;
        }

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', $txt);

        if (($nb = strlen($s)) > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }

        $b = 0;
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false) {
                    $b2 .= 'L';
                }

                if (strpos($border, 'R') !== false) {
                    $b2 .= 'R';
                }

                $b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }

        $sep = -1;
        $i = $j = $l = $ns = 0;
        $nl = 1;

        while ($i < $nb) {
            // Get next character
            if (($c = $s[$i]) == "\n") {
                // Explicit line break
                if ($this->ws > 0) {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }

                $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;

                if ($border && $nl == 2) {
                    $b = $b2;
                }

                continue;
            }

            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }

            $l += $cw[$c];
            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }

                    if ($this->ws > 0) {
                        $this->ws = 0;
                        $this->_out('0 Tw');
                    }

                    $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->fontSize / ($ns - 1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->ws * $this->scaleFactor));
                    }

                    $this->cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                    $i = $sep + 1;
                }

                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;

                if ($border && $nl == 2) {
                    $b = $b2;
                }
            } else {
                $i++;
            }
        }

        // Last chunk
        if ($this->ws > 0) {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        if ($border && strpos($border, 'B') !== false) {
            $b .= 'B';
        }

        $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    // Output text in flowing mode
    public function write($h, $txt, $link = '') {
        $cw = &$this->curFont['cw'];
        $w = $this->width - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        $sep = -1;
        $i = $j = $l = 0;
        $nl = 1;

        while ($i < $nb) {
            // Get next character
            if (($c = $s[$i]) == "\n") {
                // Explicit line break
                $this->cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;

                if ($nl == 1) {
                    $this->x = $this->lMargin;
                    $w = $this->width - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                }

                $nl++;

                continue;
            }

            if ($c == ' ') {
                $sep = $i;
            }

            $l += $cw[$c];
            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($this->x > $this->lMargin) {
                        // Move to next line
                        $this->x = $this->lMargin;
                        $this->y += $h;
                        $w = $this->width - $this->rMargin - $this->x;
                        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                        $i++;
                        $nl++;
                        continue;
                    }

                    if ($i == $j) {
                        $i++;
                    }

                    $this->cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
                } else {
                    $this->cell($w, $h, substr($s, $j, $sep - $j), 0, 2, '', 0, $link);
                    $i = $sep + 1;
                }

                $sep = -1;
                $j = $i;
                $l = 0;

                if ($nl == 1) {
                    $this->x = $this->lMargin;
                    $w = $this->width - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                }

                $nl++;
            } else {
                $i++;
            }
        }

        // Last chunk
        if ($i != $j) {
            $this->cell($l / 1000 * $this->fontSize, $h, substr($s, $j), 0, 0, '', 0, $link);
        }
    }

    // Line feed; default value is last cell height
    public function ln($h = null) {
        $this->x = $this->lMargin;
        if ($h === null) {
            $this->y += $this->lasth;
        } else {
            $this->y += $h;
        }
    }

    // Put an image on the page
    public function image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '') {
        if (!isset($this->images[$file])) {
            // First use of this image, get info
            if ($type == '') {
                if (!$pos = strrpos($file, '.')) {
                    $this->error('Image file has no extension and no type was specified: ' . $file);
                }

                $type = substr($file, $pos + 1);
            }

            $type = strtolower($type);

            if ($type == 'jpeg') {
                $type = 'jpg';
            }

            $mtd = '_parse' . $type;
            if (!method_exists($this, $mtd)) {
                $this->error('Unsupported image type: ' . $type);
            }

            $info = $this->$mtd($file);
            $info['i'] = count($this->images) + 1;
            $this->images[$file] = $info;
        } else {
            $info = $this->images[$file];
        }

        // Automatic width and height calculation if needed
        if ($w == 0 && $h == 0) {
            // Put image at 96 dpi
            $w = -96;
            $h = -96;
        }

        if ($w < 0) {
            $w = -$info['w'] * 72 / $w / $this->scaleFactor;
        }

        if ($h < 0) {
            $h = -$info['h'] * 72 / $h / $this->scaleFactor;
        }

        if ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        }

        if ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        // Flowing mode
        if ($y === null) {
            if ($this->y + $h > $this->pageBreakTrigger && !$this->inHeader && !$this->inFooter && $this->acceptPageBreak()) {
                // Automatic page break
                $x2 = $this->x;
                $this->addPage($this->curOrientation, $this->curPageSize);
                $this->x = $x2;
            }
            $y = $this->y;
            $this->y += $h;
        }

        if ($x === null) {
            $x = $this->x;
        }

        $this->_out(
            sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $w * $this->scaleFactor,
            $h * $this->scaleFactor,
            $x * $this->scaleFactor,
            ($this->height - ($y + $h)) * $this->scaleFactor,
            $info['i']
        ));

        if ($link) {
            $this->link($x, $y, $w, $h, $link);
        }
    }

    // Get x position
    public function getX() {
        return $this->x;
    }

    // Set x position
    public function setX($x) {
        $this->x = $x >= 0 ? $x : $this->width + $x;
    }

    // Get y position
    public function getY() {
        return $this->y;
    }

    // Set y position and reset x
    public function setY($y) {
        $this->x = $this->lMargin;
        $this->y = $y >= 0 ? $y : $this->height + $y;
    }

    // Set x and y positions
    public function setXY($x, $y) {
        $this->setY($y);
        $this->setX($x);
    }

    // Output PDF to some destination
    public function output($name = '', $dest = '') {
        if ($this->state < 3) {
            $this->close();
        }

        if ($dest == '') {
            if ($name == '') {
                $name = 'doc.pdf';
                $dest = 'I';
            } else {
                $dest = 'F';
            }
        }

        $dest = strtoupper($dest);
        switch ($dest) {
            case 'I':
                // Send to standard output
                $this->_checkOutput();
                if (PHP_SAPI != 'cli') {
                    // We send to a browser
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="' . $name . '"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->buffer;
                break;
            case 'D':
                // Download file
                $this->_checkOutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                // Save to local file
                if (!$f = fopen($name, 'wb')) {
                    $this->error('Unable to create output file: ' . $name);
                }

                fwrite($f, $this->buffer, strlen($this->buffer));
                fclose($f);
                break;
            case 'S':
                // Return as a string
                return $this->buffer;
            default:
                $this->error('Incorrect output destination: ' . $dest);
        }
        return '';
    }

    // Creator of document
    protected function _setMeta($key, $value, $isUTF8 = false) {
        if ($isUTF8) {
            $value = $this->_toUTF16($value);
        }

        $this->$key = $value;
    }

    protected function _checkOutput() {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                $this->error("Some data has already been output, can't send PDF file (output started at $file:$line)");
            }
        }

        if (ob_get_length()) {
            // The output buffer is not empty
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean it
                ob_clean();
            } else {
                $this->error('Some data has already been output, can\'t send PDF file');
            }
        }
    }

    protected function _getPageSize($size) {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->stdPageSizes[$size])) {
                $this->error('Unknown page size: ' . $size);
            }

            $a = $this->stdPageSizes[$size];
            return array($a[0] / $this->scaleFactor, $a[1] / $this->scaleFactor);
        } else {
            if ($size[0] > $size[1]) {
                return array($size[1], $size[0]);
            } else {
                return $size;
            }
        }
    }

    protected function _beginPage($orientation, $size) {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->fontFamily = '';
        // Check page orientation
        $orientation || $orientation = $this->defOrientation;
        $orientation = strtoupper($orientation[0]);

        // Check page size
        $size || $size = $this->defPageSize;
        $size = $this->_getPageSize($size);

        if ($orientation != $this->curOrientation || $size[0] != $this->curPageSize[0] || $size[1] != $this->curPageSize[1]) {
            // New size or orientation
            if ($orientation == 'P') {
                $this->width = $size[0];
                $this->height = $size[1];
            } else {
                $this->width = $size[1];
                $this->height = $size[0];
            }

            $this->widthPt = $this->width * $this->scaleFactor;
            $this->heightPt = $this->height * $this->scaleFactor;
            $this->pageBreakTrigger = $this->height - $this->bMargin;
            $this->curOrientation = $orientation;
            $this->curPageSize = $size;
        }

        if ($orientation != $this->defOrientation || $size[0] != $this->defPageSize[0] || $size[1] != $this->defPageSize[1]) {
            $this->pageSizes[$this->page] = array($this->widthPt, $this->heightPt);
        }
    }

    protected function _endPage() {
        $this->state = 1;
    }

    // Load a font definition file from the font directory
    protected function _loadFont($font) {
        if (!file_exists($fontpath = $this->fontpath . $font)) {
            $this->error('Could not load font definition ' . $fontpath);
        }

        $font = require $fontpath;

        if (!isset($font['name'])) {
            $this->error('Invalid font definition file');
        }

        return $font;
    }

    // Escape special characters in strings
    protected function _escape($s) {
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        $s = str_replace("\r", '\\r', $s);

        return $s;
    }

    // Format a text string
    protected function _textString($s) {
        return '(' . $this->_escape($s) . ')';
    }

    // Convert UTF-8 to UTF-16BE with BOM
    protected function _toUTF16($s) {
        $res = "\xFE\xFF";
        $nb = strlen($s);
        $i = 0;

        while ($i < $nb) {
            if (($c1 = ord($s[$i++])) >= 224) {
                // 3-byte character
                $c2 = ord($s[$i++]);
                $c3 = ord($s[$i++]);
                $res .= chr((($c1 & 0x0F) << 4) + (($c2 & 0x3C) >> 2));
                $res .= chr((($c2 & 0x03) << 6) + ($c3 & 0x3F));
            } elseif ($c1 >= 192) {
                // 2-byte character
                $c2 = ord($s[$i++]);
                $res .= chr(($c1 & 0x1C) >> 2);
                $res .= chr((($c1 & 0x03) << 6) + ($c2 & 0x3F));
            } else {
                // Single-byte character
                $res .= "\0" . chr($c1);
            }
        }

        return $res;
    }

    // Underline text
    protected function _doUnderline($x, $y, $txt) {
        $up = $this->curFont['up'];
        $ut = $this->curFont['ut'];
        $w  = $this->getStringWidth($txt) + $this->ws * substr_count($txt, ' ');

        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->scaleFactor, ($this->height - ($y - $up / 1000 * $this->fontSize)) * $this->scaleFactor, $w * $this->scaleFactor, -$ut / 1000 * $this->fontSizePt);
    }

    // Extract info from a JPEG file
    protected function _parseJpg($file) {
        if (!$a = getimagesize($file)) {
            $this->error('Missing or incorrect image file: ' . $file);
        }

        if ($a[2] != 2) {
            $this->error('Not a JPEG file: ' . $file);
        }

        if (!isset($a['channels']) || $a['channels'] == 3) {
            $colspace = 'DeviceRGB';
        } elseif ($a['channels'] == 4) {
            $colspace = 'DeviceCMYK';
        } else {
            $colspace = 'DeviceGray';
        }

        $bpc = isset($a['bits']) ? $a['bits'] : 8;
        $data = file_get_contents($file);

        return array(
            'w'    => $a[0],
            'h'    => $a[1],
            'cs'   => $colspace,
            'bpc'  => $bpc,
            'f'    => 'DCTDecode',
            'data' => $data
        );
    }

    // Extract info from a PNG file
    protected function _parsePng($file) {
        if (!($f = fopen($file, 'rb'))) {
            $this->error('Can\'t open image file: ' . $file);
        }

        $info = $this->_parsePngStream($f, $file);
        fclose($f);

        return $info;
    }

    // Check signature
    protected function _parsePngStream($f, $file) {
        if ($this->_readStream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            $this->error('Not a PNG file: ' . $file);
        }

        // Read header chunk
        $this->_readStream($f, 4);
        if ($this->_readStream($f, 4) != 'IHDR') {
            $this->error('Incorrect PNG file: ' . $file);
        }

        $w = $this->_readInt($f);
        $h = $this->_readInt($f);

        if (($bpc = ord($this->_readStream($f, 1))) > 8) {
            $this->error('16-bit depth not supported: ' . $file);
        }

        if (($ct = ord($this->_readStream($f, 1))) == 0 || $ct == 4) {
            $colspace = 'DeviceGray';
        } elseif ($ct == 2 || $ct == 6) {
            $colspace = 'DeviceRGB';
        } elseif ($ct == 3) {
            $colspace = 'Indexed';
        } else {
            $this->error('Unknown color type: ' . $file);
        }

        if (ord($this->_readStream($f, 1)) != 0) {
            $this->error('Unknown compression method: ' . $file);
        }

        if (ord($this->_readStream($f, 1)) != 0) {
            $this->error('Unknown filter method: ' . $file);
        }

        if (ord($this->_readStream($f, 1)) != 0) {
            $this->error('Interlacing not supported: ' . $file);
        }

        $this->_readStream($f, 4);
        $dp = '/Predictor 15 /Colors ' . ($colspace == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w;

        // Scan chunks looking for palette, transparency and image data
        $pal = $trns = $data = '';
        do {
            $n = $this->_readInt($f);
            $type = $this->_readStream($f, 4);

            if ($type == 'PLTE') {
                // Read palette
                $pal = $this->_readStream($f, $n);
                $this->_readStream($f, 4);
            } elseif ($type == 'tRNS') {
                // Read transparency info
                $t = $this->_readStream($f, $n);
                if ($ct == 0) {
                    $trns = array(ord(substr($t, 1, 1)));
                } elseif ($ct == 2) {
                    $trns = array(
                        ord(substr($t, 1, 1)),
                        ord(substr($t, 3, 1)),
                        ord(substr($t, 5, 1))
                    );
                } else {
                    if (($pos = strpos($t, chr(0))) !== false) {
                        $trns = array($pos);
                    }
                }

                $this->_readStream($f, 4);
            } elseif ($type == 'IDAT') {
                // Read image data block
                $data .= $this->_readStream($f, $n);
                $this->_readStream($f, 4);
            } elseif ($type == 'IEND') {
                break;
            } else {
                $this->_readStream($f, $n + 4);
            }
        } while ($n);

        if ($colspace == 'Indexed' && empty($pal)) {
            $this->error('Missing palette in ' . $file);
        }

        $info = array(
            'w'    => $w,
            'h'    => $h,
            'cs'   => $colspace,
            'bpc'  => $bpc,
            'f'    => 'FlateDecode',
            'dp'   => $dp,
            'pal'  => $pal,
            'trns' => $trns
        );

        if ($ct >= 4) {
            // Extract alpha channel
            if (!function_exists('gzuncompress')) {
                $this->error('Zlib not available, can\'t handle alpha channel: ' . $file);
            }

            $data = gzuncompress($data);
            $color = $alpha = '';

            if ($ct == 4) {
                // Gray image
                $len = 2 * $w;
                for ($i = 0; $i < $h; $i++) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.)./s', '$1', $line);
                    $alpha .= preg_replace('/.(.)/s', '$1', $line);
                }
            } else {
                // RGB image
                $len = 4 * $w;
                for ($i = 0; $i < $h; $i++) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.{3})./s', '$1', $line);
                    $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
                }
            }

            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);

            if ($this->pdfVersion < '1.4') {
                $this->pdfVersion = '1.4';
            }
        }

        $info['data'] = $data;

        return $info;
    }

    // Read n bytes from stream
    protected function _readStream($f, $n) {
        $res = '';
        while ($n > 0 && !feof($f)) {
            if (($s = fread($f, $n)) === false) {
                $this->error('Error while reading stream');
            }

            $n -= strlen($s);
            $res .= $s;
        }

        if ($n > 0) {
            $this->error('Unexpected end of stream');
        }

        return $res;
    }

    // Read a 4-byte integer from stream
    protected function _readInt($f) {
        $a = unpack('Ni', $this->_readStream($f, 4));

        return $a['i'];
    }

    // Extract info from a GIF file (via PNG conversion)
    protected function _parseGif($file) {
        if (!function_exists('imagepng')) {
            $this->error('GD extension is required for GIF support');
        }

        if (!function_exists('imagecreatefromgif')) {
            $this->error('GD has no GIF read support');
        }

        if (!$im = imagecreatefromgif($file)) {
            $this->error('Missing or incorrect image file: ' . $file);
        }

        imageinterlace($im, 0);

        if ($f = @fopen('php://temp', 'rb+')) {
            // Perform conversion in memory
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            fwrite($f, $data);
            rewind($f);
            $info = $this->_parsePngStream($f, $file);
            fclose($f);
        } else {
            // Use temporary file
            if (!$tmp = tempnam('.', 'gif')) {
                $this->error('Unable to create a temporary file');
            }

            if (!imagepng($im, $tmp)) {
                $this->error('Error while saving to temporary file');
            }

            imagedestroy($im);
            $info = $this->_parsePng($tmp);
            unlink($tmp);
        }

        return $info;
    }

    // Begin a new object
    protected function _newObj() {
        $this->objNum++;
        $this->offsets[$this->objNum] = strlen($this->buffer);
        $this->_out($this->objNum . ' 0 obj');
    }

    protected function _putStream($s) {
        $this->_out('stream');
        $this->_out($s);
        $this->_out('endstream');
    }

    // Add a line to the document
    protected function _out($s) {
        if ($this->state == 2) {
            $this->pages[$this->page] .= $s . "\n";
        } else {
            $this->buffer .= $s . "\n";
        }
    }

    protected function _putPages() {
        $nb = $this->page;

        if (!empty($this->aliasNbPages)) {
            // Replace number of pages
            for ($n = 1; $n <= $nb; $n++) {
                $this->pages[$n] = str_replace($this->aliasNbPages, $nb, $this->pages[$n]);
            }
        }

        if ($this->defOrientation == 'P') {
            $wPt = $this->defPageSize[0] * $this->scaleFactor;
            $hPt = $this->defPageSize[1] * $this->scaleFactor;
        } else {
            $wPt = $this->defPageSize[1] * $this->scaleFactor;
            $hPt = $this->defPageSize[0] * $this->scaleFactor;
        }

        $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';

        for ($n = 1; $n <= $nb; $n++) {
            // Page
            $this->_newObj();
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');

            if (isset($this->pageSizes[$n])) {
                $this->_out(sprintf(
                    '/MediaBox [0 0 %.2F %.2F]',
                    $this->pageSizes[$n][0],
                    $this->pageSizes[$n][1]
                ));
            }

            $this->_out('/Resources 2 0 R');

            if (isset($this->pageLinks[$n])) {
                // Links
                $annots = '/Annots [';

                foreach ($this->pageLinks[$n] as $pl) {
                    $rect = sprintf(
                        '%.2F %.2F %.2F %.2F',
                        $pl[0],
                        $pl[1],
                        $pl[0] + $pl[2],
                        $pl[1] - $pl[3]
                    );
                    $annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';

                    if (is_string($pl[4])) {
                        $annots .= '/A <</S /URI /URI ' . $this->_textString($pl[4]) . '>>>>';
                    } else {
                        $l = $this->links[$pl[4]];
                        $h = isset($this->pageSizes[$l[0]]) ? $this->pageSizes[$l[0]][1] : $hPt;
                        $annots .= sprintf(
                            '/Dest [%d 0 R /XYZ 0 %.2F null]>>',
                            1 + 2 * $l[0],
                            $h - $l[1] * $this->scaleFactor
                        );
                    }
                }

                $this->_out($annots . ']');
            }

            if ($this->pdfVersion > '1.3') {
                $this->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }

            $this->_out('/Contents ' . ($this->objNum + 1) . ' 0 R>>');
            $this->_out('endobj');

            // Page content
            $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
            $this->_newObj();
            $this->_out('<<' . $filter . '/Length ' . strlen($p) . '>>');
            $this->_putStream($p);
            $this->_out('endobj');
        }
        // Pages root
        $this->offsets[1] = strlen($this->buffer);
        $this->_out('1 0 obj');
        $this->_out('<</Type /Pages');
        $kids = '/Kids [';

        for ($i = 0; $i < $nb; $i++) {
            $kids .= (3 + 2 * $i) . ' 0 R ';
        }

        $this->_out($kids . ']');
        $this->_out('/Count ' . $nb);
        $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt));
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putfonts() {
        $nf = $this->objNum;

        foreach ($this->diffs as $diff) {
            // Encodings
            $this->_newObj();
            $this->_out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $diff . ']>>');
            $this->_out('endobj');
        }

        foreach ($this->fontFiles as $file => $info) {
            // Font file embedding
            $this->_newObj();
            $this->fontFiles[$file]['n'] = $this->objNum;
            $font = file_get_contents($this->fontpath . $file, true);

            if (!$font) {
                $this->error('Font file not found: ' . $file);
            }

            if (!($compressed = (substr($file, -2) == '.z')) && isset($info['length2'])) {
                $font = substr($font, 6, $info['length1']) . substr($font, 6 + $info['length1'] + 6, $info['length2']);
            }

            $this->_out('<</Length ' . strlen($font));
            if ($compressed) {
                $this->_out('/Filter /FlateDecode');
            }

            $this->_out('/Length1 ' . $info['length1']);
            if (isset($info['length2'])) {
                $this->_out('/Length2 ' . $info['length2'] . ' /Length3 0');
            }

            $this->_out('>>');
            $this->_putStream($font);
            $this->_out('endobj');
        }

        foreach ($this->fonts as $k => $font) {
            // Font objects
            $this->fonts[$k]['n'] = $this->objNum + 1;
            $type = $font['type'];
            $name = $font['name'];

            if ($type == 'Core') {
                // Core font
                $this->_newObj();
                $this->_out('<</Type /Font');
                $this->_out('/BaseFont /' . $name);
                $this->_out('/Subtype /Type1');

                if ($name != 'Symbol' && $name != 'ZapfDingbats') {
                    $this->_out('/Encoding /WinAnsiEncoding');
                }

                $this->_out('>>');
                $this->_out('endobj');
            } elseif ($type == 'Type1' || $type == 'TrueType') {
                // Additional Type1 or TrueType/OpenType font
                $this->_newObj();
                $this->_out('<</Type /Font');
                $this->_out('/BaseFont /' . $name);
                $this->_out('/Subtype /' . $type);
                $this->_out('/FirstChar 32 /LastChar 255');
                $this->_out('/Widths ' . ($this->objNum + 1) . ' 0 R');
                $this->_out('/FontDescriptor ' . ($this->objNum + 2) . ' 0 R');

                if (isset($font['diffn'])) {
                    $this->_out('/Encoding ' . ($nf + $font['diffn']) . ' 0 R');
                } else {
                    $this->_out('/Encoding /WinAnsiEncoding');
                }

                $this->_out('>>');
                $this->_out('endobj');
                // Widths
                $this->_newObj();
                $cw = &$font['cw'];
                $s = '[';

                for ($i = 32; $i <= 255; $i++) {
                    $s .= $cw[chr($i)] . ' ';
                }

                $this->_out($s . ']');
                $this->_out('endobj');
                // Descriptor
                $this->_newObj();
                $s = '<</Type /FontDescriptor /FontName /' . $name;

                foreach ($font['desc'] as $_k => $v) {
                    $s .= ' /' . $_k . ' ' . $v;
                }

                if (!empty($font['file'])) {
                    $s .= ' /FontFile' . ($type == 'Type1' ? '' : '2') . ' ' . $this->fontFiles[$font['file']]['n'] . ' 0 R';
                }

                $this->_out($s . '>>');
                $this->_out('endobj');
            } else {
                // Allow for additional types
                $mtd = '_put' . strtolower($type);
                if (!method_exists($this, $mtd)) {
                    $this->error('Unsupported font type: ' . $type);
                }

                $this->$mtd($font);
            }
        }
    }

    protected function _putImages() {
        foreach (array_keys($this->images) as $file) {
            $this->_putImage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    protected function _putImage(&$info) {
        $this->_newObj();
        $info['n'] = $this->objNum;
        $this->_out('<</Type /XObject');
        $this->_out('/Subtype /Image');
        $this->_out('/Width ' . $info['w']);
        $this->_out('/Height ' . $info['h']);

        if ($info['cs'] == 'Indexed') {
            $this->_out('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->objNum + 1) . ' 0 R]');
        } else {
            $this->_out('/ColorSpace /' . $info['cs']);
            if ($info['cs'] == 'DeviceCMYK') {
                $this->_out('/Decode [1 0 1 0 1 0 1 0]');
            }
        }

        $this->_out('/BitsPerComponent ' . $info['bpc']);
        if (isset($info['f'])) {
            $this->_out('/Filter /' . $info['f']);
        }

        if (isset($info['dp'])) {
            $this->_out('/DecodeParms <<' . $info['dp'] . '>>');
        }

        if (isset($info['trns']) && is_array($info['trns'])) {
            $trns = '';
            for ($i = 0; $i < count($info['trns']); $i++) {
                $trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
            }

            $this->_out('/Mask [' . $trns . ']');
        }

        if (isset($info['smask'])) {
            $this->_out('/SMask ' . ($this->objNum + 1) . ' 0 R');
        }

        $this->_out('/Length ' . strlen($info['data']) . '>>');
        $this->_putStream($info['data']);
        $this->_out('endobj');

        // Soft mask
        if (isset($info['smask'])) {
            $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $info['w'];
            $smask = array('w' => $info['w'], 'h' => $info['h'], 'cs' => 'DeviceGray', 'bpc' => 8, 'f' => $info['f'], 'dp' => $dp, 'data' => $info['smask']);
            $this->_putImage($smask);
        }

        // Palette
        if ($info['cs'] == 'Indexed') {
            $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
            $pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
            $this->_newObj();
            $this->_out('<<' . $filter . '/Length ' . strlen($pal) . '>>');
            $this->_putStream($pal);
            $this->_out('endobj');
        }
    }

    protected function _putXObjectDict() {
        foreach ($this->images as $image) {
            $this->_out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
        }
    }

    protected function _putResourceDict() {
        $this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_out('/Font <<');

        foreach ($this->fonts as $font) {
            $this->_out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }

        $this->_out('>>');
        $this->_out('/XObject <<');
        $this->_putXObjectDict();
        $this->_out('>>');
    }

    protected function _putResources() {
        $this->_putfonts();
        $this->_putImages();
        // Resource dictionary
        $this->offsets[2] = strlen($this->buffer);
        $this->_out('2 0 obj');
        $this->_out('<<');
        $this->_putResourceDict();
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putInfo() {
        $this->_out('/Producer ' . $this->_textString('FPDF ' . self::VERSION));

        $docMeta = array(
            '/Title '    => defined('FPDF_TITLE')    ? FPDF_TITLE    : $this->title,
            '/Subject '  => defined('FPDF_SUBJECT')  ? FPDF_SUBJECT  : $this->subject,
            '/Author '   => defined('FPDF_AUTHOR')   ? FPDF_AUTHOR   : $this->author,
            '/Keywords ' => defined('FPDF_KEYWORDS') ? FPDF_KEYWORDS : $this->keywords,
            '/Creator '  => defined('FPDF_CREATOR')  ? FPDF_CREATOR  : $this->creator,
        );

        foreach ($docMeta as $metaKey => $metaValue) {
            if (!empty($metaValue)) {
                $this->_out($metaKey.$this->_textString($metaValue));
            }
        }

        $this->_out('/CreationDate '.$this->_textString('D:'.@date('YmdHis')));
    }

    protected function _putCatalog() {
        $this->_out('/Type /Catalog');
        $this->_out('/Pages 1 0 R');

        if ($this->zoomMode == 'fullpage') {
            $this->_out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->zoomMode == 'fullwidth') {
            $this->_out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->zoomMode == 'real') {
            $this->_out('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->zoomMode)) {
            $this->_out('/OpenAction [3 0 R /XYZ null null ' . sprintf('%.2F', $this->zoomMode / 100) . ']');
        }

        if ($this->layoutMode == 'single') {
            $this->_out('/PageLayout /SinglePage');
        } elseif ($this->layoutMode == 'continuous') {
            $this->_out('/PageLayout /OneColumn');
        } elseif ($this->layoutMode == 'two') {
            $this->_out('/PageLayout /TwoColumnLeft');
        }
    }

    protected function _putHeader() {
        $this->_out('%PDF-' . $this->pdfVersion);
    }

    protected function _putTrailer() {
        $this->_out('/Size ' . ($this->objNum + 1));
        $this->_out('/Root ' . $this->objNum . ' 0 R');
        $this->_out('/Info ' . ($this->objNum - 1) . ' 0 R');
    }

    protected function _endDoc() {
        $this->_putHeader();
        $this->_putPages();
        $this->_putResources();
        // Info
        $this->_newObj();
        $this->_out('<<');
        $this->_putInfo();
        $this->_out('>>');
        $this->_out('endobj');
        // Catalog
        $this->_newObj();
        $this->_out('<<');
        $this->_putCatalog();
        $this->_out('>>');
        $this->_out('endobj');
        // Cross-ref
        $o = strlen($this->buffer);
        $this->_out('xref');
        $this->_out('0 ' . ($this->objNum + 1));
        $this->_out('0000000000 65535 f ');
        for ($i = 1; $i <= $this->objNum; $i++) {
            $this->_out(sprintf('%010d 00000 n ', $this->offsets[$i]));
        }

        // Trailer
        $this->_out('trailer');
        $this->_out('<<');
        $this->_putTrailer();
        $this->_out('>>');
        $this->_out('startxref');
        $this->_out($o);
        $this->_out('%%EOF');
        $this->state = 3;
    }
}
// End of class

// Handle special IE contype request
if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
    header('Content-Type: application/pdf');
    exit;
}
