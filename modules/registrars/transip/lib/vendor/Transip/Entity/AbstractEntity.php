<?php

namespace Transip\Api\Library\Entity;

class AbstractEntity implements \JsonSerializable
{
    public function __construct($valueArray = [])
    {
        foreach ($valueArray as $field => $value) {
            if (property_exists($this, $field)) {
                $this->{$field} = $value;
            }
        }
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
