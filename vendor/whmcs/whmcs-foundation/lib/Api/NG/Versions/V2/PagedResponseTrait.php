<?php

namespace WHMCS\Api\NG\Versions\V2;

trait PagedResponseTrait
{
    private $pageNumber = NULL;
    private $pageCount = NULL;
    private $defaultPageSize = 50;

    public function paginateData($data, \WHMCS\Http\Message\ServerRequest $request, int $pageSize = NULL)
    {
        if (is_null($pageSize)) {
            $pageSize = $this->defaultPageSize;
        } else {
            if ($pageSize < 1) {
                $pageSize = 1;
            } else {
                if (50 < $pageSize) {
                    $pageSize = 50;
                }
            }
        }
        $pageCount = (int) ceil(count($data) / $pageSize);
        $pageNumber = $request->get("page", 1);
        if ($pageNumber < 1) {
            $pageNumber = 1;
        } else {
            if ($pageCount < $pageNumber) {
                $pageNumber = $pageCount;
            }
        }
        $this->setPageNumber($pageNumber);
        $this->setPageCount($pageCount);
        $itemsToSkip = ($pageNumber - 1) * $pageSize;
        if ($data instanceof \Illuminate\Support\Collection) {
            $dataPage = $data->skip($itemsToSkip)->take($pageSize)->values();
        } else {
            if (is_array($data)) {
                $dataPage = array_slice($data, $itemsToSkip, $pageSize);
            }
        }
        return $dataPage;
    }

    public function hasPageInformation()
    {
        return !is_null($this->pageNumber) && !is_null($this->pageCount);
    }

    public function getPageNumber()
    {
        if (is_null($this->pageNumber)) {
            throw new \WHMCS\Exception\Api\NG\ApiNgException("Page number must be set");
        }
        return $this->pageNumber;
    }

    public function setPageNumber($pageNumber)
    {
        $this->pageNumber = $pageNumber;
    }

    public function getPageCount()
    {
        if (is_null($this->pageCount)) {
            throw new \WHMCS\Exception\Api\NG\ApiNgException("You must set a page count.");
        }
        return $this->pageCount;
    }

    public function setPageCount($pageCount)
    {
        $this->pageCount = $pageCount;
    }
}
