<?php

namespace WHMCS\Updater\Version;

class Version871release1 extends IncrementalVersion
{
    protected $updateActions = [];

    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "servers", "licensing", "clientarea.tpl"]);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "servers", "licensing", "clientareaproductdetails.tpl"]);
        $this->filesToRemove[] = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "servers", "licensing", "clientareaproductdetails_portal.tpl"]);
    }
}
