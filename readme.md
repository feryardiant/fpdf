# FPDF [![Build Status](https://travis-ci.org/feryardiant/fpdf.svg?branch=master)](https://travis-ci.org/feryardiant/fpdf)

Unofficial FPDF library with PSR-4 compliant, clean and readable code

This is version 1.7.2 with some changes:

* [x] The library is namespaced in `Fpdf`
* [x] directory structure follow the PSR-4 standard with `src/Fpdf` as root
* [x] Class constructor is renamed `__construct` instead of `FPDF`
* [x] `FPDF_VERSION` is now `Fpdf\Fpdf::VERSION`
* [x] on error a `\RuntimeException` is thrown instead on lib dramatically `die()`ing
* [x] Refactor all method in `camelCase` (**It's breaks backward compatibility**).
* [ ] Rebuild documentations and tutorials.
* [ ] Complete testunit.

## Installing with composer

The package exists in the packagist repository as `feryardiant/fpdf`.

## Credits

**Original Author:** Olivier PLATHEY

**FPDF Version:** 1.7


