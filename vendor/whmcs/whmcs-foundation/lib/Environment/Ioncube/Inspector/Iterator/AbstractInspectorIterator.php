<?php

namespace WHMCS\Environment\Ioncube\Inspector\Iterator;

class AbstractInspectorIterator extends \ArrayObject implements \WHMCS\Environment\Ioncube\Contracts\InspectorIteratorInterface
{
    public function merge(\WHMCS\Environment\Ioncube\Contracts\InspectorIteratorInterface $currentInspections)
    {
        $current = $currentInspections->getArrayCopy();
        $previous = $this->getArrayCopy();
        $new = array_diff_key($current, $previous);
        $stillPresent = array_intersect_key($previous, $current);
        $this->exchangeArray($new + $stillPresent);
        return $this;
    }

    public function factoryAnalyser($filePath = "")
    {
        return new \WHMCS\Environment\Ioncube\EncodedFile($filePath);
    }

    public function factoryLogFile($filePath)
    {
        return $this->factoryAnalyser($filePath)->getLoggable();
    }
}
