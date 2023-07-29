<?php

namespace WHMCS\Table;

class AffiliatesWithdrawalHistoryTable extends AbstractTable
{
    protected $castColumns = ["domainstatus"];

    protected function processData($history)
    {
        if (empty($history)) {
            return NULL;
        }
        $aInt = new \WHMCS\Admin("Manage Affiliates", false);
        foreach ($history as $historyDatum) {
            $currency = getCurrency($historyDatum->affiliateid);
            $deleteButton = "<a href=\"#\" " . "onClick=\"doWithdrawHistoryDelete('" . $historyDatum->id . "');return false\">" . "<img src=\"images/delete.gif\" border=\"0\"></a>";
            $columnData = ["date" => fromMySQLDate($historyDatum->date), "amount" => formatCurrency($historyDatum->amount, $currency["id"]), "actions" => $deleteButton];
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
        $collection = \WHMCS\Affiliate\Withdrawals::where("affiliateid", $request->get("affiliateId"));
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
