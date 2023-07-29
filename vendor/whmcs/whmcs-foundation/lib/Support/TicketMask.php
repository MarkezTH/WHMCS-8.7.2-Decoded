<?php

namespace WHMCS\Support;

class TicketMask
{
    protected $ticketId = 0;
    protected $mask = "";
    const DEFAULT_TICKET_MASK = "%A%A%A-%n%n%n%n%n%n";

    public function __construct($mask = NULL)
    {
        if (!is_null($mask) && self::isValidMask($mask)) {
            $this->mask = $mask;
        } else {
            $this->mask = $this->getDefault();
        }
        return $this;
    }

    public function getDefault()
    {
        $mask = trim(\WHMCS\Config\Setting::getValue("TicketMask"));
        if (!self::isValidMask($mask)) {
            $mask = self::DEFAULT_TICKET_MASK;
        }
        return $mask;
    }

    public static function isValidMask($mask)
    {
        $validMask = true;
        if (strlen($mask) == 0 || $mask == "") {
            $validMask = false;
        }
        return $validMask;
    }

    public function id($ticketId)
    {
        $this->ticketId = $ticketId;
        return $this;
    }

    public function make()
    {
        return $this->generateMask();
    }

    public function unique()
    {
        return $this->generateUniqueMask();
    }

    protected function ticketIdExists($masksToCheck)
    {
        return (array) Ticket::whereIn("tid", $masksToCheck)->pluck("tid")->all();
    }

    protected function generateMask()
    {
        $maskString = "";
        $mask = $this->mask;
        $maskLength = strlen($mask);
        for ($i = 0; $i < $maskLength; $i++) {
            $maskValue = $mask[$i];
            if ($maskValue == "%") {
                $i++;
                $maskValue .= $mask[$i];
                switch ($maskValue) {
                    case "%A":
                        $maskString .= (new \WHMCS\Utility\Random())->string(0, 1, 0, 0);
                        break;
                    case "%a":
                        $maskString .= (new \WHMCS\Utility\Random())->string(1, 0, 0, 0);
                        break;
                    case "%n":
                        $maskString .= (new \WHMCS\Utility\Random())->string(0, 0, 1, 0);
                        break;
                    case "%y":
                        $maskString .= date("Y");
                        break;
                    case "%m":
                        $maskString .= date("m");
                        break;
                    case "%d":
                        $maskString .= date("d");
                        break;
                    case "%i":
                        $maskString .= $this->ticketId ?: "";
                        break;
                }
            } else {
                $maskString .= $maskValue;
            }
        }
        return $maskString;
    }

    protected function generateUniqueMask()
    {
        $mask = $this->getUniqueMasksFromSet($this->generateMaskSet(5));
        $mask = array_pop($mask);
        if (empty($mask)) {
            $i = 0;
            while ($i < 100) {
                $mask = $this->getUniqueMasksFromSet($this->generateMaskSet(5));
                $mask = array_pop($mask);
                if (empty($mask)) {
                    if ($i === 99) {
                        throw new \WHMCS\Exception\Support\TicketMaskIterationException("Maximum iteration reached generating ticket mask");
                    }
                    $i++;
                }
            }
        }
        return $mask;
    }

    public function generateMaskSet($numberToGenerate)
    {
        $maskArray = [];
        for ($i = 0; $i < $numberToGenerate; $i++) {
            $maskArray[] = $this->generateMask();
        }
        return $maskArray;
    }

    public function getUniqueMasksFromSet($maskArray)
    {
        return array_diff($maskArray, $this->ticketIdExists($maskArray));
    }

    public function gatherMaskPossibilities()
    {
        $ticketMask = $this->mask;
        $possibilities = 1;
        $maskLength = strlen($ticketMask);
        for ($i = 0; $i < $maskLength; $i++) {
            $maskValue = $ticketMask[$i];
            if ($maskValue == "%") {
                $i++;
                $maskValue .= $ticketMask[$i];
                switch ($maskValue) {
                    case "%n":
                        $possibilities = $possibilities * 10;
                        break;
                    case "%a":
                    case "%A":
                        $possibilities = $possibilities * 26;
                        break;
                    case "%m":
                        $possibilities = $possibilities * 12;
                        break;
                    case "%d":
                        $possibilities = $possibilities * 30;
                        break;
                    case "%i":
                        $possibilities = $possibilities + \WHMCS\Environment\DbEngine::MYSQL_INT_MAX_SIGNED;
                        break;
                }
            }
        }
        if (PHP_INT_MAX < $possibilities) {
            return PHP_INT_MAX;
        }
        return $possibilities;
    }
}
