<?php

namespace WHMCS\Product;

trait CompoundNameTrait
{
    private function sluggify($value)
    {
        return str_replace(" ", "_", strtolower($value));
    }

    private function getCompoundName()
    {
        $value = (string) $this->getRawAttribute("optionname");
        $pipePos = strpos($value, "|");
        if ($pipePos !== false) {
            return explode("|", $value, 2);
        }
        return [$this->sluggify($value), $value];
    }

    private function setCompoundName($value)
    {
        if (count($value) < 2) {
            array_unshift($value, $this->sluggify($value[0]));
        }
        $this->setAttribute("optionname", implode("|", $value));
    }

    protected function getDisplayNameAttribute()
    {
        return (string) $this->getCompoundName()[1];
    }

    protected function setDisplayNameAttribute($value)
    {
        $compoundName = $this->getCompoundName();
        $compoundName[1] = $value;
        $this->setCompoundName($compoundName);
    }

    protected function getControlNameAttribute()
    {
        return (string) $this->getCompoundName()[0];
    }

    protected function setControlNameAttribute($value)
    {
        $compoundName = $this->getCompoundName();
        $compoundName[0] = $value;
        $this->setCompoundName($compoundName);
    }
}
