<?php

namespace WHMCS\Service\Ssl;

class ValidationMethodFileauth extends ValidationMethod
{
    public $name = NULL;
    public $path = NULL;
    public $contents = NULL;

    public function methodNameConstant()
    {
        return \WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE;
    }

    public function friendlyName()
    {
        return "HTTP File";
    }

    public function translationKey($language)
    {
        if ($language instanceof \WHMCS\Language\AdminLanguage) {
            return "wizard.ssl.fileMethod";
        }
        return "ssl.fileMethod";
    }

    public function populate($values)
    {
        return $this->populateFromClassProperties($values);
    }

    public function defaults()
    {
        return $this;
    }

    public function filePath()
    {
        return sprintf("%s/%s", $this->path, $this->name);
    }

    public function toArray()
    {
        return ["fileAuthPath" => $this->path, "fileAuthFilename" => $this->name, "fileAuthContents" => $this->contents];
    }
}
