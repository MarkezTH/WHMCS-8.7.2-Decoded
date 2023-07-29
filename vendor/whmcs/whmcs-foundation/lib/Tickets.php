<?php

namespace WHMCS;

class Tickets extends TableModel
{
    public $ticketid = 0;
    public $data = [];
    public $deptids = [];
    public $deptnames = [];
    public $deptemails = [];
    protected $departmentFeedbackRequest = [];
    public $tagticketids = [];

    public function _execute($implementationData = [])
    {
        if (is_array($implementationData) && array_key_exists("tag_ticket_ids", $implementationData)) {
            $this->tagticketids = $implementationData["tag_ticket_ids"];
            unset($implementationData["tag_ticket_ids"]);
        }
        return $this->getTickets($implementationData);
    }

    public function getTickets($criteria = [])
    {
        global $aInt;
        if ($criteria["tag"] || $criteria["multiTags"]) {
            $tagjoin = " INNER JOIN tbltickettags ON tbltickettags.ticketid=tbltickets.id";
        } else {
            $tagjoin = "";
        }
        $query = " FROM tbltickets" . $tagjoin . " INNER JOIN tblticketdepartments ON tblticketdepartments.id=tbltickets.did LEFT JOIN tblclients ON tblclients.id=tbltickets.userid";
        $filters = $this->buildCriteria($criteria);
        if (count($filters)) {
            $query .= " WHERE " . implode(" AND ", $filters);
        }
        $result = full_query("SELECT COUNT(DISTINCT tbltickets.id)" . $query);
        $data = mysql_fetch_array($result);
        $this->getPageObj()->setNumResults($data[0]);
        $query .= " ORDER BY " . $this->getPageObj()->getOrderBy() . " " . $this->getPageObj()->getSortDirection();
        if ($this->getPageObj()->isPaginated()) {
            $query .= " LIMIT " . $this->getQueryLimit();
        }
        $currentAdminId = Auth::getID();
        $tickets = [];
        $result = full_query("SELECT DISTINCT tbltickets.*,tblticketdepartments.name AS deptname,tblclients.firstname,tblclients.lastname,tblclients.companyname,tblclients.groupid" . $query);
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $ticketnumber = $data["tid"];
            $did = $data["did"];
            $deptname = $data["deptname"];
            $puserid = $data["userid"];
            $name = $data["name"];
            $email = $data["email"];
            $date = $data["date"];
            $title = $data["title"];
            $message = $data["message"];
            $tstatus = $data["status"];
            $priority = $data["urgency"];
            $rawlastactivity = $data["lastreply"];
            $flag = $data["flag"];
            $adminread = $data["adminunread"];
            $firstname = $data["firstname"];
            $lastname = $data["lastname"];
            $companyname = $data["companyname"];
            $groupid = $data["groupid"];
            $adminread = explode(",", $adminread);
            $this->addTagCloudID($id);
            $unread = in_array($currentAdminId, $adminread) ? 0 : 1;
            $alttitle = "";
            $title = trim($title);
            if (!$title) {
                $title = "&nbsp;- " . $aInt->lang("emails", "nosubject") . " -&nbsp;";
            }
            if (80 < strlen($title)) {
                $alttitle = $title . "\n";
                $title = $this->getSummary($title, 80);
            }
            $alttitle .= $this->getSummary($message, 250);
            $myAssignedTicketsSection = !empty($criteria["flag"]) && $criteria["flag"] == $currentAdminId;
            if ($flag && (!$myAssignedTicketsSection || $flag != $currentAdminId)) {
                $deptname .= " (" . getAdminName($flag) . ")";
            }
            $date = fromMySQLDate($date, 1);
            $lastactivity = fromMySQLDate($rawlastactivity, 1);
            $tstatus = $this->getColoredStatusLabel($tstatus, 0 < $currentAdminId);
            $lastreply = $this->getShortLastReplyTime($rawlastactivity);
            $clientinfo = $puserid != "0" ? $aInt->outputClientLink($puserid, $firstname, $lastname, $companyname, $groupid) : $name;
            $tickets[] = ["id" => $id, "ticketnum" => $ticketnumber, "priority" => $priority, "department" => $deptname, "subject" => $title, "textsummary" => $alttitle, "status" => $tstatus, "lastreply" => $lastreply, "unread" => $unread, "ticketModel" => Support\Ticket::find($id)];
        }
        return $tickets;
    }

    private function buildCriteria($criteria)
    {
        $filters = [];
        $tag = isset($criteria["tag"]) ? $criteria["tag"] : "";
        $status = isset($criteria["status"]) ? $criteria["status"] : "";
        $multiStatus = !empty($criteria["multiStatus"]) ? (array) $criteria["multiStatus"] : "";
        $ticketid = isset($criteria["ticketid"]) ? $criteria["ticketid"] : "";
        $multiDeptIds = !empty($criteria["multiDeptIds"]) ? (array) $criteria["multiDeptIds"] : "";
        $deptid = isset($criteria["deptid"]) ? $criteria["deptid"] : "";
        $subject = isset($criteria["subject"]) ? $criteria["subject"] : "";
        $email = isset($criteria["email"]) ? $criteria["email"] : "";
        $client = isset($criteria["client"]) ? $criteria["client"] : "";
        $clientid = isset($criteria["clientid"]) ? $criteria["clientid"] : "";
        $clientname = isset($criteria["clientname"]) ? $criteria["clientname"] : "";
        $flag = isset($criteria["flag"]) ? $criteria["flag"] : "";
        $notflaggedto = isset($criteria["notflaggedto"]) ? $criteria["notflaggedto"] : "";
        $searchFlag = isset($criteria["searchFlag"]) ? $criteria["searchFlag"] : "";
        $priority = !empty($criteria["priority"]) ? (array) $criteria["priority"] : "";
        $multiTags = !empty($criteria["multiTags"]) ? (array) $criteria["multiTags"] : "";
        if ($client) {
            if (is_numeric($client)) {
                $clientid = $client;
            } else {
                $clientname = $client;
            }
        }
        $deptids = $this->getAdminsDeptIDs();
        $filters[] = "tbltickets.did IN (" . db_build_in_array($deptids) . ")";
        if ($multiStatus) {
            $flagFilter = "";
            if (in_array("flagged", $multiStatus) && !$notflaggedto) {
                $multiStatus = array_flip($multiStatus);
                unset($multiStatus["flagged"]);
                $multiStatus = array_flip($multiStatus);
                $statuses = $multiStatus && in_array("any", $multiStatus) ? Database\Capsule::table("tblticketstatuses")->pluck("title")->all() : ($multiStatus ?: Database\Capsule::table("tblticketstatuses")->whereShowactive(1)->pluck("title")->all());
                $flagFilter = " OR (tbltickets.status IN (" . db_build_in_array($statuses) . ") AND flag=" . (int) $_SESSION["adminid"] . ")";
            }
            if ($multiStatus && !in_array("any", $multiStatus)) {
                $filters[] = "(tbltickets.status IN (" . db_build_in_array($multiStatus) . ")" . $flagFilter . ")";
            } else {
                if ($flagFilter) {
                    $filters[] = substr($flagFilter, 4);
                }
            }
        } else {
            if ($status == "Awaiting Reply" || $status == "awaitingreply" || $status == "") {
                $statusfilter = Database\Capsule::table("tblticketstatuses")->whereShowawaiting(1)->pluck("title")->all();
                $filters[] = "tbltickets.status IN (" . db_build_in_array($statusfilter) . ")";
            } else {
                if (!($status == "All Tickets" || $status == "all" || $status == "any")) {
                    if ($status == "All Active Tickets" || $status == "active") {
                        $statusfilter = Database\Capsule::table("tblticketstatuses")->whereShowactive(1)->pluck("title")->all();
                        $filters[] = "tbltickets.status IN (" . db_build_in_array($statusfilter) . ")";
                    } else {
                        if ($status == "Flagged Tickets" || $status == "flagged") {
                            $statusfilter = Database\Capsule::table("tblticketstatuses")->whereShowactive(1)->pluck("title")->all();
                            $filters[] = "tbltickets.status IN (" . db_build_in_array($statusfilter) . ") AND flag=" . (int) $_SESSION["adminid"];
                        } else {
                            $filters[] = "tbltickets.status='" . db_escape_string($status) . "'";
                        }
                    }
                }
            }
        }
        if ($tag) {
            if ($tag != "any") {
                $tag = db_escape_string($tag);
                $filters[] = "tbltickettags.tag='" . $tag . "'";
            }
        } else {
            if ($multiTags && !in_array("any", $multiTags)) {
                $filters[] = "tbltickettags.tag IN (" . db_build_in_array($multiTags) . ")";
            }
        }
        if (!($clientid || $subject || $email || $clientname)) {
            if (!checkPermission("View Flagged Tickets", true)) {
                $filters[] = "(flag=" . (int) $_SESSION["adminid"] . " OR flag=0)";
            }
        }
        if ($ticketid) {
            $filters[] = "tbltickets.tid='" . db_escape_string($ticketid) . "'";
        } else {
            $filters[] = "tbltickets.merged_ticket_id = 0";
        }
        if ($clientid) {
            $filters[] = "tbltickets.userid='" . db_escape_string($clientid) . "'";
        }
        if ($multiDeptIds) {
            $filters[] = "tbltickets.did IN (" . db_build_in_array($multiDeptIds) . ")";
        } else {
            if ($deptid) {
                $filters[] = "tbltickets.did='" . db_escape_string($deptid) . "'";
            }
        }
        if ($subject) {
            $filters[] = "(tbltickets.title LIKE '%" . db_escape_string($subject) . "%' OR tbltickets.message LIKE '%" . db_escape_string($subject) . "%')";
        }
        if ($email) {
            $filters[] = "(tbltickets.email LIKE '%" . db_escape_string($email) . "%' OR tblclients.email LIKE '%" . db_escape_string($email) . "%' OR tbltickets.name LIKE '%" . db_escape_string($email) . "%')";
        }
        if ($clientname) {
            $filters[] = "(tbltickets.name LIKE '%" . db_escape_string($clientname) . "%' OR concat(tblclients.firstname,' ',tblclients.lastname) LIKE '%" . db_escape_string($clientname) . "%')";
        }
        if ($searchFlag) {
            $filters[] = "tbltickets.flag=" . (int) $searchFlag;
        } else {
            if ($flag) {
                $filters[] = "tbltickets.flag=" . (int) $flag;
            }
            if ($notflaggedto) {
                $filters[] = "tbltickets.flag!=" . (int) $notflaggedto;
            }
        }
        if ($priority) {
            $filters[] = "tbltickets.urgency IN (" . db_build_in_array($priority) . ")";
        }
        return $filters;
    }

    public function getAdminsDeptIDs()
    {
        $deptids = [];
        $admin_supportdepts = explode(",", get_query_val("tbladmins", "supportdepts", ["id" => $_SESSION["adminid"]]));
        foreach ($admin_supportdepts as $deptid) {
            if (trim($deptid)) {
                $deptids[] = (int) $deptid;
            }
        }
        return $deptids;
    }

    public function getAdminSig()
    {
        $adminid = Session::get("adminid");
        if (!$adminid) {
            return false;
        }
        return get_query_val("tbladmins", "signature", ["id" => $adminid]);
    }

    public function getStatuses($counts = false)
    {
        $ticketcounts = [];
        if ($counts) {
            $ticketcounts[] = ["label" => "Awaiting Reply", "count" => 0];
            $ticketcounts[] = ["label" => "All Active Tickets", "count" => 0];
            $ticketcounts[] = ["label" => "Flagged Tickets", "count" => 0];
            $admin_supportdepts_qry = $this->getAdminsDeptIDs();
            if (count($admin_supportdepts_qry) < 1) {
                $admin_supportdepts_qry[] = 0;
            }
            $query = "SELECT tblticketstatuses.title,(SELECT COUNT(tbltickets.id) FROM tbltickets WHERE did IN (" . db_build_in_array($admin_supportdepts_qry) . ") AND tbltickets.status=tblticketstatuses.title),showactive,showawaiting FROM tblticketstatuses ORDER BY sortorder ASC";
        } else {
            $ticketcounts[] = "Awaiting Reply";
            $ticketcounts[] = "All Active Tickets";
            $ticketcounts[] = "Flagged Tickets";
            $query = "SELECT title FROM tblticketstatuses ORDER BY sortorder ASC";
        }
        $result = full_query($query);
        while ($data = mysql_fetch_array($result)) {
            if ($counts) {
                $ticketcounts[] = ["label" => $data[0], "count" => $data[1]];
                if ($data["showactive"]) {
                    $ticketcounts[1]["count"] += $data[1];
                }
                if ($data["showawaiting"]) {
                    $ticketcounts[0]["count"] += $data[1];
                }
            } else {
                $ticketcounts[] = $data[0];
            }
        }
        if ($counts) {
            $result = select_query("tbltickets", "COUNT(*)", "status!='Closed' AND flag='" . (int) $_SESSION["adminid"] . "'");
            $data = mysql_fetch_array($result);
            $ticketcounts[2]["count"] = $data[0];
        }
        return $ticketcounts;
    }

    public function getStatusesWithCounts()
    {
        return $this->getStatuses(true);
    }

    public function getAssignableStatuses()
    {
        $statuses = $this->getStatuses();
        unset($statuses[0]);
        unset($statuses[1]);
        unset($statuses[2]);
        return $statuses;
    }

    public function setID($ticketid)
    {
        $this->ticketid = (int) $ticketid;
        $data = $this->getData();
        return is_array($data) ? true : false;
    }

    public function getData($var = "")
    {
        if ($var) {
            return $this->data[$var] ?? NULL;
        }
        $result = select_query("tbltickets", "", ["id" => $this->ticketid]);
        $data = mysql_fetch_assoc($result);
        if ($data) {
            $data["watchers"] = Ticket\Watchers::ofTicket($this->ticketid)->pluck("admin_id")->all();
        }
        $this->data = $data;
        return $data;
    }

    public function getDepartments()
    {
        if (count($this->deptids)) {
            return false;
        }
        $ticketDepartments = Database\Capsule::table("tblticketdepartments")->orderBy("order")->get(["id", "name", "email", "feedback_request"])->all();
        foreach ($ticketDepartments as $ticketDepartment) {
            $this->deptids[] = $ticketDepartment->id;
            $this->deptnames[$ticketDepartment->id] = $ticketDepartment->name;
            $this->deptemails[$ticketDepartment->email] = $ticketDepartment->id;
            $this->departmentFeedbackRequest[$ticketDepartment->id] = $ticketDepartment->feedback_request;
        }
        return true;
    }

    public function getDeptName($deptid = "")
    {
        $this->getDepartments();
        if (!$deptid) {
            $deptid = $this->getData("did");
        }
        return $this->deptnames[$deptid];
    }

    public function getAdminsDepartments()
    {
        $this->getDepartments();
        $adminsdepts = $this->getAdminsDeptIDs();
        $depts = $this->deptnames;
        foreach ($depts as $deptid => $deptname) {
            if (!in_array($deptid, $adminsdepts)) {
                unset($depts[$deptid]);
            }
        }
        return $depts;
    }

    public function getClientName()
    {
        if (!count($this->data)) {
            $this->getData();
        }
        if ($this->getData("userid")) {
            if ($this->getData("contactid")) {
                $clientname = get_query_val("tblcontacts", "CONCAT(firstname,' ',lastname)", ["id" => $this->getData("contactid"), "userid" => $this->getData("userid")]);
            } else {
                $clientname = get_query_val("tblclients", "CONCAT(firstname,' ',lastname)", ["id" => $this->getData("userid")]);
            }
        } else {
            $clientname = $this->getData("name");
        }
        return $clientname;
    }

    public function validateDept($deptid = "")
    {
        $this->getDepartments();
        if (in_array($deptid, $this->deptids)) {
            return true;
        }
        return false;
    }

    public function setDept($newdeptid)
    {
        if (!$this->validateDept($newdeptid)) {
            return false;
        }
        if ($newdeptid == $this->getData("did")) {
            return false;
        }
        if (!count($this->data)) {
            $this->getData();
        }
        migrateCustomFields("support", $this->getData("id"), $newdeptid);
        update_query("tbltickets", ["did" => $newdeptid], ["id" => $this->getData("id")]);
        $this->data["did"] = $newdeptid;
        $deptname = $this->getDeptName();
        $this->log("Department changed to " . $deptname);
        run_hook("TicketDepartmentChange", ["ticketid" => $this->getData("id"), "deptid" => $newdeptid, "deptname" => $deptname]);
        return true;
    }

    public function changeDept($newdeptid)
    {
        return $this->setDept($newdeptid);
    }

    public function setStatus($newstatus)
    {
        $validstatuses = $this->getAssignableStatuses();
        if ($newstatus == $this->getData("status")) {
            return false;
        }
        if (!in_array($newstatus, $validstatuses)) {
            return false;
        }
        update_query("tbltickets", ["status" => $newstatus], ["id" => $this->getData("id")]);
        $this->log("Status changed to " . $newstatus);
        run_hook("TicketStatusChange", ["ticketid" => $this->getData("id"), "status" => $newstatus]);
        return true;
    }

    public function setSubject($newsubject)
    {
        $newsubject = trim($newsubject);
        if (!$newsubject) {
            return false;
        }
        if ($newsubject == $this->getData("title")) {
            return false;
        }
        update_query("tbltickets", ["title" => $newsubject], ["id" => $this->getData("id")]);
        $this->log("Subject changed to '" . $newsubject . "'");
        run_hook("TicketSubjectChange", ["ticketid" => $this->getData("id"), "subject" => $newsubject]);
        return true;
    }

    public function setFlagTo($adminid)
    {
        $adminid = (int) $adminid;
        $validadminids = $this->getFlaggableStaff();
        if ($adminid != 0 && !array_key_exists($adminid, $validadminids)) {
            return false;
        }
        if ($adminid == $this->getData("flag")) {
            return false;
        }
        $adminname = "";
        if (0 < $adminid) {
            $data = get_query_vals("tbladmins", "id,firstname,lastname,username", ["id" => $adminid]);
            if (!$data["id"]) {
                return false;
            }
            $adminname = trim($data["firstname"] . " " . $data["lastname"]);
            if (!$adminname) {
                $adminname = $data["username"];
            }
        } else {
            if ($adminid < 0) {
                $adminid = 0;
            }
        }
        if (!count($this->data)) {
            $this->getData();
        }
        update_query("tbltickets", ["flag" => $adminid], ["id" => $this->getData("id")]);
        if (0 < $adminid) {
            $this->log("Assigned to Staff Member " . $adminname);
        } else {
            $this->log("Staff Assignment Removed");
        }
        run_hook("TicketFlagged", ["ticketid" => $this->getData("id"), "adminid" => $adminid, "adminname" => $adminname]);
        return true;
    }

    public function setPriority($newpriority)
    {
        $validpriorities = $this->getPriorities();
        if ($newpriority == $this->getData("urgency")) {
            return false;
        }
        if (!in_array($newpriority, $validpriorities)) {
            return false;
        }
        update_query("tbltickets", ["urgency" => $newpriority], ["id" => $this->getData("id")]);
        $this->log("Priority changed to " . $newpriority);
        run_hook("TicketPriorityChange", ["ticketid" => $this->getData("id"), "priority" => $newpriority]);
        return true;
    }

    public function sendAdminEmail($tplname, $adminid = "", $notifydeptadmins = false, $vars = [], $getlatestmsg = false)
    {
        $messagetxt = "";
        if ($getlatestmsg) {
            $messagetxt = get_query_val("tblticketreplies", "message", ["tid" => $this->getData("id")], "id", "DESC");
        }
        $tplvars = ["ticket_id" => $this->getData("id"), "ticket_tid" => $this->getData("tid"), "client_id" => $this->getData("userid"), "client_name" => $this->getClientName(), "ticket_department" => $this->getDeptName(), "ticket_subject" => $this->getData("title"), "ticket_priority" => $this->getData("urgency"), "ticket_message" => $this->formatMsg($messagetxt)];
        if (is_array($vars)) {
            foreach ($vars as $k => $v) {
                $tplvars[$k] = $v;
            }
        }
        sendAdminMessage($tplname, $tplvars, "support", $this->getData("did"), $adminid, $notifydeptadmins);
    }

    public function log($msg)
    {
        addTicketLog($this->getData("id"), $msg);
    }

    public function addTagCloudID($ticketid)
    {
        $this->tagticketids[] = (int) $ticketid;
    }

    public function getTagTicketIds()
    {
        return $this->tagticketids;
    }

    public function getTagCloudData()
    {
        if (!count($this->tagticketids)) {
            return [];
        }
        $tags = [];
        $result = full_query("SELECT `tag`, COUNT(*) AS `count` FROM `tbltickettags` WHERE ticketid IN (" . db_build_in_array($this->tagticketids) . ") GROUP BY `tag` ORDER BY `count` DESC");
        while ($data = mysql_fetch_assoc($result)) {
            $tags[] = $data;
        }
        return $tags;
    }

    public function buildTagCloud()
    {
        $tags = $this->getTagCloudData();
        $tagcount = count($tags);
        if ($tagcount) {
            $numtags = $tagcount / 10;
            $numtags = ceil($numtags);
            $output = "";
            $fontsize = "24";
            $i = 0;
            foreach ($tags as $tag) {
                $thisfontsize = $fontsize;
                if ($tag["count"] <= 1) {
                    $thisfontsize = "12";
                }
                $tagcontent = strip_tags($tag["tag"]);
                $tagcontent = htmlspecialchars($tagcontent);
                $output .= "<a href=\"supporttickets.php?tag=" . $tagcontent . "\" style=\"font-size:" . $thisfontsize . "px;\">" . $tagcontent . "</a> ";
                $i++;
                if ($i == $numtags) {
                    $fontsize -= 2;
                    $i = 0;
                }
            }
        } else {
            $output = "None";
        }
        return $output;
    }

    public function getShortLastReplyTime($lastreply)
    {
        if (!function_exists("getShortLastReplyTime")) {
            require_once ROOTDIR . "/includes/ticketfunctions.php";
        }
        return getShortLastReplyTime($lastreply);
    }

    public function getLastReplyTime($lastreply = "", $from = "now")
    {
        if (!function_exists("getLastReplyTime")) {
            require_once ROOTDIR . "/includes/ticketfunctions.php";
        }
        return getLastReplyTime($lastreply);
    }

    public function getSummary($text, $length = 100)
    {
        $tail = "...";
        $text = strip_tags($text);
        $txtl = strlen($text);
        if ($length < $txtl) {
            for ($i = 1; $text[$length - $i] != " "; $i++) {
                if ($i == $length) {
                    return substr($text, 0, $length) . $tail;
                }
            }
            $text = substr($text, 0, $length - $i + 1) . $tail;
        }
        return $text;
    }

    public function getColoredStatusLabel($tstatus = false, $isAdmin)
    {
        if (!array_key_exists($tstatus, $ticketcolors)) {
            $ticketcolors[$tstatus] = $color = get_query_val("tblticketstatuses", "color", ["title" => $tstatus]);
        } else {
            $color = $ticketcolors[$tstatus];
        }
        $langstatus = preg_replace("/[^a-z]/i", "", strtolower($tstatus));
        if ($langstatus != "") {
            if ($isAdmin) {
                $tstatus = Application\Support\Facades\AdminLang::trans("supportticketsstatus." . $langstatus);
            } else {
                $tstatus = \Lang::trans("supportticketsstatus" . $langstatus);
            }
        }
        $statuslabel = "";
        if ($color) {
            $statuslabel .= "<span style=\"color:" . $color . "\">";
        }
        $statuslabel .= $tstatus;
        if ($color) {
            $statuslabel .= "</span>";
        }
        return $statuslabel;
    }

    public function getReplies()
    {
        global $whmcs;
        global $aInt;
        $id = $this->getData("id");
        $replies = [];
        $result = select_query("tbltickets", "userid,contactid,name,email,date,title,message,admin,attachment,attachments_removed", ["id" => $id]);
        $data = mysql_fetch_array($result);
        $userid = $data["userid"];
        $contactid = $data["contactid"];
        $name = $data["name"];
        $email = $data["email"];
        $date = $data["date"];
        $title = $data["title"];
        $message = $data["message"];
        $admin = $data["admin"];
        $attachment = $data["attachment"];
        $attachmentsRemoved = (bool) (int) $data["attachments_removed"];
        $friendlydate = substr($date, 0, 10) == date("Y-m-d") ? "" : (substr($date, 0, 4) == date("Y") ? Carbon::createFromFormat("Y-m-d H:i:s", $date)->format("l jS F") : Carbon::createFromFormat("Y-m-d H:i:s", $date)->format("l jS F Y"));
        $friendlytime = date("H:i", strtotime($date));
        $date = fromMySQLDate($date, true);
        $message = $this->formatMsg($message);
        if ($userid) {
            $name = $aInt->outputClientLink([$userid, $contactid]);
        }
        $attachments = $this->getTicketAttachmentsInfo("", $attachment, $attachmentsRemoved);
        $replies[] = ["id" => 0, "admin" => $admin, "userid" => $userid, "contactid" => $contactid, "clientname" => $name, "clientemail" => $email, "date" => $date, "friendlydate" => $friendlydate, "friendlytime" => $friendlytime, "message" => $message, "attachments" => $attachments, "attachments_removed" => $attachmentsRemoved, "numattachments" => count($attachments)];
        $result = select_query("tblticketreplies", "", ["tid" => $id], "date", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $replyid = $data["id"];
            $userid = $data["userid"];
            $contactid = $data["contactid"];
            $name = $data["name"];
            $email = $data["email"];
            $date = $data["date"];
            $message = $data["message"];
            $attachment = $data["attachment"];
            $attachmentsRemoved = (bool) (int) $data["attachments_removed"];
            $admin = $data["admin"];
            $rating = $data["rating"];
            $friendlydate = substr($date, 0, 10) == date("Y-m-d") ? "" : (substr($date, 0, 4) == date("Y") ? Carbon::createFromFormat("Y-m-d H:i:s", $date)->format("l jS F") : Carbon::createFromFormat("Y-m-d H:i:s", $date)->format("l jS F Y"));
            $friendlytime = date("H:i", strtotime($date));
            $date = fromMySQLDate($date, true);
            $message = $this->formatMsg($message);
            if ($userid) {
                $name = $aInt->outputClientLink([$userid, $contactid]);
            }
            $attachments = $this->getTicketAttachmentsInfo($replyid, $attachment, $attachmentsRemoved);
            $ratingstars = "";
            if ($admin && $rating) {
                for ($i = 1; $i <= 5; $i++) {
                    $ratingstars .= $i <= $rating ? "<img src=\"../images/rating_pos.png\" align=\"absmiddle\">" : "<img src=\"../images/rating_neg.png\" align=\"absmiddle\">";
                }
            }
            $replies[] = ["id" => $replyid, "admin" => $admin, "userid" => $userid, "contactid" => $contactid, "clientname" => $name, "clientemail" => $email, "date" => $date, "friendlydate" => $friendlydate, "friendlytime" => $friendlytime, "message" => $message, "attachments" => $attachments, "numattachments" => count($attachments), "rating" => $ratingstars];
        }
        if ($whmcs->get_config("SupportTicketOrder") == "DESC") {
            krsort($replies);
        }
        return $replies;
    }

    public function formatMsg($message = "")
    {
        if (!$message) {
            $message = $this->getData("message");
        }
        $message = strip_tags($message);
        $message = preg_replace("/\\[div=\"(.*?)\"\\]/", "<div class=\"\$1\">", $message);
        $replacetags = ["b" => "strong", "i" => "em", "u" => "ul", "div" => "div"];
        foreach ($replacetags as $k => $v) {
            $message = str_replace("[" . $k . "]", "<" . $k . ">", $message);
            $message = str_replace("[/" . $k . "]", "</" . $k . ">", $message);
        }
        $message = nl2br($message);
        $message = autoHyperLink($message);
        return $message;
    }

    public function getTicketAttachmentsInfo($replyid, $attachment, $removed = false)
    {
        $ticketid = $this->getData("id");
        $attachments = [];
        if ($attachment) {
            $attachment = explode("|", $attachment);
            foreach ($attachment as $num => $file) {
                $file = substr($file, 7);
                if ($removed) {
                    $attachments[] = ["filename" => $file, "dllink" => "", "deletelink" => ""];
                } else {
                    if ($replyid) {
                        $attachments[] = ["filename" => $file, "dllink" => "dl.php?type=ar&id=" . $replyid . "&i=" . $num, "deletelink" => $PHP_SELF . "?action=viewticket&id=" . $ticketid . "&removeattachment=true&type=r&idsd=" . $replyid . "&filecount=" . $num . generate_token("link")];
                    } else {
                        $attachments[] = ["filename" => $file, "dllink" => "dl.php?type=a&id=" . $ticketid . "&i=" . $num, "deletelink" => $PHP_SELF . "?action=viewticket&id=" . $ticketid . "&removeattachment=true&idsd=" . $ticketid . "&filecount=" . $num . generate_token("link")];
                    }
                }
            }
        }
        return $attachments;
    }

    public function getNotes()
    {
        $notes = [];
        $result = select_query("tblticketnotes", "", ["ticketid" => $this->getData("id")], "date", "DESC");
        while ($data = mysql_fetch_array($result)) {
            $date = $data["date"];
            $friendlydate = substr($date, 0, 10) == date("Y-m-d") ? "" : (substr($date, 0, 4) == date("Y") ? Carbon::createFromFormat("Y-m-d H:i:s", $date)->format("l jS F") : Carbon::createFromFormat("Y-m-d H:i:s", $date)->format("l jS F Y"));
            $friendlytime = date("H:i", strtotime($date));
            $notes[] = ["id" => $data["id"], "admin" => $data["admin"], "date" => fromMySQLDate($date, true), "friendlydate" => $friendlydate, "friendlytime" => $friendlytime, "message" => $this->formatMsg($data["message"])];
        }
        return $notes;
    }

    public function getFlaggableStaff()
    {
        $staff = [];
        $result = select_query("tbladmins", "id,firstname,lastname", "disabled=0 OR id='" . (int) $this->getData("flag") . "'", "firstname` ASC,`lastname", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $staff[$data["id"]] = $data["firstname"] . " " . $data["lastname"];
        }
        return $staff;
    }

    public function getPriorities()
    {
        return ["Low", "Medium", "High"];
    }

    public function getAllowedAttachments()
    {
        global $whmcs;
        $filetypes = $whmcs->get_config("TicketAllowedFileTypes");
        $filetypes = explode(",", $filetypes);
        foreach ($filetypes as $k => $v) {
            $filetypes[$k] = trim($v);
        }
        return $filetypes;
    }

    public static function notifyTicketChanges($ticketId, $changes, $recipients = [], $removeRecipients = [])
    {
        $ticket = new self();
        if ($ticket->setID($ticketId)) {
            $mergeFields = ["ticket_id" => $ticketId, "ticket_tid" => $ticket->getData("tid"), "newReply" => NULL, "newNote" => NULL, "newTicket" => NULL, "newAttachments" => NULL];
            if (!empty($changes["Reply"])) {
                $markup = new View\Markup\Markup();
                $markupFormat = $markup->determineMarkupEditor("ticket_reply", $ticket->getData("editor"));
                $mergeFields["newReply"] = $markup->transform($changes["Reply"]["new"], $markupFormat);
                unset($changes["Reply"]);
            }
            if (!empty($changes["Note"])) {
                if (!isset($markup)) {
                    $markup = new View\Markup\Markup();
                }
                $markupFormat = $markup->determineMarkupEditor("ticket_note", $changes["Note"]["editor"]);
                $mergeFields["newNote"] = $markup->transform($changes["Note"]["new"], $markupFormat);
                unset($changes["Note"]);
            }
            if (!empty($changes["Opened"]) && !isset($markup)) {
                $markup = new View\Markup\Markup();
                $markupFormat = $markup->determineMarkupEditor("ticket_note", $ticket->getData("editor"));
                $mergeFields["newTicket"] = $markup->transform($changes["Opened"]["new"], $markupFormat);
            }
            if (!empty($changes["Attachments"])) {
                $mergeFields["newAttachments"] = $changes["Attachments"];
                unset($changes["Attachments"]);
            }
            $mergeFields["changer"] = $changes["Who"];
            unset($changes["Who"]);
            $mergeFields["changes"] = $changes;
            $mergeFields["client_name"] = $ticket->getClientName();
            $mergeFields["client_id"] = $ticket->getData("userid");
            $mergeFields["ticket_department"] = $ticket->getDeptName();
            $mergeFields["ticket_subject"] = $ticket->getData("title");
            $mergeFields["ticket_priority"] = $ticket->getData("urgency");
            $includeFlagged = true;
            if (!empty($changes["Assigned To"])) {
                if ($changes["Assigned To"]["newId"] == Session::get("adminid")) {
                    $includeFlagged = false;
                }
                if ($changes["Assigned To"]["oldId"] && $changes["Assigned To"]["oldId"] != Session::get("adminid")) {
                    $recipients = array_merge($recipients, [$changes["Assigned To"]["oldId"]]);
                }
            }
            if (!empty($changes["Department"])) {
                $recipients = array_merge($recipients, getDepartmentNotificationIds($changes["Department"]["newId"]));
            }
            $recipients = array_unique(array_merge(0 < $ticket->getData("flag") && $includeFlagged ? [$ticket->getData("flag")] : [], $recipients, Ticket\Watchers::ofTicket($ticket->ticketid)->pluck("admin_id")->all()));
            if ($removeRecipients) {
                $recipients = array_filter($recipients, function ($value) use($removeRecipients) {
                    return !in_array($value, $removeRecipients);
                });
            }
            $recipients = array_flip($recipients);
            unset($recipients[(int) Session::get("adminid")]);
            $recipients = array_flip($recipients);
            if (0 < count($recipients)) {
                return sendAdminMessage("Support Ticket Change Notification", $mergeFields, "ticket_changes", $ticket->getData("did"), $recipients);
            }
        }
        return false;
    }

    public function getDepartmentFeedbackNotifications()
    {
        $this->getDepartments();
        if (!$this->departmentFeedbackRequest) {
            return false;
        }
        return isset($this->departmentFeedbackRequest[$this->getData("did")]) ? (bool) (int) $this->departmentFeedbackRequest[$this->getData("did")] : false;
    }
}
