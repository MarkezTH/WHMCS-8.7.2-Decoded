<?php

namespace WHMCS\Environment\Ioncube\Inspector\Filter;

abstract class AbstractAbsolutelyNonDecodableIterator extends AbstractCacheIterator
{
    public function accept(\WHMCS\Environment\Ioncube\Contracts\InspectedFileInterface $current)
    {
        if ($this->getAssessment($current) === \WHMCS\Environment\Ioncube\Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_NO) {
            return true;
        }
        return false;
    }
    public abstract function getAssessment(\WHMCS\Environment\Ioncube\Contracts\InspectedFileInterface $file);
}
