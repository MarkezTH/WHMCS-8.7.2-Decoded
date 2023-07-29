<?php

namespace WHMCS\Apps\Meta\Schema;

class AbstractVersion
{
    public $metaData = [];

    public function __construct($metaData)
    {
        $this->metaData = $metaData;
    }

    protected function meta($key)
    {
        $parts = explode(".", $key);
        $response = $this->metaData;
        foreach ($parts as $part) {
            if (isset($response[$part])) {
                $response = $response[$part];
            } else {
                return NULL;
            }
        }
        return $response;
    }
}
