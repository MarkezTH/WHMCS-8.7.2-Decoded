<?php

namespace WHMCS\Table;

class QuoteTable extends AbstractTable
{
    protected $castColumns = ["stage"];

    protected function processData($quotes)
    {
        if (!empty($quotes)) {
            $assetHelper = \DI::make("asset");
            $translatedStatus = [];
            foreach (\WHMCS\Utility\Status::QUOTE_STATUSES as $status) {
                $key = \WHMCS\Utility\Status::normalise($status);
                $translatedStatus[$status] = \AdminLang::trans("status." . $key);
            }
            foreach ($quotes as $quote) {
                $idShort = ltrim($quote->id, "0");
                $quoteLink = $assetHelper->getWebRoot() . "/" . $quote->getLink();
                $currency = $quote->client ? $quote->client->currencyrel : $quote->currency;
                $columnData = ["id" => "<a href=\"" . $quoteLink . "\">" . $idShort . "</a>", "subject" => $quote->subject, "datecreated" => fromMySQLDate($quote->dateCreated), "validuntil" => fromMySQLDate($quote->validUntilDate), "total" => formatCurrency($quote->total, $currency), "stage" => $translatedStatus[$quote->status], "actions" => "<a href=\"" . $quoteLink . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Edit\"></a>"];
                $dataRow = [];
                foreach ($this->getColumns() as $column) {
                    $dataRow[$column] = $columnData[$column] ?? "";
                }
                $dataRow["DT_RowAttr"] = ["id" => "quote" . $idShort];
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
        $adminPreferences["tableLengths"]["summaryQuotes"] = $length;
        $admin->userPreferences = $adminPreferences;
        $admin->save();
        $this->setColumns($request);
        $collection = \WHMCS\Billing\Quote::with("client")->where("userid", $request->get("clientId"));
        $this->totalData = $this->totalFiltered = $collection->count();
        $collection = $collection->offset($request->get("start"))->limit($request->get("length"));
        foreach ($request->get("order") as $orderBy) {
            $column = $this->columns[$orderBy["column"]];
            $collection = $collection->orderBy(in_array($column, $this->castColumns) ? \WHMCS\Database\Capsule::raw("CAST(" . $column . " as CHAR)") : $column, preg_match("/^(asc|desc)\$/", $orderBy["dir"]) ? $orderBy["dir"] : "asc");
        }
        return $collection->get();
    }
}
