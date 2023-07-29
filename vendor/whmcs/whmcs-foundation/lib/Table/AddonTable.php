<?php

namespace WHMCS\Table;

class AddonTable extends AbstractTable
{
    protected $castColumns = ["status"];

    protected function processData($addons)
    {
        if (!empty($addons)) {
            $assetHelper = \DI::make("asset");
            $noDomainString = \AdminLang::trans("addons.nodomain");
            $translatedStatus = [];
            foreach (\WHMCS\Utility\Status::SERVICE_STATUSES as $status) {
                $key = \WHMCS\Utility\Status::normalise($status);
                $translatedStatus[$status] = \AdminLang::trans("status." . $key);
            }
            foreach ($addons as $addon) {
                $idShort = ltrim($addon->id, "0");
                $addonLink = $assetHelper->getWebRoot() . "/" . $addon->getLink();
                $domainLink = $addon->domain && (filter_var($addon->domain, FILTER_VALIDATE_DOMAIN) || filter_var($addon->domain, FILTER_VALIDATE_IP)) ? "https://" . $addon->domain : (string) $addonLink;
                $domainString = $addon->domain ? $addon->domain : "(" . $noDomainString . ")";
                $name = $addon->name;
                if (!$addon->name && $addon->productAddon) {
                    $name = $addon->productAddon->name;
                }
                $columnData = ["checkbox" => "<input type=\"checkbox\" name=\"seladdons[]\" value=\"" . $addon->id . "\" class=\"checkprods\" />", "id" => "<a href=\"" . $addonLink . "\">" . $idShort . "</a>", "name" => $name . "<br>" . $addon->service->product->name . " - <a href=\"" . $domainLink . "\" target=\"_blank\">" . $domainString . "</a>", "recurring" => formatCurrency($addon->recurringFee), "billingcycle" => $addon->billingCycle, "paymentmethod" => ucfirst($addon->paymentGateway), "regdate" => $addon->registrationDate->toAdminDateFormat(), "nextduedate" => $addon->isRecurring() ? fromMySQLDate($addon->nextDueDate) : "-", "status" => $translatedStatus[$addon->status], "actions" => "<a href=\"" . $addonLink . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Edit\"></a>"];
                $dataRow = [];
                foreach ($this->getColumns() as $column) {
                    $dataRow[$column] = $columnData[$column] ?? "";
                }
                $dataRow["DT_RowAttr"] = ["id" => "addon" . $idShort];
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
        $adminPreferences["tableLengths"]["summaryAddons"] = $length;
        $admin->userPreferences = $adminPreferences;
        $admin->save();
        $this->setColumns($request);
        $collection = \WHMCS\Service\Addon::leftJoin("tbladdons", "tbladdons.id", "=", "tblhostingaddons.addonid")->where("userid", $request->get("clientId"))->select("tblhostingaddons.*")->selectRaw("IF(tblhostingaddons.name = '' AND tblhostingaddons.addonid, tbladdons.name, tblhostingaddons.name) AS name");
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
