<?php

namespace WHMCS\Environment\Ioncube\Contracts;

interface InspectedFileInterface extends EncodedFileInterface
{
    public function getAnalyzer();

    public function getBundledPhpVersions();

    public function getLoadedInPhp();
}
