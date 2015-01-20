<?php

class PdfExtended extends \Fpdf\Fpdf {
    // Current column
    var $col = 0;
    // Ordinate of column start
    var $y0;

    function header() {
        $this->setFont('Times', 'B', 15);
        $w = $this->getStringWidth($this->title) + 6;
        $this->setX((210 - $w) / 2);
        $this->setDrawColor(0, 80, 180);
        $this->setFillColor(230, 230, 0);
        $this->setTextColor(220, 50, 50);
        $this->setLineWidth(1);
        $this->cell($w, 9, $this->title, 1, 1, 'C', true);
        $this->ln(10);
        // Save ordinate
        $this->y0 = $this->getY();
    }

    function footer() {
        // Page footer
        $this->setY(-15);
        $this->setFont('Times', 'I', 8);
        $this->setTextColor(128);
        $this->cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function setCol($col) {
        // Set position at a given column
        $this->col = $col;
        $x = 10 + $col * 65;
        $this->setLeftMargin($x);
        $this->setX($x);
    }

    function acceptPageBreak() {
        // Method accepting or not automatic page break
        if ($this->col < 2) {
            // Go to next column
            $this->setCol($this->col + 1);
            // Set ordinate to top
            $this->setY($this->y0);
            // Keep on page
            return false;
        } else {
            // Go back to first column
            $this->setCol(0);
            // Page break
            return true;
        }
    }

    function chapterTitle($num, $label) {
        // Title
        $this->setFont('Times', '', 12);
        $this->setFillColor(200, 220, 255);
        $this->cell(0, 6, "Chapter $num : $label", 0, 1, 'L', true);
        $this->ln(4);
        // Save ordinate
        $this->y0 = $this->getY();
    }

    function chapterBody($file) {
        // Read text file
        $txt = file_get_contents($file);
        // Font
        $this->setFont('Times', '', 12);
        // Output text in a 6 cm width column
        $this->MultiCell(60, 5, $txt);
        $this->ln();
        // Mention
        $this->setFont('', 'I');
        $this->cell(0, 5, '(end of excerpt)');
        // Go back to first column
        $this->setCol(0);
    }

    function printChapter($num, $title, $file) {
        // Add chapter
        $this->addPage();
        $this->chapterTitle($num, $title);
        $this->chapterBody($file);
    }
}
