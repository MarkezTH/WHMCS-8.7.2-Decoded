<?php

namespace WHMCS\Log;

class Activity extends \WHMCS\TableModel
{
    protected $criteria = [];
    protected $outputFormatting = true;

    public function _execute($implementationData = [])
    {
        $this->setCriteria($implementationData);
        $this->getPageObj()->setNumResults($this->getTotalCount());
        $page = $this->getPageObj()->getPage();
        $limit = $this->getRecordLimit();
        $orderBy = $this->getPageObj()->getOrderBy();
        $sort = $this->getPageObj()->getSortDirection();
        return $this->getLogEntries($page - 1, $limit, $orderBy, $sort);
    }

    public function setOutputFormatting($enable)
    {
        $this->outputFormatting = $enable ? true : false;
    }

    public function getOutputFormatting()
    {
        return $this->outputFormatting;
    }

    public function prune()
    {
        $activitylimit = (int) \WHMCS\Config\Setting::getValue("ActivityLimit");
        $deleteFromId = \WHMCS\Database\Capsule::table("tblactivitylog")->where("userid", 0)->orderByDesc("id")->limit(1)->skip($activitylimit)->value("id");
        if ($deleteFromId) {
            \WHMCS\Database\Capsule::table("tblactivitylog")->where("userid", 0)->where("id", "<=", $deleteFromId)->delete();
        }
        return true;
    }

    public function setCriteria($where)
    {
        if (is_array($where)) {
            $this->criteria = $where;
            return true;
        }
        return false;
    }

    public function getCriteria($key)
    {
        return array_key_exists($key, $this->criteria) ? $this->criteria[$key] : "";
    }

    protected function buildCriteria()
    {
        $userid = $this->getCriteria("userid");
        $date = $this->getCriteria("date");
        $dateRange = $this->getCriteria("dateRange");
        $description = $this->getCriteria("description");
        $username = $this->getCriteria("username");
        $ipaddress = $this->getCriteria("ipaddress");
        $where = [];
        if ($userid) {
            $where[] = "userid='" . (int) $userid . "'";
        }
        if ($date) {
            $mysqlDate = toMySQLDate($date);
            $where[] = "date >= '" . $mysqlDate . "' AND date <= '" . $mysqlDate . " 23:59:59'";
        }
        if ($dateRange) {
            $dateParts = \WHMCS\Carbon::parseDateRangeValue($dateRange);
            $dateFrom = $dateParts["from"]->toDateString();
            $dateTo = $dateParts["to"]->toDateString();
            $where[] = "date >= '" . $dateFrom . "' AND date <= '" . $dateTo . " 23:59:59'";
        }
        if ($description) {
            $where[] = "description LIKE '%" . db_escape_string($description) . "%'";
        }
        if ($username) {
            $where[] = "user='" . db_escape_string($username) . "'";
        }
        if ($ipaddress) {
            $where[] = " ipaddr='" . db_escape_string($ipaddress) . "'";
        }
        return implode(" AND ", $where);
    }

    public function getTotalCount()
    {
        $result = select_query("tblactivitylog", "COUNT(id)", $this->buildCriteria());
        $data = mysql_fetch_array($result);
        return (int) $data[0];
    }

