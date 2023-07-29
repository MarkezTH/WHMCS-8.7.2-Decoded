<?php

namespace WHMCS\Table;

class AffiliatesPendingCommissionTable extends AbstractTable
{
    protected $castColumns = ["domainstatus"];

    protected function processData($pending)
    {
        if (empty($pending)) {
            return NULL;
        }
        $aInt = new \WHMCS\Admin("Manage Affiliates", false);
        foreach ($pending as $pendingDatum) {
            $serviceModel = $pendingDatum->account->service;
            $clientModel = $serviceModel->client;
            $currency = getCurrency($pendingDatum->account->affiliateid);
            $invoiceNumString = "";
            if ($pendingDatum->invoice) {
                $invoiceNumString = sprintf("<a href=\"%s\">%s</a>", $pendingDatum->invoice->getEditInvoiceUrl(), $pendingDatum->invoice->getInvoiceNumber());
            }
            $productLink = sprintf("<a href=\"clientshosting.php?userid=%d&id=%d\">%s</a>", $clientModel->id, $serviceModel->id, $serviceModel->product->name);
            $deleteButton = "<a href=\"#\" " . "onClick=\"doPendingCommissionDelete('" . $pendingDatum->id . "');return false\">" . "<img src=\"images/delete.gif\" border=\"0\"></a>";
            $columnData = ["affaccid" => $pendingDatum->affiliateAccountId, "clientname" => $aInt->outputClientLink($clientModel->id, $clientModel->firstName, $clientModel->lastName, $clientModel->companyName), "product" => $productLink, "productstatus" => $serviceModel->status, "invoicenum" => $invoiceNumString, "amount" => formatCurrency($pendingDatum->amount, $currency["id"]), "pendingid" => $pendingDatum->id, "userid" => $clientModel->id, "relid" => $serviceModel->id, "actions" => $deleteButton, "clearingdate" => fromMySQLDate($pendingDatum->clearingDate)];
            $dataRow = [];
            foreach ($this->getColumns() as $column) {
                $dataRow[$column] = $columnData[$column] ?? "";
            }
            $dataRow["DT_RowAttr"] = ["id" => "pending" . $pendingDatum->id];
            $this->data[] = $dataRow;
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
        $collection = \WHMCS\Affiliate\Pending::whereHas("account", function ($query) use($request) {
            return $query->where("affiliateid", $request->get("affiliateId"));
        });
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
}
