<?php

namespace WHMCS\Table;

class AffiliatesCommissionHistoryTable extends AbstractTable
{
    protected $castColumns = ["domainstatus"];

    protected function processData($history)
    {
        if (empty($history)) {
            return NULL;
        }
        $aInt = new \WHMCS\Admin("Manage Affiliates", false);
        foreach ($history as $historyDatum) {
            $status = "";
            $productLink = "";
            $clientLink = "";
            $currency = getCurrency($historyDatum->affiliateid);
            if ($historyDatum->affiliateAccountId) {
                $serviceModel = $historyDatum->account->service;
                $clientModel = $serviceModel->client;
                $userid = $clientModel->id;
                $status = $serviceModel->status;
                $productLink = sprintf("<a href=\"clientshosting.php?userid=%d&id=%d\">%s</a>", $userid, $serviceModel->id, $serviceModel->product->name);
                $clientLink = $aInt->outputClientLink($userid, $clientModel->firstName, $clientModel->lastName, $clientModel->companyName);
                unset($serviceModel);
                unset($clientModel);
            }
            $invoiceNumString = "";
            if ($historyDatum->invoice) {
                $invoiceNumString = sprintf("<a href=\"%s\">%s</a>", $historyDatum->invoice->getEditInvoiceUrl(), $historyDatum->invoice->getInvoiceNumber());
            }
            $deleteButton = "<a href=\"#\" " . "onClick=\"doAffHistoryDelete('" . $historyDatum->id . "');return false\">" . "<img src=\"images/delete.gif\" border=\"0\"></a>";
            $columnData = ["date" => fromMySQLDate($historyDatum->date), "affaccid" => $historyDatum->affiliateAccountId, "clientname" => $clientLink, "description" => $historyDatum->description ?? "&nbsp;", "product" => $productLink, "productstatus" => $status, "invoicenum" => $invoiceNumString, "amount" => formatCurrency($historyDatum->amount, $currency["id"]), "historyid" => $historyDatum->id, "actions" => $deleteButton];
            $dataRow = [];
            foreach ($this->getColumns() as $column) {
                $dataRow[$column] = $columnData[$column] ?? "";
            }
            $dataRow["DT_RowAttr"] = ["id" => "history" . $historyDatum->id];
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
        $collection = \WHMCS\Affiliate\History::where("affiliateid", $request->get("affiliateId"));
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
