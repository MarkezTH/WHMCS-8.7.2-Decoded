<?php

namespace WHMCS\Environment\Ioncube\Contracts;

interface InspectorFilterIteratorInterface
{
    public function getPhpVersion();

    public function setPhpVersion($phpVersion);

    public function getFilterIterator(\Iterator $iterator);

    public function accept(InspectedFileInterface $current);
}
