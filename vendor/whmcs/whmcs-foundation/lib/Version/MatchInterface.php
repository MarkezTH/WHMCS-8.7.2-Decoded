<?php

namespace WHMCS\Version;

interface MatchInterface
{
    const VALUE_ANYTHING = "*";

    public function matches($value);
}
