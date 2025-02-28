<?php

namespace WHMCS\Cron\Task;

class DataNormalization extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $accessLevel = \WHMCS\Scheduling\Task\TaskInterface::ACCESS_SYSTEM;
    protected $defaultPriority = 710;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "Normalize Database";
    protected $defaultName = "Database Normalization";
    protected $systemName = "DataNormalization";
    protected $outputs = [];

    public function __invoke()
    {
        full_query("DELETE FROM tblinvoices WHERE userid NOT IN (SELECT id FROM tblclients)");
        full_query("UPDATE tbltickets SET did=(SELECT id FROM tblticketdepartments ORDER BY `order` ASC LIMIT 1) WHERE did NOT IN (SELECT id FROM tblticketdepartments)");
        update_query("tblclients", ["currency" => "1"], ["currency" => "0"]);
        update_query("tblaccounts", ["currency" => "1"], ["currency" => "0", "userid" => "0"]);
        $tables = ["tblhosting", "tbldomains"];
        foreach ($tables as $table) {
            $result = select_query($table, "id,userid", ["paymentmethod" => ""]);
            while ($data = mysql_fetch_assoc($result)) {
                ensurePaymentMethodIsSet($data["userid"], $data["id"], $table);
            }
        }
        $result = select_query("tblhostingaddons", "tblhostingaddons.id as id,tblhosting.userid as userid", ["tblhostingaddons.paymentmethod" => ""], "", "", "", "tblhosting on tblhostingaddons.hostingid = tblhosting.id");
        while ($data = mysql_fetch_assoc($result)) {
            ensurePaymentMethodIsSet($data["userid"], $data["id"], "tblhostingaddons");
        }
        return $this;
    }
}
