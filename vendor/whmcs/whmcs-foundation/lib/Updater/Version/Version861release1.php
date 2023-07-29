<?php

namespace WHMCS\Updater\Version;

class Version861release1 extends IncrementalVersion
{
    public function __construct(\WHMCS\Version\SemanticVersion $version)
    {
        parent::__construct($version);
        $this->filesToRemove[] = ROOTDIR . "/modules/mail/SmtpMail/SmtpOauth.php";
    }
}
