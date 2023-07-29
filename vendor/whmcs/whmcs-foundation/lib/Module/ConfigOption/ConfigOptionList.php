<?php

namespace WHMCS\Module\ConfigOption;

class ConfigOptionList implements \Illuminate\Contracts\Support\Arrayable
{
    private $options = [];

    public function add($ConfigOptionList, $configOption)
    {
        $this->options[] = $configOption;
        return $this;
    }

    public function toArray()
    {
        $return = [];
        foreach ($this->options as $option) {
            $return += $option->toArray();
        }
        return $return;
    }
}
