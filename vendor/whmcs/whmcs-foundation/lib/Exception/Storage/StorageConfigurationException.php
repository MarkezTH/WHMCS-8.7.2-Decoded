<?php

namespace WHMCS\Exception\Storage;

class StorageConfigurationException extends StorageException
{
    private $fields = [];

    public function __construct($fields)
    {
        parent::__construct(join(" ", array_values($fields)));
        $this->fields = $fields;
    }

    public function getFields()
    {
        return $this->fields;
    }
}
