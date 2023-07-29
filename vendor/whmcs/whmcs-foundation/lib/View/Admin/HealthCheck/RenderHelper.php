<?php

namespace WHMCS\View\Admin\HealthCheck;

class RenderHelper
{
    public function section($title)
    {
        return sprintf("<strong>%s</strong><br/>", $title);
    }

    public function unordered($items, $renderer)
    {
        $out = "<ul>";
        foreach ($items as $item) {
            $out .= $this->li($renderer($item)) . "\n";
        }
        return $out . "</ul>";
    }

    public function li($item)
    {
        return sprintf("<li>%s</li>", $item);
    }
}
