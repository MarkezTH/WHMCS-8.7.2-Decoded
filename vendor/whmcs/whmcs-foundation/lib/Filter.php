<?php

namespace WHMCS;

class Filter
{
    private $name = "";
    private $data = [];
    private $allowedvars = [];

    public function __construct($filterName = "")
    {
        if (!$filterName) {
            $filterName = $this->getFilename();
        }
        $this->name = $filterName;
        $this->data = Cookie::get("FD", true);
    }

    private function getFilename()
    {
        return \App::getCurrentFilename();
    }

    public function isActive()
    {
        if (!array_key_exists($this->name, $this->data)) {
            return false;
        }
        foreach ($this->data[$this->name] as $v) {
            if ($v) {
                return true;
            }
        }
        return false;
    }

    public function setAllowedVars($allowedvars)
    {
        $this->allowedvars = $allowedvars;
        return $this;
    }

    public function addAllowedVar($var)
    {
        $this->allowedvars[] = $var;
        return true;
    }

    public function getFromReq($var)
    {
        return \App::getFromRequest($var);
    }

    public function getFromSession($var)
    {
        return isset($this->data[$this->name][$var]) ? $this->data[$this->name][$var] : "";
    }

    public function get($var)
    {
        $this->addAllowedVar($var);
        if ($this->getFromReq("filter")) {
            return $this->getFromSession($var);
        }
        return $this->getFromReq($var);
    }

    public function store()
    {
        if ($this->getFromReq("filter")) {
            return $this;
        }
        $arr = [];
        foreach ($this->allowedvars as $op) {
            $arr[$op] = $this->getFromReq($op);
        }
        $this->data[$this->name] = $arr;
        Cookie::set("FD", $this->data);
        return $this;
    }

    public function redir($vars = "")
    {
        if (is_array($this->data[$this->name])) {
            if ($vars) {
                $vars .= "&filter=1";
            } else {
                $vars = "filter=1";
            }
        }
        redir($vars);
    }

    public function getFilterCriteria()
    {
        $data = [];
        foreach ($this->allowedvars as $key) {
            $data[$key] = $this->get($key);
        }
        return $data;
    }
}
