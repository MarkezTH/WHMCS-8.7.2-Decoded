<?php

namespace WHMCS\User\Admin;

class Permission
{
    protected $permission = ["1" => "Main Homepage", "3" => "My Account", "4" => "List Clients", "152" => "List Users", "153" => "Delete Users", "151" => "Manage Users", "150" => "View Account Users", "5" => "List Services", "6" => "List Addons", "7" => "List Domains", "8" => "Add New Client", "104" => "View Clients Summary", "120" => "Allow Login as Owner", "9" => "Edit Clients Details", "128" => "View Credit Log", "129" => "Manage Credits", "10" => "Manage Pay Methods", "106" => "Decrypt Full Credit Card Number", "107" => "Update/Delete Stored Credit Card", "123" => "Attempts CC Captures", "11" => "View Clients Products/Services", "12" => "Edit Clients Products/Services", "99" => "Create Upgrade/Downgrade Orders", "13" => "Delete Clients Products/Services", "14" => "Perform Server Operations", "15" => "View Clients Domains", "16" => "Edit Clients Domains", "17" => "Delete Clients Domains", "98" => "Perform Registrar Operations", "95" => "Manage Clients Files", "18" => "View Clients Notes", "19" => "Add/Edit Client Notes", "97" => "Delete Client Notes", "20" => "Delete Client", "21" => "Mass Mail", "22" => "View Cancellation Requests", "23" => "Manage Affiliates", "24" => "View Orders", "25" => "Delete Order", "26" => "View Order Details", "27" => "Add New Order", "130" => "Use Any Promotion Code on Order", "28" => "List Transactions", "94" => "View Income Totals", "154" => "View Gateway Balances", "29" => "Add Transaction", "30" => "Edit Transaction", "31" => "Delete Transaction", "33" => "List Invoices", "34" => "Create Invoice", "149" => "Create Add Funds Invoice", "124" => "Generate Due Invoices", "35" => "Manage Invoice", "36" => "Delete Invoice", "92" => "Refund Invoice Payments", "89" => "View Billable Items", "90" => "Manage Billable Items", "37" => "Offline Credit Card Processing", "32" => "View Gateway Log", "155" => "List Disputes", "156" => "Manage Disputes", "157" => "Close Disputes", "85" => "Manage Quotes", "38" => "Support Center Overview", "39" => "Manage Announcements", "40" => "Manage Knowledgebase", "41" => "Manage Downloads", "84" => "Manage Network Issues", "42" => "List Support Tickets", "105" => "View Support Ticket", "121" => "Access All Tickets Directly", "82" => "View Flagged Tickets", "43" => "Open New Ticket", "93" => "Delete Ticket", "125" => "Create Predefined Replies", "44" => "Manage Predefined Replies", "126" => "Delete Predefined Replies", "45" => "View Reports", "146" => "Client Data Export", "88" => "Mass Data Export", "46" => "Addon Modules", "135" => "Update WHMCS", "136" => "Modify Update Configuration", "131" => "WHMCSConnect", "101" => "Email Marketer", "47" => "Link Tracking", "49" => "Calendar", "50" => "To-Do List", "51" => "WHOIS Lookups", "52" => "Domain Resolver Checker", "53" => "View Integration Code", "54" => "WHM Import Script", "138" => "Automation Status", "55" => "Database Status", "56" => "System Cleanup Operations", "57" => "View PHP Info", "58" => "View Activity Log", "59" => "View Admin Log", "60" => "View Email Message Log", "61" => "View Ticket Mail Import Log", "62" => "View WHOIS Lookup Log", "103" => "View Module Debug Log", "137" => "View Module Queue", "63" => "Configure General Settings", "148" => "Apps and Integrations", "143" => "Configure Sign-In Integration", "67" => "Configure Automation Settings", "141" => "Manage MarketConnect", "145" => "View MarketConnect Balance", "144" => "Manage Notifications", "147" => "Manage Storage Settings", "133" => "Configure Application Links", "134" => "Configure OpenID Connect", "64" => "Configure Administrators", "65" => "Configure Admin Roles", "127" => "Configure Two-Factor Authentication", "142" => "Manage API Credentials", "100" => "Configure Addon Modules", "91" => "Configure Client Groups", "66" => "Configure Servers", "86" => "Configure Currencies", "68" => "Configure Payment Gateways", "69" => "Tax Configuration", "70" => "View Email Templates", "113" => "Create/Edit Email Templates", "114" => "Delete Email Templates", "115" => "Manage Email Template Languages", "71" => "View Products/Services", "119" => "Manage Product Groups", "116" => "Create New Products/Services", "117" => "Edit Products/Services", "118" => "Delete Products/Services", "72" => "Configure Product Addons", "102" => "Configure Product Bundles", "73" => "View Promotions", "108" => "Create/Edit Promotions", "109" => "Delete Promotions", "74" => "Configure Domain Pricing", "75" => "Configure Support Departments", "140" => "Configure Escalation Rules", "96" => "Configure Ticket Statuses", "122" => "Configure Order Statuses", "76" => "Configure Spam Control", "110" => "View Banned IPs", "111" => "Add Banned IP", "112" => "Unban Banned IP", "77" => "Configure Banned Emails", "78" => "Configure Domain Registrars", "79" => "Configure Fraud Protection", "80" => "Configure Custom Client Fields", "87" => "Configure Security Questions", "83" => "Configure Database Backups", "132" => "Health and Updates", "139" => "View What's New", "81" => "API Access"];
    const GROUP_SETUP = ["Configure General Settings", "Apps and Integrations", "Configure Sign-In Integration", "Configure Automation Settings", "Manage MarketConnect", "View MarketConnect Balance", "Manage Notifications", "Manage Storage Settings", "Configure Application Links", "Configure OpenID Connect", "Configure Administrators", "Configure Admin Roles", "Configure Two-Factor Authentication", "Manage API Credentials", "Configure Addon Modules", "Configure Client Groups", "Configure Servers", "Configure Currencies", "Configure Payment Gateways", "Tax Configuration", "View Email Templates", "Create/Edit Email Templates", "Delete Email Templates", "Manage Email Template Languages", "View Products/Services", "Manage Product Groups", "Create New Products/Services", "Edit Products/Services", "Delete Products/Services", "Configure Product Addons", "Configure Product Bundles", "View Promotions", "Create/Edit Promotions", "Delete Promotions", "Configure Domain Pricing", "Configure Support Departments", "Configure Escalation Rules", "Configure Ticket Statuses", "Configure Order Statuses", "Configure Spam Control", "View Banned IPs", "Add Banned IP", "Unban Banned IP", "Configure Banned Emails", "Configure Domain Registrars", "Configure Fraud Protection", "Configure Custom Client Fields", "Configure Security Questions", "Configure Database Backups", "Health and Updates"];
    const PERMISSION_GROUP_SETUP = "SETUP";