    public function getLogEntries($page = 0, $limit = 0, $orderBy = "id", $sort = "DESC")
    {
        $page = (int) $page;
        $limit = (int) $limit;
        if (!$limit) {
            $whmcs = \WHMCS\Application::getInstance();
            $limit = (int) $whmcs->get_config("NumRecordstoDisplay");
        }
        $logs = [];
        $result = select_query("tblactivitylog", "", $this->buildCriteria(), $orderBy, $sort, $page * $limit . "," . $limit);
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $clientId = $data["userid"];
            $userId = $data["user_id"];
            $adminId = $data["admin_id"];
            $date = $data["date"];
            $description = $data["description"];
            $username = $data["user"];
            $ipaddress = $data["ipaddr"];
            if ($this->getOutputFormatting()) {
                $date = fromMySQLDate($date, true);
                $description = \WHMCS\Input\Sanitize::makeSafeForOutput($description);
                $username = \WHMCS\Input\Sanitize::makeSafeForOutput($username);
                $ipaddress = \WHMCS\Input\Sanitize::makeSafeForOutput($ipaddress);
                $description = $this->autoLink($description);
            }
            $logs[] = ["id" => (int) $id, "clientId" => (int) $clientId, "userId" => (int) $userId, "adminId" => (int) $adminId, "date" => $date, "description" => $description, "username" => $username, "ipaddress" => $ipaddress];
        }
        return $logs;
    }

    public function autoLink($description)
    {
        $patterns = $replacements = [];
        $patterns[] = "/Client ID: (.*?) - Contact ID: (.*?) /";
        $replacements[] = "<a href=\"clientscontacts.php?userid=\$1&contactid=\$2\">Contact ID: \$2</a> ";
        $patterns[] = "/Client ID: (.*?) (?!- Contact)/";
        $replacements[] = "<a href=\"clientssummary.php?userid=\$1\">Client ID: \$1</a> ";
        $patterns[] = "/User ID: (.*?) - Contact ID: (.*?) /";
        $replacements[] = "<a href=\"clientscontacts.php?userid=\$1&contactid=\$2\">Contact ID: \$2</a> ";
        $patterns[] = "/User ID: (.*?) (?!- Contact)/";
        $replacements[] = "User ID: \$1 ";
        $patterns[] = "/UserID: (.*?) /";
        $replacements[] = "User ID: \$1 ";
        $patterns[] = "/Service ID: (.*?) /";
        $replacements[] = "<a href=\"clientsservices.php?id=\$1\">Service ID: \$1</a> ";
        $patterns[] = "/Service Addon ID: (\\d+)(\\D*?)/";
        $replacements[] = "<a href=\"clientsservices.php?aid=\$1\">Service Addon ID: \$1</a>";
        $patterns[] = "/Domain ID: (.*?) /";
        $replacements[] = "<a href=\"clientsdomains.php?id=\$1\">Domain ID: \$1</a> ";
        $patterns[] = "/Invoice ID: (.*?) /";
        $replacements[] = "<a href=\"invoices.php?action=edit&id=\$1\">Invoice ID: \$1</a> ";
        $patterns[] = "/Quote ID: (.*?) /";
        $replacements[] = "<a href=\"quotes.php?action=manage&id=\$1\">Quote ID: \$1</a> ";
        $patterns[] = "/Order ID: (.*?) /";
        $replacements[] = "<a href=\"orders.php?action=view&id=\$1\">Order ID: \$1</a> ";
        $patterns[] = "/Transaction ID: (.*?) /";
        $replacements[] = "<a href=\"transactions.php?action=edit&id=\$1\">Transaction ID: \$1</a> ";
        $patterns[] = "/Product ID: (\\d+)(\\D*?)/";
        $replacements[] = "<a href=\"configproducts.php?action=edit&id=\$1\">Product ID: \$1</a>";
        $patterns[] = "/Affiliate ID: (\\d+)(\\D*?)/";
        $replacements[] = "<a href=\"affiliates.php?action=edit&id=\$1\">Affiliate ID: \$1</a>";
        $patterns[] = "/:go-1665-storage-settings-troubleshooting/";
        $replacements[] = "<a href=\"https://go.whmcs.com/1665/storage-settings-troubleshooting\" target=\"_blank\">Storage Settings Troubleshooting</a>";
        $description = preg_replace($patterns, $replacements, $description . " ");
        if (strpos($description, "User ID:") !== false) {
            $description = $this->generateUserModalLink($description);
        }
        return trim($description);
    }

    public function generateUserModalLink($description)
    {
        $routePathForUsers = routePath("admin-user-manage", "USERID");
        $userLangStrings = ["manageUser" => \AdminLang::trans("user.manageUser"), "save" => \AdminLang::trans("global.save")];
        $userAjaxModal = "<a href=\"" . $routePathForUsers . "\"\n    class=\"open-modal\"\n    data-modal-title=\"" . $userLangStrings["manageUser"] . "\"\n    data-modal-size=\"modal-lg\"\n    data-btn-submit-label=\"" . $userLangStrings["save"] . "\"\n    data-btn-submit-id=\"btnUpdateUser\"\n>\n    LINK_TEXT\n</a>";
        $matches = [];
        if (preg_match("/User ID: (\\d*) /", $description, $matches)) {
            $search = ["USERID", "LINK_TEXT"];
            $replace = [$matches[1], $matches[0]];
            $userAjaxModal = str_replace($search, $replace, $userAjaxModal);
            return str_replace($matches[0], $userAjaxModal, $description);
        }
        return $description;
    }
}
