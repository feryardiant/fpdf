# Welcome to Unofficial FPDF Wiki

[![Build Status](https://travis-ci.org/feryardiant/fpdf.svg?branch=master)](https://travis-ci.org/feryardiant/fpdf)

## Introduction

Unofficial FPDF library with PSR-0 compliant, clean and readable code

## Credit

* **Original Author:** [Olivier PLATHEY](http://fpdf.org/)
* **FPDF Version:** 1.7

## License

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software to use, copy, modify, distribute, sublicense, and/or sell
copies of the software, and to permit persons to whom the software is furnished
to do so.

**THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED**.

## Installation

The package exists in the packagist repository as `feryardiant/fpdf`.

## Usage

```php
require 'vendor/autoload.php';

$fpdf = new Fpdf\Fpdf();
```

## Classes

### [Fpdf\AbstractFpdf](abstractfpdf)

* `[Fpdf\AbstractFpdf::_validateFontpath](abstractfpdf-_validatefontpath)`
* `[Fpdf\AbstractFpdf::error](abstractfpdf-error)`

### [Fpdf\Fpdf](fpdf)

* `[Fpdf\Fpdf::__construct](fpdf-constructor)`
* `[Fpdf\Fpdf::acceptPageBreak](fpdf-acceptpagebreak)`
* `[Fpdf\Fpdf::addFont](fpdf-addfont)`
* `[Fpdf\Fpdf::addLink](fpdf-addlink)`
* `[Fpdf\Fpdf::addPage](fpdf-addpage)`
* `[Fpdf\Fpdf::aliasNbPages](fpdf-aliasnbpages)`
* `[Fpdf\Fpdf::cell](fpdf-cell)`
* `[Fpdf\Fpdf::close](fpdf-close)`
* `[Fpdf\Fpdf::footer](fpdf-footer)`
* `[Fpdf\Fpdf::getStringWidth](fpdf-getstringwidth)`
* `[Fpdf\Fpdf::getX](fpdf-getx)`
* `[Fpdf\Fpdf::getY](fpdf-gety)`
* `[Fpdf\Fpdf::header](fpdf-header)`
* `[Fpdf\Fpdf::image](fpdf-image)`
* `[Fpdf\Fpdf::line](fpdf-line)`
* `[Fpdf\Fpdf::link](fpdf-link)`
* `[Fpdf\Fpdf::ln](fpdf-ln)`
* `[Fpdf\Fpdf::multiCell](fpdf-multicell)`
* `[Fpdf\Fpdf::output](fpdf-output)`
* `[Fpdf\Fpdf::pageNo](fpdf-pageno)`
* `[Fpdf\Fpdf::rect](fpdf-rect)`
* `[Fpdf\Fpdf::setAuthor](fpdf-setauthor)`
* `[Fpdf\Fpdf::setAutoPageBreak](fpdf-setautopagebreak)`
* `[Fpdf\Fpdf::setCompression](fpdf-setcompression)`
* `[Fpdf\Fpdf::setCreator](fpdf-setcreator)`
* `[Fpdf\Fpdf::setDisplayMode](fpdf-setdisplaymode)`
* `[Fpdf\Fpdf::setDrawColor](fpdf-setdrawcolor)`
* `[Fpdf\Fpdf::setFillColor](fpdf-setfillcolor)`
* `[Fpdf\Fpdf::setFont](fpdf-setfont)`
* `[Fpdf\Fpdf::setFontSize](fpdf-setfontsize)`
* `[Fpdf\Fpdf::setKeywords](fpdf-setkeywords)`
* `[Fpdf\Fpdf::setLeftMargin](fpdf-setleftmargin)`
* `[Fpdf\Fpdf::setLineWidth](fpdf-setlinewidth)`
* `[Fpdf\Fpdf::setLink](fpdf-setlink)`
* `[Fpdf\Fpdf::setMargins](fpdf-setmargins)`
* `[Fpdf\Fpdf::setRightMargin](fpdf-setrightmargin)`
* `[Fpdf\Fpdf::setSubject](fpdf-setsubject)`
* `[Fpdf\Fpdf::setTextColor](fpdf-settextcolor)`
* `[Fpdf\Fpdf::setTitle](fpdf-settitle)`
* `[Fpdf\Fpdf::setTopMargin](fpdf-settopmargin)`
* `[Fpdf\Fpdf::setX](fpdf-setx)`
* `[Fpdf\Fpdf::setXY](fpdf-setxy)`
* `[Fpdf\Fpdf::setY](fpdf-sety)`
* `[Fpdf\Fpdf::text](fpdf-text)`
* `[Fpdf\Fpdf::write](fpdf-write)`

### [Fpdf\MakeFont](makefont)

* `[Fpdf\MakeFont::__construct](makefont-constructor)`
* `[Fpdf\MakeFont::make](makefont-make)`
* `[Fpdf\MakeFont::toFile](makefont-tofile)`
* `[Fpdf\MakeFont::loadMap](makefont-loadmap)`
* `[Fpdf\MakeFont::getTrueTypeInfo](makefont-gettruetypeinfo)`
* `[Fpdf\MakeFont::getType1Info](makefont-gettype1info)`
* `[Fpdf\MakeFont::descriptor](makefont-descriptor)`
* `[Fpdf\MakeFont::makeWidthArray](makefont-makewidtharray)`
* `[Fpdf\MakeFont::encoding](makefont-encoding)`
* `[Fpdf\MakeFont::saveToFile](makefont-savetofile)`
* `[Fpdf\MakeFont::makeDefinitionFile](makefont-makedefinitionfile)`

### [Fpdf\TTFParser](ttfparser)

* `[Fpdf\TTFParser::__construct](ttfparser-constructor)`
* `[Fpdf\TTFParser::head](ttfparser-head)`
* `[Fpdf\TTFParser::hhea](ttfparser-hhea)`
* `[Fpdf\TTFParser::maxp](ttfparser-maxp)`
* `[Fpdf\TTFParser::hmtx](ttfparser-hmtx)`
* `[Fpdf\TTFParser::cmap](ttfparser-cmap)`
* `[Fpdf\TTFParser::name](ttfparser-name)`
* `[Fpdf\TTFParser::os2](ttfparser-os2)`
* `[Fpdf\TTFParser::post](ttfparser-post)`
* `[Fpdf\TTFParser::seek](ttfparser-seek)`
* `[Fpdf\TTFParser::skip](ttfparser-skip)`
* `[Fpdf\TTFParser::read](ttfparser-read)`
* `[Fpdf\TTFParser::readUShort](ttfparser-readushort)`
* `[Fpdf\TTFParser::readShort](ttfparser-readshort)`
* `[Fpdf\TTFParser::readULong](ttfparser-readulong)`
