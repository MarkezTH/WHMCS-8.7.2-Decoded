<?php

namespace WHMCS\Environment\Ioncube\Inspector\Filter;

class EncoderFingerprintFavorV9 extends AbstractAbsolutelyNonDecodableIterator
{
    public function getAssessment(\WHMCS\Environment\Ioncube\Contracts\InspectedFileInterface $file)
    {
        return $file->getAnalyzer()->versionCompatibilityAssessment($this->getPhpVersion());
    }
}
