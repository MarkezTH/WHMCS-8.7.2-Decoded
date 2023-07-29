<?php

namespace WHMCS\Api\V1;

class Catalog
{
    protected $groups = [];
    protected $actions = [];
    const SETTING_API_CATALOG = "ApiCatalog";
    const GROUP_ADDONS = "Addons";
    const GROUP_AFFILIATES = "Affiliates";
    const GROUP_AUTHENTICATION = "Authentication";
    const GROUP_BILLING = "Billing";
    const GROUP_CLIENT = "Client";
    const GROUP_CUSTOM = "Custom";
    const GROUP_DOMAINS = "Domains";
    const GROUP_MODULE = "Module";
    const GROUP_ORDERS = "Orders";
    const GROUP_PRODUCTS = "Products";
    const GROUP_PMA = "Project-Management";
    const GROUP_SERVERS = "Servers";
    const GROUP_SERVICE = "Service";
    const GROUP_SUPPORT = "Support";
    const GROUP_SYSTEM = "System";
    const GROUP_TICKETS = "Tickets";
    const GROUP_USERS = "Users";

    public function __construct($data = [])
    {
        if (!empty($data["groups"]) && is_array($data["groups"])) {
            $this->setGroups($data["groups"]);
        }
        if (!empty($data["actions"]) && is_array($data["actions"])) {
            $this->setActions($data["actions"]);
        }
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function setGroups($groups)
    {
        $this->groups = $groups;
        return $this;
    }

    public function getActions()
    {
        return $this->actions;
    }

    public function setActions($actions)
    {
        $this->actions = $actions;
        return $this;
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public function toArray()
    {
        $catalog = static::normalize($this);
        return ["groups" => $catalog->getGroups(), "actions" => $catalog->getActions()];
    }

    public function getGroupedActions()
    {
        $groups = $this->getGroups();
        $actions = $this->getActions();
        uasort($groups, function ($a, $b) {
            return strnatcasecmp($a["name"], $b["name"]);
        });
        uasort($actions, function ($a, $b) {
            return strnatcasecmp($a["name"], $b["name"]);
        });
        foreach ($actions as $key => $data) {
            if (isset($groups[$data["group"]])) {
                $groups[$data["group"]]["actions"][$key] = $data;
            }
        }
        foreach ($groups as $key => $group) {
            if (!isset($group["actions"])) {
                unset($groups[$key]);
            }
        }
        return $groups;
    }

    public static function get()
    {
        $storedApiCatalog = \WHMCS\Config\Setting::getValue(static::SETTING_API_CATALOG);
        if (!empty($storedApiCatalog)) {
            $apiCatalog = json_decode($storedApiCatalog, true);
            if (is_array($apiCatalog) && !empty($apiCatalog)) {
                return new static($apiCatalog);
            }
        }
        return static::defaultCatalog();
    }

    public static function factoryApiRole(Catalog $catalog, $permissionClass = "WHMCS\\Api\\Authorization\\ApiRole")
    {
        $permissions = new $permissionClass();
        if (!$permissions instanceof \WHMCS\Authorization\Contracts\RoleInterface) {
            throw new \InvalidArgumentException("2nd argument to WHMCS\\Api\\V1\\Catalog::factoryApiRole must be a named class which implements WHMCS\\Authorization\\Contracts\\RoleInterface");
        }
        $actions = $catalog->getActions();
        if ($actions) {
            $data = [];
            foreach ($actions as $key => $actionDetails) {
                if (!empty($actionDetails["default"])) {
                    $data[] = $key;
                }
            }
            $permissions->allow($data);
        }
        return $permissions;
    }

    public static function add($actions = [], $groups = [])
    {
        $storedCatalog = static::get();
        $storedGroups = $storedCatalog->getGroups();
        $storedActions = $storedCatalog->getActions();
        foreach ($groups as $group => $data) {
            if (!array_key_exists($group, $storedGroups)) {
                $storedGroups[$group] = $data;
            } else {
                $storedGroups[$group] = array_merge($storedGroups[$group], $data);
            }
        }
        foreach ($actions as $action => $data) {
            if (!array_key_exists($action, $storedGroups)) {
                $storedActions[$action] = $data;
            } else {
                $storedActions[$action] = array_merge($storedActions[$action], $data);
            }
        }
        $updatedCatalog = new static(["groups" => $storedGroups, "actions" => $storedActions]);
        static::store($updatedCatalog);
        return $updatedCatalog;
    }

    public static function store(Catalog $catalog = NULL)
    {
        if (is_null($catalog)) {
            $data = "{}";
        } else {
            $data = $catalog->toJson();
        }
        \WHMCS\Config\Setting::setValue(static::SETTING_API_CATALOG, $data);
    }

    public static function normalize(Catalog $catalog)
    {
        $defaultCatalog = static::defaultCatalog();
        $doubleCheckGroups = $defaultCatalog->getGroups();
        $groupToNormailize = [];
        if (!$catalog->getActions()) {
            $catalog->setActions($defaultCatalog->getActions());
        } else {
            $actions = $catalog->getActions();
            foreach ($actions as $key => $data) {
                $normalizedData = ["group" => static::GROUP_CUSTOM, "name" => ucfirst($key), "default" => 0];
                if (is_array($data)) {
                    if (!empty($data["group"]) && is_string($data["group"])) {
                        $normalizedData["group"] = $data["group"];
                        $doubleCheckGroups[] = $data["group"];
                    }
                    if (!empty($data["name"]) && is_string($data["name"])) {
                        $normalizedData["name"] = $data["name"];
                    }
                    if (isset($data["default"]) && (int) $data["default"] === 1) {
                        $normalizedData["default"] = 1;
                    }
                }
                $actions[$key] = $normalizedData;
            }
            $catalog->setActions($actions);
        }
        if (!$catalog->getGroups()) {
            $catalog->setGroups($defaultCatalog->getGroups());
        } else {
            $groups = $catalog->getGroups();
            foreach ($groups as $key => $data) {
                $normalizedData = ["name" => ucfirst($key)];
                if (is_array($data) && !empty($data["name"]) && is_string($data["name"])) {
                    $normalizedData["name"] = $data["name"];
                }
                $groups[$key] = $normalizedData;
            }
            $catalog->setGroups($groups);
        }
        return $catalog;
    }

    public static function defaultCatalog()
    {
        $defaults = ["groups" => [static::GROUP_ADDONS => ["name" => static::GROUP_ADDONS], static::GROUP_AFFILIATES => ["name" => static::GROUP_AFFILIATES], static::GROUP_AUTHENTICATION => ["name" => static::GROUP_AUTHENTICATION], static::GROUP_BILLING => ["name" => static::GROUP_BILLING], static::GROUP_CLIENT => ["name" => static::GROUP_CLIENT], static::GROUP_CUSTOM => ["name" => static::GROUP_CUSTOM], static::GROUP_DOMAINS => ["name" => static::GROUP_DOMAINS], static::GROUP_MODULE => ["name" => static::GROUP_MODULE], static::GROUP_ORDERS => ["name" => static::GROUP_ORDERS], static::GROUP_PRODUCTS => ["name" => static::GROUP_PRODUCTS], static::GROUP_PMA => ["name" => str_replace("-", " ", static::GROUP_PMA)], static::GROUP_SERVERS => ["name" => static::GROUP_SERVERS], static::GROUP_SERVICE => ["name" => static::GROUP_SERVICE], static::GROUP_SUPPORT => ["name" => static::GROUP_SUPPORT], static::GROUP_TICKETS => ["name" => static::GROUP_TICKETS], static::GROUP_USERS => ["name" => static::GROUP_USERS], static::GROUP_SYSTEM => ["name" => static::GROUP_SYSTEM]], "actions" => ["acceptorder" => ["group" => static::GROUP_ORDERS, "name" => "AcceptOrder", "default" => 0], "acceptquote" => ["group" => static::GROUP_BILLING, "name" => "AcceptQuote", "default" => 0], "activatemodule" => ["group" => static::GROUP_MODULE, "name" => "ActivateModule", "default" => 0], "addannouncement" => ["group" => static::GROUP_SUPPORT, "name" => "AddAnnouncement", "default" => 0], "addbannedip" => ["group" => static::GROUP_SYSTEM, "name" => "AddBannedIp", "default" => 0], "addbillableitem" => ["group" => static::GROUP_BILLING, "name" => "AddBillableItem", "default" => 0], "addcancelrequest" => ["group" => static::GROUP_SUPPORT, "name" => "AddCancelRequest", "default" => 0], "addclient" => ["group" => static::GROUP_CLIENT, "name" => "AddClient", "default" => 0], "addclientnote" => ["group" => static::GROUP_SUPPORT, "name" => "AddClientNote", "default" => 0], "addcontact" => ["group" => static::GROUP_CLIENT, "name" => "AddContact", "default" => 0], "addcredit" => ["group" => static::GROUP_BILLING, "name" => "AddCredit", "default" => 0], "addinvoicepayment" => ["group" => static::GROUP_BILLING, "name" => "AddInvoicePayment", "default" => 0], "addorder" => ["group" => static::GROUP_ORDERS, "name" => "AddOrder", "default" => 0], "addpaymethod" => ["group" => static::GROUP_BILLING, "name" => "AddPayMethod", "default" => 0], "addproduct" => ["group" => static::GROUP_PRODUCTS, "name" => "AddProduct", "default" => 0], "addprojectmessage" => ["group" => static::GROUP_PMA, "name" => "AddProjectMessage", "default" => 0], "addprojecttask" => ["group" => static::GROUP_PMA, "name" => "AddProjectTask", "default" => 0], "addticketnote" => ["group" => static::GROUP_TICKETS, "name" => "AddTicketNote", "default" => 0], "addticketreply" => ["group" => static::GROUP_TICKETS, "name" => "AddTicketReply", "default" => 0], "addtransaction" => ["group" => static::GROUP_BILLING, "name" => "AddTransaction", "default" => 0], "adduser" => ["group" => static::GROUP_USERS, "name" => "AddUser", "default" => 0], "affiliateactivate" => ["group" => static::GROUP_AFFILIATES, "name" => "AffiliateActivate", "default" => 0], "blockticketsender" => ["group" => static::GROUP_TICKETS, "name" => "BlockTicketSender", "default" => 0], "applycredit" => ["group" => static::GROUP_BILLING, "name" => "ApplyCredit", "default" => 0], "cancelorder" => ["group" => static::GROUP_ORDERS, "name" => "CancelOrder", "default" => 0], "capturepayment" => ["group" => static::GROUP_BILLING, "name" => "CapturePayment", "default" => 0], "closeclient" => ["group" => static::GROUP_CLIENT, "name" => "CloseClient", "default" => 0], "createclientinvite" => ["group" => static::GROUP_USERS, "name" => "CreateClientInvite", "default" => 0], "createssotoken" => ["group" => static::GROUP_AUTHENTICATION, "name" => "CreateSsoToken", "default" => 0], "createinvoice" => ["group" => static::GROUP_BILLING, "name" => "CreateInvoice", "default" => 0], "createoauthcredential" => ["group" => static::GROUP_AUTHENTICATION, "name" => "CreateOAuthCredential", "default" => 0], "createorupdatetld" => ["group" => static::GROUP_DOMAINS, "name" => "CreateOrUpdateTLD", "default" => 0], "createproject" => ["group" => static::GROUP_PMA, "name" => "CreateProject", "default" => 0], "createquote" => ["group" => static::GROUP_BILLING, "name" => "CreateQuote", "default" => 0], "deactivatemodule" => ["group" => static::GROUP_MODULE, "name" => "DeactivateModule", "default" => 0], "decryptpassword" => ["group" => static::GROUP_SYSTEM, "name" => "DecryptPassword", "default" => 0], "deleteannouncement" => ["group" => static::GROUP_SUPPORT, "name" => "DeleteAnnouncement", "default" => 0], "deleteclient" => ["group" => static::GROUP_CLIENT, "name" => "DeleteClient", "default" => 0], "deletecontact" => ["group" => static::GROUP_CLIENT, "name" => "DeleteContact", "default" => 0], "deleteoauthcredential" => ["group" => static::GROUP_AUTHENTICATION, "name" => "DeleteOAuthCredential", "default" => 0], "deleteorder" => ["group" => static::GROUP_ORDERS, "name" => "DeleteOrder", "default" => 0], "deletepaymethod" => ["group" => static::GROUP_BILLING, "name" => "DeletePayMethod", "default" => 0], "deleteprojecttask" => ["group" => static::GROUP_PMA, "name" => "DeleteProjectTask", "default" => 0], "deletequote" => ["group" => static::GROUP_BILLING, "name" => "DeleteQuote", "default" => 0], "deleteticket" => ["group" => static::GROUP_TICKETS, "name" => "DeleteTicket", "default" => 0], "deleteticketnote" => ["group" => static::GROUP_TICKETS, "name" => "DeleteTicketNote", "default" => 0], "deleteticketreply" => ["group" => static::GROUP_TICKETS, "name" => "DeleteTicketReply", "default" => 0], "deleteuserclient" => ["group" => static::GROUP_USERS, "name" => "DeleteUserClient", "default" => 0], "domaingetlockingstatus" => ["group" => static::GROUP_DOMAINS, "name" => "DomainGetLockingStatus", "default" => 0], "domaingetnameservers" => ["group" => static::GROUP_DOMAINS, "name" => "DomainGetNameservers", "default" => 0], "domaingetwhoisinfo" => ["group" => static::GROUP_DOMAINS, "name" => "DomainGetWhoisInfo", "default" => 0], "domainregister" => ["group" => static::GROUP_DOMAINS, "name" => "DomainRegister", "default" => 0], "domainrelease" => ["group" => static::GROUP_DOMAINS, "name" => "DomainRelease", "default" => 0], "domainrenew" => ["group" => static::GROUP_DOMAINS, "name" => "DomainRenew", "default" => 0], "domainrequestepp" => ["group" => static::GROUP_DOMAINS, "name" => "DomainRequestEPP", "default" => 0], "domaintoggleidprotect" => ["group" => static::GROUP_DOMAINS, "name" => "DomainToggleIdProtect", "default" => 0], "domaintransfer" => ["group" => static::GROUP_DOMAINS, "name" => "DomainTransfer", "default" => 0], "domainupdatelockingstatus" => ["group" => static::GROUP_DOMAINS, "name" => "DomainUpdateLockingStatus", "default" => 0], "domainupdatenameservers" => ["group" => static::GROUP_DOMAINS, "name" => "DomainUpdateNameservers", "default" => 0], "domainupdatewhoisinfo" => ["group" => static::GROUP_DOMAINS, "name" => "DomainUpdateWhoisInfo", "default" => 0], "domainwhois" => ["group" => static::GROUP_DOMAINS, "name" => "DomainWhois", "default" => 0], "encryptpassword" => ["group" => static::GROUP_SYSTEM, "name" => "EncryptPassword", "default" => 0], "endtasktimer" => ["group" => static::GROUP_PMA, "name" => "EndTaskTimer", "default" => 0], "fraudorder" => ["group" => static::GROUP_ORDERS, "name" => "FraudOrder", "default" => 0], "geninvoices" => ["group" => static::GROUP_BILLING, "name" => "GenInvoices", "default" => 0], "getactivitylog" => ["group" => static::GROUP_SYSTEM, "name" => "GetActivityLog", "default" => 0], "getadmindetails" => ["group" => static::GROUP_SYSTEM, "name" => "GetAdminDetails", "default" => 0], "getadminusers" => ["group" => static::GROUP_SYSTEM, "name" => "GetAdminUsers", "default" => 0], "getaffiliates" => ["group" => static::GROUP_AFFILIATES, "name" => "GetAffiliates", "default" => 0], "getannouncements" => ["group" => static::GROUP_SUPPORT, "name" => "GetAnnouncements", "default" => 0], "getautomationlog" => ["group" => static::GROUP_SYSTEM, "name" => "GetAutomationLog", "default" => 0], "getcancelledpackages" => ["group" => static::GROUP_SUPPORT, "name" => "GetCancelledPackages", "default" => 0], "getclientgroups" => ["group" => static::GROUP_CLIENT, "name" => "GetClientGroups", "default" => 0], "getclientpassword" => ["group" => static::GROUP_CLIENT, "name" => "GetClientPassword", "default" => 0], "getclients" => ["group" => static::GROUP_CLIENT, "name" => "GetClients", "default" => 0], "getclientsaddons" => ["group" => static::GROUP_CLIENT, "name" => "GetClientsAddons", "default" => 0], "getclientsdetails" => ["group" => static::GROUP_CLIENT, "name" => "GetClientsDetails", "default" => 0], "getclientsdomains" => ["group" => static::GROUP_CLIENT, "name" => "GetClientsDomains", "default" => 0], "getclientsproducts" => ["group" => static::GROUP_CLIENT, "name" => "GetClientsProducts", "default" => 0], "getconfigurationvalue" => ["group" => static::GROUP_SYSTEM, "name" => "GetConfigurationValue", "default" => 0], "getcontacts" => ["group" => static::GROUP_CLIENT, "name" => "GetContacts", "default" => 0], "getcredits" => ["group" => static::GROUP_BILLING, "name" => "GetCredits", "default" => 0], "getcurrencies" => ["group" => static::GROUP_SYSTEM, "name" => "GetCurrencies", "default" => 0], "getemails" => ["group" => static::GROUP_CLIENT, "name" => "GetEmails", "default" => 0], "getemailtemplates" => ["group" => static::GROUP_SYSTEM, "name" => "GetEmailTemplates", "default" => 0], "gethealthstatus" => ["group" => static::GROUP_SERVERS, "name" => "GetHealthStatus", "default" => 0], "getinvoice" => ["group" => static::GROUP_BILLING, "name" => "GetInvoice", "default" => 0], "getinvoices" => ["group" => static::GROUP_BILLING, "name" => "GetInvoices", "default" => 0], "getmoduleconfigurationparameters" => ["group" => static::GROUP_MODULE, "name" => "GetModuleConfigurationParameters", "default" => 0], "getmodulequeue" => ["group" => static::GROUP_MODULE, "name" => "GetModuleQueue", "default" => 0], "getorders" => ["group" => static::GROUP_ORDERS, "name" => "GetOrders", "default" => 0], "getorderstatuses" => ["group" => static::GROUP_ORDERS, "name" => "GetOrderStatuses", "default" => 0], "getpaymentmethods" => ["group" => static::GROUP_SYSTEM, "name" => "GetPaymentMethods", "default" => 0], "getpaymethods" => ["group" => static::GROUP_BILLING, "name" => "GetPayMethods", "default" => 0], "getpermissionslist" => ["group" => static::GROUP_USERS, "name" => "GetPermissionsList", "default" => 0], "getproducts" => ["group" => static::GROUP_PRODUCTS, "name" => "GetProducts", "default" => 0], "getproject" => ["group" => static::GROUP_PMA, "name" => "GetProject", "default" => 0], "getprojects" => ["group" => static::GROUP_PMA, "name" => "GetProjects", "default" => 0], "getpromotions" => ["group" => static::GROUP_ORDERS, "name" => "GetPromotions", "default" => 0], "getquotes" => ["group" => static::GROUP_BILLING, "name" => "GetQuotes", "default" => 0], "getregistrars" => ["group" => static::GROUP_DOMAINS, "name" => "GetRegistrars", "default" => 0], "getservers" => ["group" => static::GROUP_SERVERS, "name" => "GetServers", "default" => 0], "getstaffonline" => ["group" => static::GROUP_SYSTEM, "name" => "GetStaffOnline", "default" => 0], "getstats" => ["group" => static::GROUP_SYSTEM, "name" => "GetStats", "default" => 0], "getsupportdepartments" => ["group" => static::GROUP_SUPPORT, "name" => "GetSupportDepartments", "default" => 0], "getsupportstatuses" => ["group" => static::GROUP_SUPPORT, "name" => "GetSupportStatuses", "default" => 0], "getticket" => ["group" => static::GROUP_TICKETS, "name" => "GetTicket", "default" => 0], "getticketattachment" => ["group" => static::GROUP_TICKETS, "name" => "GetTicketAttachment", "default" => 0], "getticketcounts" => ["group" => static::GROUP_TICKETS, "name" => "GetTicketCounts", "default" => 0], "getticketnotes" => ["group" => static::GROUP_TICKETS, "name" => "GetTicketNotes", "default" => 0], "getticketpredefinedcats" => ["group" => static::GROUP_TICKETS, "name" => "GetTicketPredefinedCats", "default" => 0], "getticketpredefinedreplies" => ["group" => static::GROUP_TICKETS, "name" => "GetTicketPredefinedReplies", "default" => 0], "gettickets" => ["group" => static::GROUP_TICKETS, "name" => "GetTickets", "default" => 0], "gettldpricing" => ["group" => static::GROUP_DOMAINS, "name" => "GetTLDPricing", "default" => 0], "gettodoitems" => ["group" => static::GROUP_SYSTEM, "name" => "GetToDoItems", "default" => 0], "gettodoitemstatuses" => ["group" => static::GROUP_SYSTEM, "name" => "GetToDoItemStatuses", "default" => 0], "gettransactions" => ["group" => static::GROUP_BILLING, "name" => "Gettransactions", "default" => 0], "getuserpermissions" => ["group" => static::GROUP_USERS, "name" => "GetUserPermissions", "default" => 0], "listoauthcredentials" => ["group" => static::GROUP_AUTHENTICATION, "name" => "ListOAuthCredentials", "default" => 0], "logactivity" => ["group" => static::GROUP_SYSTEM, "name" => "LogActivity", "default" => 0], "mergeticket" => ["group" => static::GROUP_TICKETS, "name" => "MergeTicket", "default" => 0], "modulechangepackage" => ["group" => static::GROUP_SERVICE, "name" => "ModuleChangePackage", "default" => 0], "modulechangepw" => ["group" => static::GROUP_SERVICE, "name" => "ModuleChangePw", "default" => 0], "modulecreate" => ["group" => static::GROUP_SERVICE, "name" => "ModuleCreate", "default" => 0], "modulecustom" => ["group" => static::GROUP_SERVICE, "name" => "ModuleCustom", "default" => 0], "modulesuspend" => ["group" => static::GROUP_SERVICE, "name" => "ModuleSuspend", "default" => 0], "moduleterminate" => ["group" => static::GROUP_SERVICE, "name" => "ModuleTerminate", "default" => 0], "moduleunsuspend" => ["group" => static::GROUP_SERVICE, "name" => "ModuleUnsuspend", "default" => 0], "openticket" => ["group" => static::GROUP_TICKETS, "name" => "OpenTicket", "default" => 0], "orderfraudcheck" => ["group" => static::GROUP_ORDERS, "name" => "OrderFraudCheck", "default" => 0], "pendingorder" => ["group" => static::GROUP_ORDERS, "name" => "PendingOrder", "default" => 0], "resetpassword" => ["group" => static::GROUP_USERS, "name" => "ResetPassword", "default" => 0], "sendadminemail" => ["group" => static::GROUP_SYSTEM, "name" => "SendAdminEmail", "default" => 0], "sendemail" => ["group" => static::GROUP_SYSTEM, "name" => "SendEmail", "default" => 0], "sendquote" => ["group" => static::GROUP_BILLING, "name" => "SendQuote", "default" => 0], "setconfigurationvalue" => ["group" => static::GROUP_SYSTEM, "name" => "SetConfigurationValue", "default" => 0], "starttasktimer" => ["group" => static::GROUP_PMA, "name" => "StartTaskTimer", "default" => 0], "triggernotificationevent" => ["group" => static::GROUP_SYSTEM, "name" => "TriggerNotificationEvent", "default" => 0], "updateadminnotes" => ["group" => static::GROUP_SYSTEM, "name" => "UpdateAdminNotes", "default" => 0], "updateannouncement" => ["group" => static::GROUP_SUPPORT, "name" => "UpdateAnnouncement", "default" => 0], "updateclient" => ["group" => static::GROUP_CLIENT, "name" => "UpdateClient", "default" => 0], "updateclientaddon" => ["group" => static::GROUP_ADDONS, "name" => "UpdateClientAddon", "default" => 0], "updateclientdomain" => ["group" => static::GROUP_DOMAINS, "name" => "UpdateClientDomain", "default" => 0], "updateclientproduct" => ["group" => static::GROUP_PRODUCTS, "name" => "UpdateClientProduct", "default" => 0], "updatecontact" => ["group" => static::GROUP_CLIENT, "name" => "UpdateContact", "default" => 0], "updateinvoice" => ["group" => static::GROUP_BILLING, "name" => "UpdateInvoice", "default" => 0], "updatemoduleconfiguration" => ["group" => static::GROUP_SYSTEM, "name" => "UpdateModuleConfiguration", "default" => 0], "updateoauthcredential" => ["group" => static::GROUP_AUTHENTICATION, "name" => "UpdateOAuthCredential", "default" => 0], "updatepaymethod" => ["group" => static::GROUP_BILLING, "name" => "UpdatePayMethod", "default" => 0], "updateproject" => ["group" => static::GROUP_PMA, "name" => "UpdateProject", "default" => 0], "updateprojecttask" => ["group" => static::GROUP_PMA, "name" => "UpdateProjectTask", "default" => 0], "updatequote" => ["group" => static::GROUP_BILLING, "name" => "UpdateQuote", "default" => 0], "updateticket" => ["group" => static::GROUP_TICKETS, "name" => "UpdateTicket", "default" => 0], "updateticketreply" => ["group" => static::GROUP_TICKETS, "name" => "UpdateTicketReply", "default" => 0], "updatetodoitem" => ["group" => static::GROUP_SYSTEM, "name" => "UpdateToDoItem", "default" => 0], "updatetransaction" => ["group" => static::GROUP_BILLING, "name" => "UpdateTransaction", "default" => 0], "updateuser" => ["group" => static::GROUP_USERS, "name" => "UpdateUser", "default" => 0], "updateuserpermissions" => ["group" => static::GROUP_USERS, "name" => "UpdateUserPermissions", "default" => 0], "upgradeproduct" => ["group" => static::GROUP_SERVICE, "name" => "UpgradeProduct", "default" => 0], "validatelogin" => ["group" => static::GROUP_AUTHENTICATION, "name" => "ValidateLogin", "default" => 0], "whmcsdetails" => ["group" => static::GROUP_SYSTEM, "name" => "WhmcsDetails", "default" => 0], "getusers" => ["group" => static::GROUP_USERS, "name" => "GetUsers", "default" => 0]]];
        return new static($defaults);
    }
}
