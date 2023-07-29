<?php

namespace WHMCS\Version;

trait AnythingExpressionTrait
{
    protected $matchesAnything = NULL;

    public function matchesAnything()
    {
        return (bool) $this->matchesAnything;
    }

    public function allowAnyMatch()
    {
        $this->matchesAnything = true;
    }

    public function preventAnyMatch()
    {
        $this->matchesAnything = false;
    }
}
