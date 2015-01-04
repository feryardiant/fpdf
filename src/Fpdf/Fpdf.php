<?php

namespace Fpdf;

class Fpdf extends AbstractFpdf {
    const
        VERSION = '1.7.0';

    protected
        // current page number
        $page = 0,
        // current object number
        $n = 2,
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
        $k,
        // default and current orientation
        $DefOrientation, $CurOrientation,
        // standard page sizes
        $StdPageSizes = array(
            'a3'     => array( 841.89, 1190.55 ),
            'a4'     => array( 595.28, 841.89  ),
            'a5'     => array( 420.94, 595.28  ),
            'letter' => array( 612,    792     ),
            'legal'  => array( 612,    1008    ),
        ),
        // default and current page size
        $DefPageSize, $CurPageSize,
        $PageSizes = array(), // used for pages with non default sizes or orientations
        // dimensions of current page in points and unit
        $wPt, $hPt, $w, $h,
        // left, top, right, bottom and cell margin
        $lMargin, $tMargin, $rMargin, $bMargin, $cMargin,
        // current position in user unit
        $x, $y,
        // height of last printed cell
        $lasth = 0,
        // line width in user unit
        $LineWidth,
        // array of core font names
        $CoreFonts = array(
            'courier',
            'helvetica',
            'times',
            'symbol',
            'zapfdingbats',
        ),
        // array of used fonts
        $fonts = array(),
        // array of font files
        $FontFiles = array(),
        // array of encoding differences
        $diffs = array(),
        // current font family
        $FontFamily = '',
        // current font style
        $FontStyle = '',
        // underlining flag
        $underline = false,
        // current font info
        $CurrentFont,
        // current font size in points
        $FontSizePt = 12,
        // current font size in user unit
        $FontSize,
        // commands for drawing color
        $DrawColor = self::DRAW_COLOR,
        // commands for filling color
        $FillColor = self::FILL_COLOR,
        // commands for text color
        $TextColor = self::TEXT_COLOR,
        // indicates whether fill and text colors are different
        $ColorFlag = false,
        // word spacing
        $ws = 0,
        // array of used images
        $images = array(),
        // array of links in pages
        $PageLinks = array(),
        // array of internal links
        $links = array(),
        // Page breaking
        $AutoPageBreak, $PageBreakTrigger,
        // flag set when processing header
        $InHeader = false,
        // flag set when processing footer
        $InFooter = false,
        // zoom display mode
        $ZoomMode = 'default',
        // layout display mode
        $LayoutMode = 'default',
        // title
        $title, $subject, $author, $keywords, $creator,
        // alias for total number of pages
        $AliasNbPages,
        // PDF version number
        $PDFVersion = '1.3';

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
        // Some checks
        // Check availability of %F
        if (sprintf('%.1F', 1.0) != '1.0') {
            $this->Error('This version of PHP is not supported');
        }

        // Check mbstring overloading
        if (ini_get('mbstring.func_overload') & 2) {
            $this->Error('mbstring overloading must be disabled');
        }

        // Ensure runtime magic quotes are disabled
        if (get_magic_quotes_runtime()) {
            @set_magic_quotes_runtime(0);
        }

        // Font path
        $this->_validateFontpath();

        // Scale factor
        if ($unit == 'pt') {
            $this->k = 1;
        } elseif ($unit == 'mm') {
            $this->k = 72 / 25.4;
        } elseif ($unit == 'cm') {
            $this->k = 72 / 2.54;
        } elseif ($unit == 'in') {
            $this->k = 72;
        } else {
            $this->Error('Incorrect unit: ' . $unit);
        }

        // Page sizes
        $size = $this->_getpagesize($size);
        $this->DefPageSize = $size;
        $this->CurPageSize = $size;

