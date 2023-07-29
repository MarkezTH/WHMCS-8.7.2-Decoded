<?php

namespace WHMCS\Api\NG\Versions\V2;

interface PagedResponseInterface
{
    public function hasPageInformation();

    public function getPageNumber();

    public function setPageNumber($pageNumber);

    public function getPageCount();

    public function setPageCount($pageCount);
}
