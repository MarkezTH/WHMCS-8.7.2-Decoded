<?php
/**
 * @see       https://github.com/laminas/laminas-validator for the canonical source repository
 * @copyright https://github.com/laminas/laminas-validator/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-validator/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Validator\Barcode;

class Planet extends AbstractAdapter
{
    /**
     * Constructor for this barcode adapter
     */
    public function __construct()
    {
        $this->setLength([12, 14]);
        $this->setCharacters('0123456789');
        $this->setChecksum('postnet');
    }
}
