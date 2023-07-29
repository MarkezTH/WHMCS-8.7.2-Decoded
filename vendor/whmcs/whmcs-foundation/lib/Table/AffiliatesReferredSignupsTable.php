<?php

namespace WHMCS\Table;

class AffiliatesReferredSignupsTable extends AbstractTable
{
    protected $castColumns = ["domainstatus"];

    protected function processData($signups)
    {
        if (empty($signups)) {
            return NULL;
        }
        $aInt = new \WHMCS\Admin("Manage Affiliates", false);
        foreach ($signups as $signupDatum) {
            $serviceModel = $signupDatum->service;
            $clientModel = $serviceModel->client;
            $currency = getCurrency($signupDatum->affiliateid);
            $commission = calculateAffiliateCommission($signupDatum->affiliateId, $serviceModel->id, $signupDatum->lastpaid);
            $commission = formatCurrency($commission, $currency["id"]);
            $lastPaid = $signupDatum->lastpaid->isEmpty() ? \AdminLang::trans("affiliates.never") : fromMySQLDate($signupDatum->lastpaid);
            $productLink = sprintf("<a href=\"clientshosting.php?userid=%d&id=%d\">%s</a><br>%s", $clientModel->id, $serviceModel->id, $serviceModel->product->name, self::getServiceAmountDescription($serviceModel));
            $payoutLink = sprintf("<a href=\"affiliates.php?action=edit&id=%d&pay=true&affaccid=%d&serviceid=%d&userid=%d\">%s<br>%s</a>", $signupDatum->affiliateId, $signupDatum->id, $serviceModel->id, $clientModel->id, \AdminLang::trans("affiliates.manual"), \AdminLang::trans("affiliates.payout"));
            $deleteButton = "<a href=\"#\" " . "onClick=\"doAccDelete('" . $signupDatum->id . "');return false\">" . "<img src=\"images/delete.gif\" border=\"0\"></a>";
            $columnData = ["id" => $signupDatum->id, "regdate" => fromMySQLDate($serviceModel->regdate), "affaccid" => $signupDatum->id, "clientname" => $aInt->outputClientLink($clientModel->id, $clientModel->firstName, $clientModel->lastName, $clientModel->companyName), "name" => $productLink, "productstatus" => $serviceModel->status, "amount" => $commission, "lastpaid" => $lastPaid, "other" => $payoutLink, "actions" => $deleteButton];
            $dataRow = [];
            foreach ($this->getColumns() as $column) {
                $dataRow[$column] = $columnData[$column] ?? "";
            }
            $dataRow["DT_RowAttr"] = ["id" => "accounts" . $signupDatum->id];
            $this->data[] = $dataRow;
            unset($serviceModel);
            unset($clientModel);
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
        $adminPreferences["tableLengths"]["summaryAffiliate"] = $length;
        $admin->userPreferences = $adminPreferences;
        $admin->save();
        $this->setColumns($request);
        $select = ["tblaffiliatesaccounts.id", "tblaffiliatesaccounts.affiliateid", "tblaffiliatesaccounts.lastpaid", "tblaffiliatesaccounts.relid", \WHMCS\Database\Capsule::raw("CONCAT(\n                    tblclients.firstname,\" \" , tblclients.lastname, \" \", tblclients.currency\n                ) as clientname"), "tblproducts.name", "tblhosting.userid", "tblhosting.domainstatus as productstatus", "tblhosting.domain", "tblhosting.amount", "tblhosting.firstpaymentamount", "tblhosting.regdate", "tblhosting.billingcycle"];
        $collection = \WHMCS\Affiliate\Accounts::has("service")->select($select)->where("affiliateid", $request->get("affiliateId"))->join("tblhosting", "tblhosting.id", "=", "relid")->join("tblclients", "tblclients.id", "=", "userid")->join("tblproducts", "tblproducts.id", "=", "packageid");
        $this->totalData = $collection->count();
        $this->totalFiltered = $collection->count();
        $collection = $collection->offset($request->get("start"))->limit($length);
        foreach ($request->get("order") as $orderBy) {
            $column = $this->columns[$orderBy["column"]];
            if (in_array($column, $this->castColumns)) {
                $column = \WHMCS\Database\Capsule::raw("CAST(" . $column . " as CHAR)");
            }
            $direction = preg_match("/^(asc|desc)\$/", $orderBy["dir"]) ? $orderBy["dir"] : "asc";
            $collection = $collection->orderBy($column, $direction);
        }
        return $collection->get();
    }

    public static function getServiceAmountDescription($service)
    {
        $description = "";
        $currency = getCurrency($service->client->id);
        if (in_array($service->billingCycle, ["Free", "Free Account"])) {
            $description = "Free";
        } else {
            if ($service->billingCycle == "One Time") {
                $description = sprintf("%s %s", formatCurrency($service->firstPaymentAmount, $currency["id"]), $service->billingCycle);
            } else {
                if ($service->firstPaymentAmount != $service->recurringAmount) {
                    $description .= sprintf("%s %s ", formatCurrency($service->firstPaymentAmount, $currency["id"]), \AdminLang::trans("affiliates.initiallythen"));
                }
                $description .= sprintf("%s %s", formatCurrency($service->recurringAmount, $currency["id"]), $service->billingCycle);
            }
        }
        return $description;
    }
}
