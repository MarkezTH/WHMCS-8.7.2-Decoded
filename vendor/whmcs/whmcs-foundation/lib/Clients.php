<?php

namespace WHMCS;

class Clients extends TableModel
{
    private $groups = NULL;
    private $customfieldsfilter = false;

    public function _execute($implementationData = [])
    {
        return $this->getClients($implementationData);
    }

    public function getClients($criteria)
    {
        global $disable_clients_list_services_summary;
        $clients = [];
        $clientgroups = $this->getGroups();
        $filters = $this->buildCriteria($criteria);
        $matchedResult = User\Client::query();
        if ($this->customfieldsfilter) {
            $matchedResult = $matchedResult->join("tblcustomfieldsvalues", "tblcustomfieldsvalues.relid", "=", "tblclients.id");
        }
        $joinPayMethodTables = !empty($criteria["cctype"]) || !empty($criteria["cclastfour"]);
        if ($joinPayMethodTables) {
            $matchedResult = $matchedResult->leftJoin("tblpaymethods", "tblpaymethods.userid", "tblclients.id")->leftJoin("tblcreditcards", "tblpaymethods.id", "=", "tblcreditcards.pay_method_id")->whereNull(["tblpaymethods.deleted_at", "tblcreditcards.deleted_at"]);
        }
        $inactiveResult = clone $matchedResult;
        $inactiveResult->whereIn("status", ["Inactive", "Closed"]);
        foreach ($filters as $key => $filter) {
            if (substr($filter, 0, 6) !== "status") {
                $inactiveResult->whereRaw($filter);
            }
            $matchedResult = $matchedResult->whereRaw($filter);
        }
        $matchedCount = $matchedResult->distinct("tblclients.id")->count();
        $inactiveCount = $inactiveResult->distinct("tblclients.id")->count();
        $this->getPageObj()->setNumResults($matchedCount);
        $this->getPageObj()->setHiddenCount($inactiveCount);
        $matchedResult = $matchedResult->select("tblclients.*")->orderBy("tblclients." . $this->getPageObj()->getOrderBy(), $this->getPageObj()->getSortDirection())->offset($this->getRecordOffset())->limit($this->getRecordLimit());
        $results = $matchedResult->distinct("tblclients.*")->get();
        foreach ($results as $data) {
            $datecreated = fromMySQLDate($data->datecreated);
            $groupid = $data->groupid;
            $groupcolor = isset($clientgroups[$groupid]["colour"]) ? $clientgroups[$groupid]["colour"] . "\"" : "";
            $services = $totalservices = "-";
            if (!$disable_clients_list_services_summary) {
                $result2 = full_query("SELECT (SELECT COUNT(*) FROM tblhosting WHERE userid=tblclients.id AND domainstatus IN ('Active','Suspended'))+(SELECT COUNT(*) FROM tblhostingaddons WHERE hostingid IN (SELECT id FROM tblhosting WHERE userid=tblclients.id) AND status IN ('Active','Suspended'))+(SELECT COUNT(*) FROM tbldomains WHERE userid=tblclients.id AND status IN ('Active')) AS services,(SELECT COUNT(*) FROM tblhosting WHERE userid=tblclients.id)+(SELECT COUNT(*) FROM tblhostingaddons WHERE hostingid IN (SELECT id FROM tblhosting WHERE userid=tblclients.id))+(SELECT COUNT(*) FROM tbldomains WHERE userid=tblclients.id) AS totalservices FROM tblclients WHERE tblclients.id=" . (int) $data->id . " LIMIT 1");
                $data2 = mysql_fetch_array($result2);
                $services = $data2["services"];
                $totalservices = $data2["totalservices"];
            }
            $clients[] = ["id" => $data->id, "firstname" => $data->firstname, "lastname" => $data->lastname, "companyname" => $data->companyname, "groupid" => $data->groupid, "groupcolor" => $groupcolor, "email" => $data->email, "services" => $services, "totalservices" => $totalservices, "datecreated" => $datecreated, "status" => $data->status];
        }
        return $clients;
    }

