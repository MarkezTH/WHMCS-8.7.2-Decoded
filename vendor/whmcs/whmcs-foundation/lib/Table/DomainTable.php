<?php

namespace WHMCS\Table;

class DomainTable extends AbstractTable
{
    protected $castColumns = ["status"];

    protected function processData($domains)
    {
        if (!empty($domains)) {
            $assetHelper = \DI::make("asset");
            $translatedStatus = [];
            foreach (\WHMCS\Utility\Status::DOMAIN_STATUSES as $status) {
                $key = \WHMCS\Utility\Status::normalise($status);
                $translatedStatus[$status] = \AdminLang::trans("status." . $key);
            }
            foreach ($domains as $domain) {
                $idShort = ltrim($domain->id, "0");
                $domainLink = $assetHelper->getWebRoot() . "/" . $domain->getLink();
                $columnData = ["checkbox" => "<input type=\"checkbox\" name=\"seldomains[]\" value=\"" . $domain->id . "\" class=\"checkprods\" />", "id" => "<a href=\"" . $domainLink . "\">" . $idShort . "</a>", "domain" => "<a href=\"https://" . $domain->domain . "\" target=\"_blank\">" . $domain->domain . "</a>", "registrar" => ucfirst($domain->registrarModuleName), "paymentmethod" => ucfirst($domain->paymentGateway), "registrationdate" => fromMySQLDate($domain->registrationDate), "nextduedate" => fromMySQLDate($domain->nextDueDate), "expirydate" => fromMySQLDate($domain->expiryDate), "status" => $translatedStatus[$domain->status], "actions" => "<a href=\"" . $domainLink . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Edit\"></a>"];
                $dataRow = [];
                foreach ($this->getColumns() as $column) {
                    $dataRow[$column] = $columnData[$column] ?? "";
                }
                $dataRow["DT_RowAttr"] = ["id" => "domain" . $idShort];
                $this->data[] = $dataRow;
            }
        }
    }

    protected function getData($Collection, $request)
    {
        $admin = (new \WHMCS\Authentication\CurrentUser())->admin();
        $length = $request->get("length", 10);
        $adminPreferences = $admin->userPreferences;
        if (empty($adminPreferences["tableLengths"])) {
            $adminPreferences["tableLengths"] = [];
        }
        if (empty($adminPreferences["tableLengths"]["default"])) {
            $adminPreferences["tableLengths"]["default"] = 10;
        }
        $adminPreferences["tableLengths"]["summaryDomains"] = $length;
        $admin->userPreferences = $adminPreferences;
        $admin->save();
        $this->setColumns($request);
        $collection = \WHMCS\Domain\Domain::where("userid", $request->get("clientId"));
        $this->totalData = $collection->count();
        $collection->whereIn("status", $this->filters);
        $this->totalFiltered = $collection->count();
        $collection = $collection->offset($request->get("start"))->limit($length);
        foreach ($request->get("order") as $orderBy) {
            $column = $this->columns[$orderBy["column"]];
            $collection = $collection->orderBy(in_array($column, $this->castColumns) ? \WHMCS\Database\Capsule::raw("CAST(" . $column . " as CHAR)") : $column, preg_match("/^(asc|desc)\$/", $orderBy["dir"]) ? $orderBy["dir"] : "asc");
        }
        return $collection->get();
    }
}
