<?php

namespace WHMCS\Module\Server;

class CustomActionCollection extends \Illuminate\Support\Collection
{
    public function add($self, $item)
    {
        if (!$item instanceof CustomAction) {
            throw new \InvalidArgumentException("The provided item must be an instance of the CustomAction class.");
        }
        parent::add($item);
        return $this;
    }
}
