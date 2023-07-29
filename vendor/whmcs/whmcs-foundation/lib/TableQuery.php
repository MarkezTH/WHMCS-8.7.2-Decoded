<?php

namespace WHMCS;

class TableQuery
{
    protected $recordOffset = 0;
    protected $recordLimit = 25;
    protected $data = [];

    public function getData()
    {
        return $this->data;
    }

    public function getOne()
    {
        return isset($this->data[0]) ? $this->data[0] : NULL;
    }

    public function setRecordLimit($limit)
    {
        $this->recordLimit = $limit;
        return $this;
    }

    public function getRecordLimit()
    {
        return $this->recordLimit;
    }

    public function getRecordOffset()
    {
        $page = $this->getPageObj()->getPage();
        $offset = ($page - 1) * $this->getRecordLimit();
        return $offset;
    }

    public function getQueryLimit()
    {
        return $this->getRecordOffset() . "," . $this->getRecordLimit();
    }

    public function setData($data = [])
    {
        if (!is_array($data) && !$data instanceof \Illuminate\Support\Collection) {
            throw new \InvalidArgumentException("Dataset must be an array");
        }
        $this->data = $data;
        return $this;
    }
}
