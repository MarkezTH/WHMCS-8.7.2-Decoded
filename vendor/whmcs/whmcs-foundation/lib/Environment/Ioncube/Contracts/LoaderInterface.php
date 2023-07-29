<?php

namespace WHMCS\Environment\Ioncube\Contracts;

interface LoaderInterface
{
    public static function getVersion();

    public function compatAssessment($phpVersion, InspectedFileInterface $file);

    public function supportsBundledEncoding();
}
