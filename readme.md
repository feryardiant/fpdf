# FPDF

[![Packagist License](https://img.shields.io/packagist/l/feryardiant/fpdf.svg?style=flat-square)](https://packagist.org/packages/feryardiant/fpdf)
[![Packagist Version](https://img.shields.io/packagist/v/feryardiant/fpdf.svg?style=flat-square)](https://packagist.org/packages/feryardiant/fpdf)
[![Travis branch](https://img.shields.io/travis/feryardiant/fpdf/master.svgstyle=flat-square)](https://travis-ci.org/feryardiant/fpdf)
[![Gittip donate](http://img.shields.io/gratipay/feryardiant.svg?style=flat-square)](https://www.gratipay.com/feryardiant/ "Buy me coffee via Gittip")
[![PayPal donate](https://img.shields.io/badge/paypal-donate-orange.svg?style=flat-square)](http://j.mp/1Qp9MUT "Buy me coffee via PayPal")

Unofficial FPDF library with PSR-4 compliant, clean and readable code

This is version 1.7.2 with some changes:

* [x] The library is namespaced in `Fpdf`
* [x] directory structure follow the PSR-4 standard with `src/Fpdf` as root
* [x] Class constructor is renamed `__construct` instead of `FPDF`
* [x] `FPDF_VERSION` is now `Fpdf\Fpdf::VERSION`
* [x] on error a `Fpdf\Exception` is thrown instead on lib dramatically `die()`ing
* [x] Refactor all method in `camelCase` (**It's breaks backward compatibility**).
* [ ] Rebuild documentations and tutorials.
* [ ] Complete testunit.

## Installing with composer

The package exists in the packagist repository as `feryardiant/fpdf`.

```bash
$ composer require feryardiant/fpdf --prefer-dist
```

## Testing

```bash
phpunit -c tests/phpunit.xml
```

## Credits

**Original Author:** Olivier PLATHEY

**FPDF Version:** 1.7


