<?php

namespace WHMCS;

class Table
{
    private $fields = [];
    private $labelwidth = "20";
    const EMPTY_ROW = "<EMPTY_ROW>";

    public function __construct($width = "20")
    {
        $this->labelwidth = $width;
        return $this;
    }

    public function add($self, $name, $field = false, $fullwidth = 1, int $rowspan)
    {
        if ($field === static::EMPTY_ROW) {
            $name = "&nbsp;";
            $field = "&nbsp;";
        }
        if ($fullwidth) {
            $fullwidth = true;
        }
        $this->fields[] = ["name" => $name, "field" => $field, "fullwidth" => $fullwidth, "rowspan" => $rowspan];
        return $this;
    }

    public function output()
    {
        $code = "<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\"><tr>";
        $i = 0;
        $rowspanRow = false;
        foreach ($this->fields as $k => $v) {
            $colspan = "";
            if ($v["fullwidth"]) {
                $colspan = "3";
                if ($colspan && $i != 0) {
                    $code .= "</tr><tr>";
                    $i = 0;
                }
                $i++;
            }
            $rowspan = "";
            if (1 < $v["rowspan"]) {
                $rowspanRow = true;
                $rowspan = " rowspan=\"" . $v["rowspan"] . "\"";
            }
            $code .= "<td class=\"fieldlabel\"" . $rowspan . " width=\"" . $this->labelwidth . "%\">" . $v["name"] . "</td>" . "<td class=\"fieldarea\"" . ($colspan ? " colspan=\"" . $colspan . "\"" : " width=\"" . (50 - $this->labelwidth) . "%\"") . $rowspan . ">" . $v["field"] . "</td>";
            $i++;
            if ($i == 2) {
                $code .= "</tr><tr>";
                $i = 0;
                if ($rowspanRow) {
                    $i = 1;
                    $rowspanRow = false;
                }
            }
        }
        $code .= "</tr></table>";
        return $code;
    }
}
