<?php

namespace WHMCS\Version;

class ExpressionVersion extends SemanticVersion implements MatchInterface
{
    use AnythingExpressionTrait;
    protected $exactly = NULL;
    protected $ofVersion = NULL;
    protected $anyOfMajor = NULL;
    protected $anyOfMinor = NULL;

    protected function getSemVerPattern()
    {
        $pattern = "^(0|[1-9]\\d*)(\\.0|\\.[1-9]\\d*|\\.\\*)?(\\.0|\\.[1-9]\\d*|\\.\\*)?(?:-(0|[1-9]\\d*|\\d*[a-zA-Z-][a-zA-Z0-9-]*)(?:\\.((?:(?:0|[1-9]\\d*|[a-zA-Z-][a-zA-Z]*)\\.?)*).*)?)?\$";
        return $pattern;
    }

    public function isValid($version)
    {
        if (is_null($version)) {
            return false;
        }
        return parent::isValid(ltrim($version, "#~^"));
    }

    public function parse($version)
    {
        switch ($version[0]) {
            case "#":
                $version = substr($version, 1);
                $this->setExactly($version);
                break;
            case "^":
                $version = substr($version, 1);
                $this->setAnyOfMajor($version);
                break;
            case "~":
                $version = substr($version, 1);
                if (substr_count($version, ".") <= 1) {
                    $this->setAnyOfMajor($version);
                } else {
                    $this->setAnyOfMinor($version);
                }
                break;
            default:
                $pattern = $this->getSemVerPattern();
                $versionParts = $this->separateBuildTag($version);
                $matches = [];
                preg_match("/" . $pattern . "/", $versionParts[0], $matches);
                while (count($matches) < 4) {
                    $matches = [];
                    $versionParts[0] .= ".0";
                    preg_match("/" . $pattern . "/", $versionParts[0], $matches);
                }
                if (strpos($matches[2], "*") !== false) {
                    $this->setAnyOfMajor($version);
                    $version = str_replace("*", "0", $versionParts[0]);
                } else {
                    if (strpos($matches[3], "*") !== false) {
                        $this->setAnyOfMinor($version);
                        $version = str_replace("*", "0", $versionParts[0]);
                    } else {
                        if (preg_match("/\\d+/", $version)) {
                            $version = $version . ".0.0";
                            $this->setAnyOfMajor($version);
                        }
                        $version = $versionParts[0];
                    }
                }
                if (isset($versionParts[1])) {
                    $version .= "+" . $versionParts[1];
                }
                parent::parse($version);
        }
    }

    public function getExactly()
    {
        return $this->exactly;
    }

    public function setExactly($value)
    {
        $this->exactly = $value;
        if ($value === static::VALUE_ANYTHING) {
            $this->matchesAnything = true;
        }
        return $this;
    }

    public function getOfVersion()
    {
        return $this->ofVersion;
    }

    public function setOfVersion($value)
    {
        $this->ofVersion = $value;
        if ($value === self::VALUE_ANYTHING) {
            $this->matchesAnything = true;
        }
        return $this;
    }

    public function getAnyOfMajor()
    {
        return $this->anyOfMajor;
    }

    public function setAnyOfMajor($value)
    {
        $this->anyOfMajor = $value;
        if ($value === static::VALUE_ANYTHING) {
            $this->matchesAnything = true;
        }
        return $this;
    }

    public function getAnyOfMinor()
    {
        return $this->anyOfMinor;
    }

    public function setAnyOfMinor($value)
    {
        $this->anyOfMinor = $value;
        if ($value === static::VALUE_ANYTHING) {
            $this->matchesAnything = true;
        }
        return $this;
    }

    public function matches($value)
    {
        if ($this->matchesAnything()) {
            return true;
        }
        if (is_null($value) || !(is_numeric($value) || is_string($value))) {
            return false;
        }
        $value = (string) $value;
        if (!is_null($this->getExactly())) {
            return $this->exactly === $value || $this->exactly === static::VALUE_ANYTHING;
        }
        $compareVersion = new ExpressionVersion($value);
        if (!is_null($this->getAnyOfMajor())) {
            if (!self::compare($compareVersion, $this, "<")) {
                $next = (int) $this->getMajor() + 1 . ".0.0";
                $next = new SemanticVersion($next);
                if (self::compare($compareVersion, $next, "<")) {
                    return true;
                }
            }
        } else {
            if (!is_null($this->getAnyOfMinor())) {
                if (!self::compare($compareVersion, $this, "<")) {
                    $next = $this->getMajor() . "." . ((int) $this->getMinor() + 1) . ".0";
                    $next = new SemanticVersion($next);
                    if (self::compare($compareVersion, $next, "<")) {
                        return true;
                    }
                }
            } else {
                if ($this->getCasual() === $value) {
                    return true;
                }
            }
        }
        return false;
    }
}
