<?php

namespace WHMCS;

abstract class TableModel extends TableQuery
{
    protected $pageObj = NULL;
    protected $queryObj = NULL;

    public function __construct(Pagination $obj = NULL)
    {
        $this->pageObj = $obj;
        $numrecords = Config\Setting::getValue("NumRecordstoDisplay");
        $this->setRecordLimit($numrecords);
        return $this;
    }
    public abstract function _execute($implementationData);

    public function setPageObj(Pagination $pageObj)
    {
        $this->pageObj = $pageObj;
    }

    public function getPageObj()
    {
        return $this->pageObj;
    }

    public function execute($criteria = [])
    {
        $results = $this->_execute($criteria);
        $this->getPageObj()->setData($results);
        return $this;
    }
}
