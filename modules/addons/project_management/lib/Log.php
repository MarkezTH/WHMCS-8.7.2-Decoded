<?php

namespace WHMCS\Module\Addon\ProjectManagement;

class Log extends BaseProjectEntity
{
    protected function formatLogTime($date)
    {
        return date("g:ia", strtotime($date));
    }

    public function get()
    {
        $where = ["projectid" => $this->project->id];
        $adminNames = [];
        $result = select_query("tbladmins", "id, firstname, lastname", "");
        while ($data = mysql_fetch_array($result)) {
            $adminNames[$data["id"]] = $data["firstname"] . " " . $data["lastname"];
        }
        $log = [];
        $result = select_query("mod_projectlog", "", $where, "date", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $log[] = ["id" => $data["id"], "date" => fromMySQlDate($data["date"]) . " " . $this->formatLogTime($data["date"]), "message" => $data["msg"], "adminId" => $data["adminid"], "adminName" => $adminNames[$data["adminid"]]];
        }
        return $log;
    }

    public function add($message)
    {
        insert_query("mod_projectlog", ["projectid" => $this->project->id, "date" => "now()", "msg" => $message, "adminid" => Helper::getCurrentAdminId()]);
    }
}
