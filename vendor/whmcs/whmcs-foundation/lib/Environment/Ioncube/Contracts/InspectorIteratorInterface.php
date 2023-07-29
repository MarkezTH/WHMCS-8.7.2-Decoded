<?php

namespace WHMCS\Environment\Ioncube\Contracts;

interface InspectorIteratorInterface extends \IteratorAggregate, \ArrayAccess, \Serializable, \Countable
{
    public function getArrayCopy();
}
