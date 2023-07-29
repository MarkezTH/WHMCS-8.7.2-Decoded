<?php

namespace WHMCS\Module\MailSender;

trait DescriptionTrait
{
    protected $displayName = "";

    public function getName()
    {
        return basename(str_replace("\\", "/", get_class($this)));
    }

    public function getDisplayName()
    {
        return $this->displayName;
    }

    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
        return $this;
    }
}