    public static function all()
    {
        $authz = new self();
        return $authz->permission;
    }

    public static function findId($authorizationName)
    {
        $allAuthz = self::all();
        $id = array_keys($allAuthz, $authorizationName);
        if (count($id)) {
            return $id[0];
        }
        return 0;
    }

    public static function findName($id)
    {
        $allAuthz = self::all();
        if (isset($allAuthz[$id])) {
            return $allAuthz[$id];
        }
        return NULL;
    }

    public static function hasPermissionId($permissionId, $adminId)
    {
        if (!$permissionId && $adminId) {
            return false;
        }
        if (!$adminId) {
            return false;
        }
        $result = select_query("tbladmins", "roleid", ["id" => $adminId]);
        $data = mysql_fetch_array($result);
        $roleId = $data["roleid"];
        if (!$roleId) {
            return false;
        }
        $result = select_query("tbladminperms", "COUNT(*)", ["roleid" => $roleId, "permid" => $permissionId]);
        $data = mysql_fetch_array($result);
        if (!empty($data[0])) {
            return true;
        }
        return false;
    }

    public static function currentAdminHasPermissionName($permName)
    {
        return self::hasPermissionId(self::findId($permName), \WHMCS\Admin::getID());
    }

    public static function currentAdminHasAnyPermissionName($permissionNames)
    {
        $adminId = \WHMCS\Admin::getID();
        if (empty($permissionNames) || !$adminId) {
            return false;
        }
        $permissionIds = [];
        foreach ($permissionNames as $name) {
            $permId = self::findId($name);
            if ($permId !== 0) {
                $permissionIds[] = $permId;
            }
        }
        if (!empty($permissionIds)) {
            $admin = \WHMCS\User\Admin::find($adminId);
            $check = \WHMCS\Database\Capsule::table("tbladminperms")->where("roleid", $admin->roleId)->whereIn("permid", $permissionIds)->count();
            if (0 < $check) {
                return true;
            }
        }
        return false;
    }

    public static function hasPermissionInGroup($group)
    {
        $group = strtoupper($group);
        $permissionGroup = constant("WHMCS\\User\\Admin\\Permission::GROUP_" . $group);
        if ($permissionGroup) {
            return self::currentAdminHasAnyPermissionName($permissionGroup);
        }
        return false;
    }
}
