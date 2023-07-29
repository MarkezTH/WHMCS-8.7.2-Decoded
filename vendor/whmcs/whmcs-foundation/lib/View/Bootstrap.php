<?php

namespace WHMCS\View;

class Bootstrap
{
    public function renderKeyValuePairsInColumns($colWidth, $data)
    {
        $output = "<div class=\"row\">";
        foreach ($data as $values) {
            $output .= "<div class=\"col-sm-" . $colWidth . "\">";
            foreach ($values as $key => $value) {
                if (empty($value)) {
                    $value = "-";
                }
                $output .= $key . ": " . $value . "<br>";
            }
            $output .= "</div>";
        }
        $output .= "</div>";
        return $output;
    }
}