    private function buildCriteria($criteria)
    {
        $enabled = function ($facet) {
            return isset($criteria[$facet]) && $criteria[$facet];
        };
        $filters = [];
        if ($enabled("userid")) {
            $filters[] = "id=" . (int) $criteria["userid"];
        }
        if ($enabled("name")) {
            $filters[] = "concat(firstname,' ',lastname,' ',companyname) LIKE '%" . db_escape_string($criteria["name"]) . "%'";
        }
        if ($enabled("address1")) {
            $filters[] = "address1 LIKE '%" . db_escape_string($criteria["address1"]) . "%'";
        }
        if ($enabled("address2")) {
            $filters[] = "address2 LIKE '%" . db_escape_string($criteria["address2"]) . "%'";
        }
        if ($enabled("city")) {
            $filters[] = "city LIKE '%" . db_escape_string($criteria["city"]) . "%'";
        }
        if ($enabled("state")) {
            $filters[] = "state LIKE '%" . db_escape_string($criteria["state"]) . "%'";
        }
        if ($enabled("postcode")) {
            $filters[] = "postcode LIKE '%" . db_escape_string($criteria["postcode"]) . "%'";
        }
        if ($enabled("country")) {
            $filters[] = "country='" . db_escape_string($criteria["country"]) . "'";
        }
        if ($enabled("email")) {
            $filters[] = "email LIKE '%" . db_escape_string($criteria["email"]) . "%'";
        }
        if ($enabled("email2")) {
            $filters[] = "email LIKE '%" . db_escape_string($criteria["email2"]) . "%'";
        }
        if ($enabled("phone")) {
            $rawPhone = $phone = db_escape_string($criteria["phone"]);
            if ($enabled("country-calling-code-phone")) {
                $phone = "+" . db_escape_string($criteria["country-calling-code-phone"]) . "%" . $rawPhone;
            }
            $filters[] = "(phonenumber LIKE '" . $phone . "%' OR phonenumber LIKE '%" . $rawPhone . "%')";
        }
        if ($enabled("phone2")) {
            $rawPhone = $phone = db_escape_string($criteria["phone2"]);
            if ($enabled("country-calling-code-phone2")) {
                $phone = "+" . db_escape_string($criteria["country-calling-code-phone2"]) . "%" . $rawPhone;
            }
            $filters[] = "(phonenumber LIKE '" . $phone . "%' OR phonenumber LIKE '%" . $rawPhone . "%')";
        }
        if ($enabled("status") && $criteria["status"] != "any") {
            $filters[] = "status='" . db_escape_string($criteria["status"]) . "'";
        } else {
            if (isset($criteria["status"]) && $criteria["status"] != "any" && (\App::isInRequest("show_hidden") && !\App::getFromRequest("show_hidden") || !\App::isInRequest("show_hidden"))) {
                $filters[] = "status='Active'";
            }
        }
        if ($enabled("group")) {
            $filters[] = "groupid=" . (int) $criteria["group"];
        }
        if ($enabled("group2")) {
            $filters[] = "groupid=" . (int) $criteria["group2"];
        }
        if ($enabled("paymentmethod")) {
            $filters[] = "defaultgateway='" . db_escape_string($criteria["paymentmethod"]) . "'";
        }
        if ($enabled("cctype")) {
            $value = db_escape_string($criteria["cctype"]);
            $filters[] = "( tblclients.cardtype='" . $value . "'" . " OR tblcreditcards.card_type='" . $value . "'" . " )";
        }
        if ($enabled("cclastfour")) {
            $value = db_escape_string($criteria["cclastfour"]);
            $filters[] = "( tblclients.cardlastfour='" . $value . "'" . " OR tblcreditcards.last_four='" . $value . "'" . " )";
        }
        if ($enabled("autoccbilling")) {
            if ($criteria["autoccbilling"] === "true") {
                $filters[] = "disableautocc=1";
            } else {
                $filters[] = "disableautocc!=1";
            }
        }
        if ($enabled("credit")) {
            $filters[] = "credit='" . db_escape_string($criteria["credit"]) . "'";
        }
        if ($enabled("currency")) {
            $filters[] = "currency=" . (int) $criteria["currency"];
        }
        if ($enabled("language")) {
            $filters[] = "language='" . db_escape_string($criteria["language"]) . "'";
        }
        if ($enabled("marketingoptin")) {
            $filters[] = "marketing_emails_opt_in='" . (int) ($criteria["marketingoptin"] === "true") . "'";
        }
        if ($enabled("emailverification")) {
            if ($criteria["emailverification"] === "true") {
                $filters[] = "email_verified=1";
            } else {
                $filters[] = "email_verified!=1";
            }
        }
        if ($enabled("autostatus")) {
            if ($criteria["autostatus"] === "true") {
                $filters[] = "overrideautoclose=1";
            } else {
                $filters[] = "overrideautoclose!=1";
            }
        }
        if ($enabled("taxexempt")) {
            if ($criteria["taxexempt"] === "true") {
                $filters[] = "taxexempt=1";
            } else {
                $filters[] = "taxexempt!=1";
            }
        }
        if ($enabled("latefees")) {
            if ($criteria["latefees"] === "true") {
                $filters[] = "latefeeoveride=1";
            } else {
                $filters[] = "latefeeoveride!=1";
            }
        }
        if ($enabled("overduenotices")) {
            if ($criteria["overduenotices"] === "true") {
                $filters[] = "overideduenotices=1";
            } else {
                $filters[] = "overideduenotices!=1";
            }
        }
        if ($enabled("separateinvoices")) {
            if ($criteria["separateinvoices"] === "true") {
                $filters[] = "separateinvoices=1";
            } else {
                $filters[] = "separateinvoices!=1";
            }
        }
        if ($enabled("signupdaterange")) {
            $dateRange = $criteria["signupdaterange"];
            $dateRange = Carbon::parseDateRangeValue($dateRange);
            $dateFrom = $dateRange["from"];
            $dateTo = $dateRange["to"];
            $filters[] = "datecreated >= '" . $dateFrom->toDateTimeString() . "' " . " AND datecreated <= '" . $dateTo->toDateTimeString() . "'";
        }
        $cfquery = [];
        if (isset($criteria["customfields"]) && is_array($criteria["customfields"])) {
            foreach ($criteria["customfields"] as $fieldid => $fieldvalue) {
                $fieldvalue = trim($fieldvalue);
                if ($fieldvalue) {
                    $cfquery[] = "(tblcustomfieldsvalues.fieldid='" . db_escape_string($fieldid) . "' AND tblcustomfieldsvalues.value LIKE '%" . db_escape_string($fieldvalue) . "%')";
                    $this->customfieldsfilter = true;
                }
            }
        }
        if (count($cfquery)) {
            $filters[] = implode(" OR ", $cfquery);
        }
        return $filters;
    }

    public function getGroups()
    {
        if (is_array($this->groups)) {
            return $this->groups;
        }
        $this->groups = [];
        $result = select_query("tblclientgroups", "", "");
        while ($data = mysql_fetch_array($result)) {
            $this->groups[$data["id"]] = ["name" => $data["groupname"], "colour" => $data["groupcolour"], "discountpercent" => $data["discountpercent"], "susptermexempt" => $data["susptermexempt"], "separateinvoices" => $data["separateinvoices"]];
        }
        return $this->groups;
    }

    public function getNumberOfOpenCancellationRequests()
    {
        return (int) get_query_val("tblcancelrequests", "COUNT(tblcancelrequests.id)", "(tblhosting.domainstatus!='Cancelled' AND tblhosting.domainstatus!='Terminated')", "", "", "", "tblhosting ON tblhosting.id=tblcancelrequests.relid INNER JOIN tblproducts ON tblproducts.id=tblhosting.packageid INNER JOIN tblproductgroups ON tblproductgroups.id=tblproducts.gid INNER JOIN tblclients ON tblhosting.userid=tblclients.id");
    }
}
