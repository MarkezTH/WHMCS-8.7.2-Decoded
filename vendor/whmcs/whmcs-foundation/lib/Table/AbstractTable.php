<?php

namespace WHMCS\Table;

abstract class AbstractTable implements Contracts\TableInterface
{
    protected $data = [];
    protected $columns = NULL;
    protected $castColumns = NULL;
    protected $totalData = NULL;
    protected $totalFiltered = NULL;
    protected $filters = [];

    public function list($JsonResponse, $request)
    {
        if (!$request->isXHR()) {
            throw new \WHMCS\Exception("Invalid request.");
        }
        $this->processData($this->getData($request));
        return new \WHMCS\Http\Message\JsonResponse(["draw" => (int) $request->get("draw"), "recordsTotal" => $this->totalData, "recordsFiltered" => $this->totalFiltered, "data" => $this->data]);
    }

    protected function setColumns($request)
    {
        foreach ($request->get("columns") as $column) {
            $this->columns[] = $column["name"];
            if (in_array($column["name"], ["domainstatus", "status", "stage"])) {
                $this->filters = array_filter(explode("|", $column["search"]["value"]));
            }
        }
    }

    protected function getColumns()
    {
        return $this->columns;
    }
}
