<?php

namespace WHMCS\Table;

class ServiceTable extends AbstractTable
{
    protected $castColumns = ["domainstatus"];

    protected function processData($services)
    {
        if (!empty($services)) {
            $assetHelper = \DI::make("asset");
            $noDomainString = \AdminLang::trans("addons.nodomain");
            foreach (\WHMCS\Utility\Status::SERVICE_STATUSES as $status) {
                $key = \WHMCS\Utility\Status::normalise($status);
                $translatedStatus[$status] = \AdminLang::trans("status." . $key);
            }
            foreach ($services as $service) {
                $idShort = ltrim($service->id, "0");
                $serviceLink = $assetHelper->getWebRoot() . "/" . $service->getLink();
                $domainLink = $service->domain && (filter_var($service->domain, FILTER_VALIDATE_DOMAIN) || filter_var($service->domain, FILTER_VALIDATE_IP)) ? "https://" . $service->domain : (string) $serviceLink;
                $domainString = $service->domain ? $service->domain : "(" . $noDomainString . ")";
                $columnData = ["checkbox" => "<input type=\"checkbox\" name=\"selproducts[]\" value=\"" . $service->id . "\" class=\"checkprods\" />", "id" => "<a href=\"" . $serviceLink . "\">" . $idShort . "</a>", "name" => $service->product->name . " - <a href=\"" . $domainLink . "\" target=\"_blank\">" . $domainString . "</a>", "amount" => formatCurrency($service->isRecurring() ? $service->recurringAmount : $service->firstPaymentAmount), "billingcycle" => $service->billingCycle, "paymentmethod" => ucfirst($service->paymentGateway), "regdate" => $service->registrationDate->toAdminDateFormat(), "nextduedate" => $service->isRecurring() ? fromMySQLDate($service->nextDueDate) : "-", "domainstatus" => $translatedStatus[$service->status], "actions" => "<a href=\"" . $serviceLink . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Edit\"></a>"];
                $dataRow = [];
                foreach ($this->getColumns() as $column) {
                    $dataRow[$column] = $columnData[$column] ?? "";
                }
                $dataRow["DT_RowAttr"] = ["id" => "service" . $idShort];
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
        $adminPreferences["tableLengths"]["summaryServices"] = $length;
        $admin->userPreferences = $adminPreferences;
        $admin->save();
        $this->setColumns($request);
        $collection = \WHMCS\Service\Service::join("tblproducts", "tblhosting.packageid", "=", "tblproducts.id")->where("userid", $request->get("clientId"))->select(["tblhosting.*", "tblproducts.name"]);
        $this->totalData = $collection->count();
        $collection->whereIn("domainstatus", $this->filters);
        $this->totalFiltered = $collection->count();
        $collection = $collection->offset($request->get("start"))->limit($length);
        foreach ($request->get("order") as $orderBy) {
            $column = $this->columns[$orderBy["column"]];
            $collection = $collection->orderBy(in_array($column, $this->castColumns) ? \WHMCS\Database\Capsule::raw("CAST(" . $column . " as CHAR)") : $column, preg_match("/^(asc|desc)\$/", $orderBy["dir"]) ? $orderBy["dir"] : "asc");
        }
        return $collection->get();
    }
}
