<?php

namespace WHMCS\Results;

class ResultsList extends \ArrayObject
{
    public function toArray()
    {
        $result = [];
        foreach ($this->getArrayCopy() as $key => $data) {
            $result[$key] = $data->toArray();
        }
        return $result;
    }
}