        // Page orientation
        $orientation = strtolower($orientation);
        if (in_array($orientation, array('p', 'portrait'))) {
            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        } elseif (in_array($orientation, array('l', 'landscape'))) {
            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        } else {
            $this->Error('Incorrect orientation: ' . $orientation);
        }

        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;
        // Page margins (1 cm)
        $margin = 28.35 / $this->k;
        $this->SetMargins($margin, $margin);
        // Interior cell margin (1 mm)
        $this->cMargin = $margin / 10;
        // Line width (0.2 mm)
        $this->LineWidth = .567 / $this->k;
        // Automatic page break
        $this->SetAutoPageBreak(true, 2 * $margin);
        // Default display mode
        $this->SetDisplayMode('default');
        // Enable compression
        $this->SetCompression(true);
    }

    // Set left, top and right margins
    public function SetMargins($left, $top, $right = null) {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right ?: $left;
    }

    // Set left margin
    public function SetLeftMargin($margin) {
        $this->lMargin = $margin;
        if ($this->page > 0 && $this->x < $margin) {
            $this->x = $margin;
        }
    }

    // Set top margin
    public function SetTopMargin($margin) {
        $this->tMargin = $margin;
    }

    // Set right margin
    public function SetRightMargin($margin) {
        $this->rMargin = $margin;
    }

    // Set auto page break mode and triggering margin
    public function SetAutoPageBreak($auto, $margin = 0) {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    // Set display mode in viewer
    public function SetDisplayMode($zoom, $layout = 'default') {
        if (in_array($zoom, array('fullpage', 'fullwidth', 'real', 'default')) || !is_string($zoom)) {
            $this->ZoomMode = $zoom;
        } else {
            $this->Error('Incorrect zoom display mode: ' . $zoom);
        }

        if (in_array($zoom, array('single', 'continuous', 'two', 'default')) || !is_string($zoom)) {
            $this->LayoutMode = $layout;
        } else {
            $this->Error('Incorrect layout display mode: ' . $layout);
        }
    }

    // Set page compression
    public function SetCompression($compress) {
        $this->compress = function_exists('gzcompress') ? $compress : false;
    }

    // Title of document
    public function SetTitle($title, $isUTF8 = false) {
        $this->_SetMeta('title', $title);
    }

    // Subject of document
    public function SetSubject($subject, $isUTF8 = false) {
        $this->_SetMeta('subject', $subject);
    }

    // Author of document
    public function SetAuthor($author, $isUTF8 = false) {
        $this->_SetMeta('author', $author);
    }

    // Keywords of document
    public function SetKeywords($keywords, $isUTF8 = false) {
        $this->_SetMeta('keywords', $keywords);
    }

    // Creator of document
    public function SetCreator($creator, $isUTF8 = false) {
        $this->_SetMeta('creator', $creator);
    }

    // Define an alias for total number of pages
    public function AliasNbPages($alias = '{nb}') {
        $this->AliasNbPages = $alias;
    }

    // Begin document
    public function Open() {
        $this->state = 1;
    }

    // Terminate document
    public function Close() {
        if ($this->state == 3) {
            return;
        }

        if ($this->page == 0) {
            $this->AddPage();
        }

        // Page footer
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        // Close page
        $this->_endpage();
        // Close document
        $this->_enddoc();
    }

    // Start a new page
    public function AddPage($orientation = '', $size = '') {
        if ($this->state == 0) {
            $this->Open();
        }

        $family = $this->FontFamily;
        $style = $this->FontStyle . ($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;

        if ($this->page > 0) {
            // Page footer
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            // Close page
            $this->_endpage();
        }

        // Start new page
        $this->_beginpage($orientation, $size);
        // Set line cap style to square
        $this->_out('2 J');
        // Set line width
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->k));
        // Set font
        if ($family) {
            $this->SetFont($family, $style, $fontsize);
        }

        // Set colors
        $this->DrawColor = $dc;
        if ($dc != self::DRAW_COLOR) {
            $this->_out($dc);
        }

        $this->FillColor = $fc;
        if ($fc != self::FILL_COLOR) {
            $this->_out($fc);
        }

        $this->TextColor = $tc;
        $this->ColorFlag = $cf;

        // Page header
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;

        // Restore line width
        if ($this->LineWidth != $lw) {
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w', $lw * $this->k));
        }
        // Restore font
        if ($family) {
            $this->SetFont($family, $style, $fontsize);
        }

        // Restore colors
        if ($this->DrawColor != $dc) {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if ($this->FillColor != $fc) {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
    }

    // To be implemented in your own inherited class
    public function Header() {}

    // To be implemented in your own inherited class
    public function Footer() {}

    // Get current page number
    public function PageNo() {
        return $this->page;
    }

    // Set color for all stroking operations
    public function SetDrawColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->DrawColor = sprintf('%.3F G', $r / 255);
        } else {
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }

        if ($this->page > 0) {
            $this->_out($this->DrawColor);
        }
    }

    // Set color for all filling operations
    public function SetFillColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->FillColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }

        $this->ColorFlag = ($this->FillColor != $this->TextColor);
        if ($this->page > 0) {
            $this->_out($this->FillColor);
        }
    }

    // Set color for text
    public function SetTextColor($r, $g = null, $b = null) {
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->TextColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }

        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    // Get width of a string in the current font
    public function GetStringWidth($s) {
        $s = (string) $s;
        $cw = &$this->CurrentFont['cw'];
        $w = 0;
        $l = strlen($s);
        for ($i = 0; $i < $l; $i++) {
            $w += $cw[$s[$i]];
        }

        return $w * $this->FontSize / 1000;
    }

    // Set line width
    public function SetLineWidth($width) {
        $this->LineWidth = $width;
        if ($this->page > 0) {
            $this->_out(sprintf('%.2F w', $width * $this->k));
        }
    }

    // Draw a line
    public function Line($x1, $y1, $x2, $y2) {
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k));
    }

    // Draw a rectangle
    public function Rect($x, $y, $w, $h, $style = '') {
        if ($style == 'F') {
            $op = 'f';
        } elseif (in_array($style, array('FD', 'DF'))) {
            $op = 'B';
        } else {
            $op = 'S';
        }

        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
    }

    // Add a TrueType, OpenType or Type1 font
    public function AddFont($family, $style = '', $file = '') {
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

        $info = $this->_loadfont($file);
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
                $this->FontFiles[$info['file']] = array('length1' => $info['originalsize']);
            } else {
                $this->FontFiles[$info['file']] = array('length1' => $info['size1'], 'length2' => $info['size2']);
            }
        }

        $this->fonts[$fontkey] = $info;
    }

    // Select a font; size given in points
    public function SetFont($family, $style = '', $size = 0) {
        $family || $family = $this->FontFamily;
        $size || $size = $this->FontSizePt;
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
        if ($this->FontFamily == $family && $this->FontStyle == $style && $this->FontSizePt == $size) {
            return;
        }

        // Test if font is already loaded
        $fontkey = $family . $style;
        if (!isset($this->fonts[$fontkey])) {
            // Test if one of the core fonts
            if ($family == 'arial') {
                $family = 'helvetica';
            }

            if (in_array($family, $this->CoreFonts)) {
                if (in_array($family, array('symbol', 'zapfdingbats'))) {
                    $style = '';
                }

                $fontkey = $family . $style;
                if (!isset($this->fonts[$fontkey])) {
                    $this->AddFont($family, $style);
                }
            } else {
                $this->Error('Undefined font: ' . $family . ' ' . $style);
            }
        }

        // Select it
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        $this->CurrentFont = &$this->fonts[$fontkey];

        if ($this->page > 0) {
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
        }
    }

    // Set font size in points
    public function SetFontSize($size) {
        if ($this->FontSizePt == $size) {
            return;
        }

        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
        }
    }

    // Create a new internal link
    public function AddLink() {
        $n = count($this->links) + 1;
        $this->links[$n] = array(0, 0);

        return $n;
    }

    // Set destination of internal link
    public function SetLink($link, $y = 0, $page = -1) {
        if ($y == -1) {
            $y = $this->y;
        }

        if ($page == -1) {
            $page = $this->page;
        }

        $this->links[$link] = array($page, $y);
    }

    // Put a link on the page
    public function Link($x, $y, $w, $h, $link) {
        $this->PageLinks[$this->page][] = array($x * $this->k, $this->hPt - $y * $this->k, $w * $this->k, $h * $this->k, $link);
    }

    // Output a string
    public function Text($x, $y, $txt) {
        $s = sprintf(
            'BT %.2F %.2F Td (%s) Tj ET',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $this->_escape($txt)
        );

        if ($this->underline && $txt != '') {
            $s .= ' ' . $this->_dounderline($x, $y, $txt);
        }

        if ($this->ColorFlag) {
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
        }

        $this->_out($s);
    }

    // Accept automatic page break or not
    public function AcceptPageBreak() {
        return $this->AutoPageBreak;
    }

    // Output a cell
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
        $k = $this->k;

        if (
            $this->y + $h > $this->PageBreakTrigger &&
            !$this->InHeader &&
            !$this->InFooter &&
            $this->AcceptPageBreak()
        ) {
            // Automatic page break
            $x = $this->x;

            if (($ws = $this->ws) > 0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }

            $this->AddPage($this->CurOrientation, $this->CurPageSize);
            $this->x = $x;

            if ($ws > 0) {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
            }
        }

        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
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
                ($this->h - $this->y) * $k,
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
                    ($this->h - $y) * $k,
                    $x * $k,
                    ($this->h - ($y + $h)) * $k
                );
            }

            if (strpos($border, 'T') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k,
                    ($this->h - $y) * $k,
                    ($x + $w) * $k,
                    ($this->h - $y) * $k
                );
            }

            if (strpos($border, 'R') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    ($x + $w) * $k,
                    ($this->h - $y) * $k,
                    ($x + $w) * $k,
                    ($this->h - ($y + $h)) * $k
                );
            }

            if (strpos($border, 'B') !== false) {
                $s .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $k,
                    ($this->h - ($y + $h)) * $k,
                    ($x + $w) * $k,
                    ($this->h - ($y + $h)) * $k
                );
            }
        }

        if ($txt !== '') {
            if ($align == 'R') {
                $dx = $w - $this->cMargin - $this->GetStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($w - $this->GetStringWidth($txt)) / 2;
            } else {
                $dx = $this->cMargin;
            }

            if ($this->ColorFlag) {
                $s .= 'q ' . $this->TextColor . ' ';
            }

            $txt2 = str_replace(')', '\\)', str_replace('(', '\\(', str_replace('\\', '\\\\', $txt)));
            $s .= sprintf(
                'BT %.2F %.2F Td (%s) Tj ET',
                ($this->x + $dx) * $k,
                ($this->h - ($this->y + .5 * $h + .3 * $this->FontSize)) * $k,
                $txt2
            );

            if ($this->underline) {
                $s .= ' ' . $this->_dounderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->FontSize, $txt);
            }

            if ($this->ColorFlag) {
                $s .= ' Q';
            }

            if ($link) {
                $this->Link(
                    $this->x + $dx,
                    $this->y + .5 * $h - .5 * $this->FontSize,
                    $this->GetStringWidth($txt),
                    $this->FontSize,
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
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
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

                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
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

                    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->FontSize / ($ns - 1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->ws * $this->k));
                    }

                    $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
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

        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    // Output text in flowing mode
    public function Write($h, $txt, $link = '') {
        $cw = &$this->CurrentFont['cw'];
        $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        $sep = -1;
        $i = $j = $l = 0;
        $nl = 1;

        while ($i < $nb) {
            // Get next character
            if (($c = $s[$i]) == "\n") {
                // Explicit line break
                $this->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;

                if ($nl == 1) {
                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
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
                        $w = $this->w - $this->rMargin - $this->x;
                        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
                        $i++;
                        $nl++;
                        continue;
                    }

                    if ($i == $j) {
                        $i++;
                    }

                    $this->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
                } else {
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), 0, 2, '', 0, $link);
                    $i = $sep + 1;
                }

                $sep = -1;
                $j = $i;
                $l = 0;

                if ($nl == 1) {
                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
                }

                $nl++;
            } else {
                $i++;
            }
        }

        // Last chunk
        if ($i != $j) {
            $this->Cell($l / 1000 * $this->FontSize, $h, substr($s, $j), 0, 0, '', 0, $link);
        }
    }

    // Line feed; default value is last cell height
    public function Ln($h = null) {
        $this->x = $this->lMargin;
        if ($h === null) {
            $this->y += $this->lasth;
        } else {
            $this->y += $h;
        }
    }

    // Put an image on the page
    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '') {
        if (!isset($this->images[$file])) {
            // First use of this image, get info
            if ($type == '') {
                if (!$pos = strrpos($file, '.')) {
                    $this->Error('Image file has no extension and no type was specified: ' . $file);
                }

                $type = substr($file, $pos + 1);
            }

            $type = strtolower($type);

            if ($type == 'jpeg') {
                $type = 'jpg';
            }

            $mtd = '_parse' . $type;
            if (!method_exists($this, $mtd)) {
                $this->Error('Unsupported image type: ' . $type);
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
            $w = -$info['w'] * 72 / $w / $this->k;
        }

        if ($h < 0) {
            $h = -$info['h'] * 72 / $h / $this->k;
        }

        if ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        }

        if ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        // Flowing mode
        if ($y === null) {
            if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
                // Automatic page break
                $x2 = $this->x;
                $this->AddPage($this->CurOrientation, $this->CurPageSize);
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
            $w * $this->k,
            $h * $this->k,
            $x * $this->k,
            ($this->h - ($y + $h)) * $this->k,
            $info['i']
        ));

        if ($link) {
            $this->Link($x, $y, $w, $h, $link);
        }
    }

    // Get x position
    public function GetX() {
        return $this->x;
    }

    // Set x position
    public function SetX($x) {
        $this->x = $x >= 0 ? $x : $this->w + $x;
    }

    // Get y position
    public function GetY() {
        return $this->y;
    }

    // Set y position and reset x
    public function SetY($y) {
        $this->x = $this->lMargin;
        $this->y = $y >= 0 ? $y : $this->h + $y;
    }

    // Set x and y positions
    public function SetXY($x, $y) {
        $this->SetY($y);
        $this->SetX($x);
    }

    // Output PDF to some destination
    public function Output($name = '', $dest = '') {
        if ($this->state < 3) {
            $this->Close();
        }

        $dest = strtoupper($dest);
        if ($dest == '') {
            if ($name == '') {
                $name = 'doc.pdf';
                $dest = 'I';
            } else {
                $dest = 'F';
            }
        }

        switch ($dest) {
            case 'I':
                // Send to standard output
                $this->_checkoutput();
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
                $this->_checkoutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                // Save to local file
                if (!($f = fopen($name, 'wb'))) {
                    $this->Error('Unable to create output file: ' . $name);
                }

                fwrite($f, $this->buffer, strlen($this->buffer));
                fclose($f);
                break;
            case 'S':
                // Return as a string
                return $this->buffer;
            default:
                $this->Error('Incorrect output destination: ' . $dest);
        }
        return '';
    }

    // Creator of document
    protected function _SetMeta($key, $value, $isUTF8 = false) {
        if ($isUTF8) {
            $value = $this->_UTF8toUTF16($value);
        }

        $this->$key = $value;
    }

    protected function _checkoutput() {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
            }
        }

        if (ob_get_length()) {
            // The output buffer is not empty
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean it
                ob_clean();
            } else {
                $this->Error('Some data has already been output, can\'t send PDF file');
            }
        }
    }

    protected function _getpagesize($size) {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->StdPageSizes[$size])) {
                $this->Error('Unknown page size: ' . $size);
            }

            $a = $this->StdPageSizes[$size];
            return array($a[0] / $this->k, $a[1] / $this->k);
        } else {
            if ($size[0] > $size[1]) {
                return array($size[1], $size[0]);
            } else {
                return $size;
            }
        }
    }

    protected function _beginpage($orientation, $size) {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
        // Check page orientation
        $orientation || $orientation = $this->DefOrientation;
        $orientation = strtoupper($orientation[0]);

        // Check page size
        $size || $size = $this->DefPageSize;
        $size = $this->_getpagesize($size);

        if ($orientation != $this->CurOrientation || $size[0] != $this->CurPageSize[0] || $size[1] != $this->CurPageSize[1]) {
            // New size or orientation
            if ($orientation == 'P') {
                $this->w = $size[0];
                $this->h = $size[1];
            } else {
                $this->w = $size[1];
                $this->h = $size[0];
            }

            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->PageBreakTrigger = $this->h - $this->bMargin;
            $this->CurOrientation = $orientation;
            $this->CurPageSize = $size;
        }

        if ($orientation != $this->DefOrientation || $size[0] != $this->DefPageSize[0] || $size[1] != $this->DefPageSize[1]) {
            $this->PageSizes[$this->page] = array($this->wPt, $this->hPt);
        }
    }

    protected function _endpage() {
        $this->state = 1;
    }

    // Load a font definition file from the font directory
    protected function _loadfont($font) {
        include $this->fontpath . $font;
        $a = get_defined_vars();

        if (!isset($a['name'])) {
            $this->Error('Could not include font definition file');
        }

        return $a;
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
    protected function _textstring($s) {
        return '(' . $this->_escape($s) . ')';
    }

    // Convert UTF-8 to UTF-16BE with BOM
    protected function _UTF8toUTF16($s) {
        $res = "\xFE\xFF";
        $nb = strlen($s);
        $i = 0;

        while ($i < $nb) {
            $c1 = ord($s[$i++]);

            if ($c1 >= 224) {
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
    protected function _dounderline($x, $y, $txt) {
        $up = $this->CurrentFont['up'];
        $ut = $this->CurrentFont['ut'];
        $w = $this->GetStringWidth($txt) + $this->ws * substr_count($txt, ' ');

        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->FontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->FontSizePt);
    }

    // Extract info from a JPEG file
    protected function _parsejpg($file) {
        if (!$a = getimagesize($file)) {
            $this->Error('Missing or incorrect image file: ' . $file);
        }

        if ($a[2] != 2) {
            $this->Error('Not a JPEG file: ' . $file);
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
    protected function _parsepng($file) {
        if (!($f = fopen($file, 'rb'))) {
            $this->Error('Can\'t open image file: ' . $file);
        }

        $info = $this->_parsepngstream($f, $file);
        fclose($f);

        return $info;
    }

    // Check signature
    protected function _parsepngstream($f, $file) {
        if ($this->_readstream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            $this->Error('Not a PNG file: ' . $file);
        }

        // Read header chunk
        $this->_readstream($f, 4);
        if ($this->_readstream($f, 4) != 'IHDR') {
            $this->Error('Incorrect PNG file: ' . $file);
        }

        $w = $this->_readint($f);
        $h = $this->_readint($f);

        if (($bpc = ord($this->_readstream($f, 1))) > 8) {
            $this->Error('16-bit depth not supported: ' . $file);
        }

        if (($ct = ord($this->_readstream($f, 1))) == 0 || $ct == 4) {
            $colspace = 'DeviceGray';
        } elseif ($ct == 2 || $ct == 6) {
            $colspace = 'DeviceRGB';
        } elseif ($ct == 3) {
            $colspace = 'Indexed';
        } else {
            $this->Error('Unknown color type: ' . $file);
        }

        if (ord($this->_readstream($f, 1)) != 0) {
            $this->Error('Unknown compression method: ' . $file);
        }

        if (ord($this->_readstream($f, 1)) != 0) {
            $this->Error('Unknown filter method: ' . $file);
        }

        if (ord($this->_readstream($f, 1)) != 0) {
            $this->Error('Interlacing not supported: ' . $file);
        }

        $this->_readstream($f, 4);
        $dp = '/Predictor 15 /Colors ' . ($colspace == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w;

        // Scan chunks looking for palette, transparency and image data
        $pal = $trns = $data = '';
        do {
            $n = $this->_readint($f);
            $type = $this->_readstream($f, 4);

            if ($type == 'PLTE') {
                // Read palette
                $pal = $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type == 'tRNS') {
                // Read transparency info
                $t = $this->_readstream($f, $n);
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

                $this->_readstream($f, 4);
            } elseif ($type == 'IDAT') {
                // Read image data block
                $data .= $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type == 'IEND') {
                break;
            } else {
                $this->_readstream($f, $n + 4);
            }
        } while ($n);

        if ($colspace == 'Indexed' && empty($pal)) {
            $this->Error('Missing palette in ' . $file);
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
                $this->Error('Zlib not available, can\'t handle alpha channel: ' . $file);
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

            if ($this->PDFVersion < '1.4') {
                $this->PDFVersion = '1.4';
            }
        }

        $info['data'] = $data;

        return $info;
    }

    // Read n bytes from stream
    protected function _readstream($f, $n) {
        $res = '';
        while ($n > 0 && !feof($f)) {
            if (($s = fread($f, $n)) === false) {
                $this->Error('Error while reading stream');
            }

            $n -= strlen($s);
            $res .= $s;
        }

        if ($n > 0) {
            $this->Error('Unexpected end of stream');
        }

        return $res;
    }

    // Read a 4-byte integer from stream
    protected function _readint($f) {
        $a = unpack('Ni', $this->_readstream($f, 4));

        return $a['i'];
    }

    // Extract info from a GIF file (via PNG conversion)
    protected function _parsegif($file) {
        if (!function_exists('imagepng')) {
            $this->Error('GD extension is required for GIF support');
        }

        if (!function_exists('imagecreatefromgif')) {
            $this->Error('GD has no GIF read support');
        }

        if (!$im = imagecreatefromgif($file)) {
            $this->Error('Missing or incorrect image file: ' . $file);
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
            $info = $this->_parsepngstream($f, $file);
            fclose($f);
        } else {
            // Use temporary file
            if (!$tmp = tempnam('.', 'gif')) {
                $this->Error('Unable to create a temporary file');
            }

            if (!imagepng($im, $tmp)) {
                $this->Error('Error while saving to temporary file');
            }

            imagedestroy($im);
            $info = $this->_parsepng($tmp);
            unlink($tmp);
        }

        return $info;
    }

    // Begin a new object
    protected function _newobj() {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->_out($this->n . ' 0 obj');
    }

    protected function _putstream($s) {
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

    protected function _putpages() {
        $nb = $this->page;

        if (!empty($this->AliasNbPages)) {
            // Replace number of pages
            for ($n = 1; $n <= $nb; $n++) {
                $this->pages[$n] = str_replace($this->AliasNbPages, $nb, $this->pages[$n]);
            }
        }

        if ($this->DefOrientation == 'P') {
            $wPt = $this->DefPageSize[0] * $this->k;
            $hPt = $this->DefPageSize[1] * $this->k;
        } else {
            $wPt = $this->DefPageSize[1] * $this->k;
            $hPt = $this->DefPageSize[0] * $this->k;
        }

        $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';

        for ($n = 1; $n <= $nb; $n++) {
            // Page
            $this->_newobj();
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');

            if (isset($this->PageSizes[$n])) {
                $this->_out(sprintf(
                    '/MediaBox [0 0 %.2F %.2F]',
                    $this->PageSizes[$n][0],
                    $this->PageSizes[$n][1]
                ));
            }

            $this->_out('/Resources 2 0 R');

            if (isset($this->PageLinks[$n])) {
                // Links
                $annots = '/Annots [';

                foreach ($this->PageLinks[$n] as $pl) {
                    $rect = sprintf(
                        '%.2F %.2F %.2F %.2F',
                        $pl[0],
                        $pl[1],
                        $pl[0] + $pl[2],
                        $pl[1] - $pl[3]
                    );
                    $annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';

                    if (is_string($pl[4])) {
                        $annots .= '/A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>>>>';
                    } else {
                        $l = $this->links[$pl[4]];
                        $h = isset($this->PageSizes[$l[0]]) ? $this->PageSizes[$l[0]][1] : $hPt;
                        $annots .= sprintf(
                            '/Dest [%d 0 R /XYZ 0 %.2F null]>>',
                            1 + 2 * $l[0],
                            $h - $l[1] * $this->k
                        );
                    }
                }

                $this->_out($annots . ']');
            }

            if ($this->PDFVersion > '1.3') {
                $this->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }

            $this->_out('/Contents ' . ($this->n + 1) . ' 0 R>>');
            $this->_out('endobj');

            // Page content
            $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
            $this->_newobj();
            $this->_out('<<' . $filter . '/Length ' . strlen($p) . '>>');
            $this->_putstream($p);
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
        $nf = $this->n;

        foreach ($this->diffs as $diff) {
            // Encodings
            $this->_newobj();
            $this->_out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $diff . ']>>');
            $this->_out('endobj');
        }

        foreach ($this->FontFiles as $file => $info) {
            // Font file embedding
            $this->_newobj();
            $this->FontFiles[$file]['n'] = $this->n;
            $font = file_get_contents($this->fontpath . $file, true);

            if (!$font) {
                $this->Error('Font file not found: ' . $file);
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
            $this->_putstream($font);
            $this->_out('endobj');
        }
        foreach ($this->fonts as $k => $font) {
            // Font objects
            $this->fonts[$k]['n'] = $this->n + 1;
            $type = $font['type'];
            $name = $font['name'];

            if ($type == 'Core') {
                // Core font
                $this->_newobj();
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
                $this->_newobj();
                $this->_out('<</Type /Font');
                $this->_out('/BaseFont /' . $name);
                $this->_out('/Subtype /' . $type);
                $this->_out('/FirstChar 32 /LastChar 255');
                $this->_out('/Widths ' . ($this->n + 1) . ' 0 R');
                $this->_out('/FontDescriptor ' . ($this->n + 2) . ' 0 R');

                if (isset($font['diffn'])) {
                    $this->_out('/Encoding ' . ($nf + $font['diffn']) . ' 0 R');
                } else {
                    $this->_out('/Encoding /WinAnsiEncoding');
                }

                $this->_out('>>');
                $this->_out('endobj');
                // Widths
                $this->_newobj();
                $cw = &$font['cw'];
                $s = '[';

                for ($i = 32; $i <= 255; $i++) {
                    $s .= $cw[chr($i)] . ' ';
                }

                $this->_out($s . ']');
                $this->_out('endobj');
                // Descriptor
                $this->_newobj();
                $s = '<</Type /FontDescriptor /FontName /' . $name;

                foreach ($font['desc'] as $k => $v) {
                    $s .= ' /' . $k . ' ' . $v;
                }

                if (!empty($font['file'])) {
                    $s .= ' /FontFile' . ($type == 'Type1' ? '' : '2') . ' ' . $this->FontFiles[$font['file']]['n'] . ' 0 R';
                }

                $this->_out($s . '>>');
                $this->_out('endobj');
            } else {
                // Allow for additional types
                $mtd = '_put' . strtolower($type);
                if (!method_exists($this, $mtd)) {
                    $this->Error('Unsupported font type: ' . $type);
                }

                $this->$mtd($font);
            }
        }
    }

    protected function _putimages() {
        foreach (array_keys($this->images) as $file) {
            $this->_putimage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    protected function _putimage(&$info) {
        $this->_newobj();
        $info['n'] = $this->n;
        $this->_out('<</Type /XObject');
        $this->_out('/Subtype /Image');
        $this->_out('/Width ' . $info['w']);
        $this->_out('/Height ' . $info['h']);

        if ($info['cs'] == 'Indexed') {
            $this->_out('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
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
            $this->_out('/SMask ' . ($this->n + 1) . ' 0 R');
        }

        $this->_out('/Length ' . strlen($info['data']) . '>>');
        $this->_putstream($info['data']);
        $this->_out('endobj');

        // Soft mask
        if (isset($info['smask'])) {
            $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $info['w'];
            $smask = array('w' => $info['w'], 'h' => $info['h'], 'cs' => 'DeviceGray', 'bpc' => 8, 'f' => $info['f'], 'dp' => $dp, 'data' => $info['smask']);
            $this->_putimage($smask);
        }

        // Palette
        if ($info['cs'] == 'Indexed') {
            $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
            $pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
            $this->_newobj();
            $this->_out('<<' . $filter . '/Length ' . strlen($pal) . '>>');
            $this->_putstream($pal);
            $this->_out('endobj');
        }
    }

    protected function _putxobjectdict() {
        foreach ($this->images as $image) {
            $this->_out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
        }
    }

    protected function _putresourcedict() {
        $this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_out('/Font <<');

        foreach ($this->fonts as $font) {
            $this->_out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }

        $this->_out('>>');
        $this->_out('/XObject <<');
        $this->_putxobjectdict();
        $this->_out('>>');
    }

    protected function _putresources() {
        $this->_putfonts();
        $this->_putimages();
        // Resource dictionary
        $this->offsets[2] = strlen($this->buffer);
        $this->_out('2 0 obj');
        $this->_out('<<');
        $this->_putresourcedict();
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putinfo() {
        $this->_out('/Producer ' . $this->_textstring('FPDF ' . self::VERSION));

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

    protected function _putcatalog() {
        $this->_out('/Type /Catalog');
        $this->_out('/Pages 1 0 R');

        if ($this->ZoomMode == 'fullpage') {
            $this->_out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->ZoomMode == 'fullwidth') {
            $this->_out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->ZoomMode == 'real') {
            $this->_out('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->ZoomMode)) {
            $this->_out('/OpenAction [3 0 R /XYZ null null ' . sprintf('%.2F', $this->ZoomMode / 100) . ']');
        }

        if ($this->LayoutMode == 'single') {
            $this->_out('/PageLayout /SinglePage');
        } elseif ($this->LayoutMode == 'continuous') {
            $this->_out('/PageLayout /OneColumn');
        } elseif ($this->LayoutMode == 'two') {
            $this->_out('/PageLayout /TwoColumnLeft');
        }
    }

    protected function _putheader() {
        $this->_out('%PDF-' . $this->PDFVersion);
    }

    protected function _puttrailer() {
        $this->_out('/Size ' . ($this->n + 1));
        $this->_out('/Root ' . $this->n . ' 0 R');
        $this->_out('/Info ' . ($this->n - 1) . ' 0 R');
    }

    protected function _enddoc() {
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();
        // Info
        $this->_newobj();
        $this->_out('<<');
        $this->_putinfo();
        $this->_out('>>');
        $this->_out('endobj');
        // Catalog
        $this->_newobj();
        $this->_out('<<');
        $this->_putcatalog();
        $this->_out('>>');
        $this->_out('endobj');
        // Cross-ref
        $o = strlen($this->buffer);
        $this->_out('xref');
        $this->_out('0 ' . ($this->n + 1));
        $this->_out('0000000000 65535 f ');
        for ($i = 1; $i <= $this->n; $i++) {
            $this->_out(sprintf('%010d 00000 n ', $this->offsets[$i]));
        }

        // Trailer
        $this->_out('trailer');
        $this->_out('<<');
        $this->_puttrailer();
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
