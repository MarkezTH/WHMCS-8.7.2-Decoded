<?php

namespace WHMCS\Environment\Ioncube\Inspector\Filter;

class AnyEncodingIterator extends AbstractCacheIterator
{
    public function accept(\WHMCS\Environment\Ioncube\Contracts\InspectedFileInterface $current)
    {
        if (in_array($current->getEncoderVersion(), [\WHMCS\Environment\Ioncube\Contracts\EncodedFileInterface::ENCODER_VERSION_NONE, \WHMCS\Environment\Ioncube\Contracts\EncodedFileInterface::ENCODER_VERSION_UNKNOWN])) {
            return false;
        }
        return true;
    }
}
