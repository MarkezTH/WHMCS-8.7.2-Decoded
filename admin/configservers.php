<?php

define("ADMINAREA", true);
require "../init.php";
$action = APP::getFromRequest("action");
$accesshash = App::getFromRequest("accesshash");
if ($action == "singlesignon" && checkPermission("WHMCSConnect", true)) {
    $aInt = new WHMCS\Admin("WHMCSConnect");
    if ($whmcs->get_req_var("error")) {
        if (WHMCS\Session::get("ServerModuleCallError")) {
            echo WHMCS\View\Helper::applicationError(AdminLang::trans("global.erroroccurred"), WHMCS\Session::get("ServerModuleCallError"));
        } else {
            echo WHMCS\View\Helper::applicationError(AdminLang::trans("global.erroroccurred"));
        }
        throw new WHMCS\Exception\ProgramExit();
    }
} else {
    $aInt = new WHMCS\Admin("Configure Servers");
}
$aInt->title = "Servers";
$aInt->sidebar = "config";
$aInt->icon = "servers";
$aInt->helplink = "Servers";
$action = $whmcs->get_req_var("action");
$id = (int) $whmcs->get_req_var("id");
$errorMsg = "";
if ($action == "getmoduleinfo") {
    check_token("WHMCS.admin.default");
    $moduleName = $whmcs->get_req_var("type");
    $moduleInfo = getmoduleinfo($moduleName);
    $aInt->jsonResponse($moduleInfo);
}
if ($action == "testconnection") {
    check_token("WHMCS.admin.default");
    $moduleName = $whmcs->get_req_var("type");
    $moduleInterface = new WHMCS\Module\Server();
    if (!$moduleInterface->load($moduleName)) {
        throw new WHMCS\Exception\ProgramExit("Invalid Server Module Type");
    }
    $response = ["success" => false, "autoPopulateValues" => [], "growlTitle" => AdminLang::trans("global.erroroccurred"), "errorMsg" => AdminLang::trans("configservers.testconnectionnotsupported")];
    if ($moduleInterface->functionExists("TestConnection")) {
        $passwordToTest = WHMCS\Input\Sanitize::decode($whmcs->get_req_var("password"));
        $serverId = $whmcs->get_req_var("serverid");
        if ($serverId) {
            $storedPassword = get_query_val("tblservers", "password", ["id" => $serverId]);
            $storedPassword = decrypt($storedPassword);
            if (!hasMaskedPasswordChanged($passwordToTest, $storedPassword)) {
                $passwordToTest = $storedPassword;
            }
        }
        $server = new WHMCS\Product\Server();
        try {
            $hostAddress = new WHMCS\Filter\HostAddress(App::getFromRequest("hostname"), App::getFromRequest("ipaddress"), App::getFromRequest("port"));
            $server->ipAddress = $hostAddress->getIpAddress();
            $server->hostname = $hostAddress->getHostname();
            $server->port = $hostAddress->getPort();
            $server->username = $whmcs->get_req_var("username");
            $server->password = encrypt($passwordToTest);
            $server->accessHash = $whmcs->get_req_var("accesshash");
            $server->secure = $whmcs->get_req_var("secure");
            $params = $moduleInterface->getServerParams($server);
            $connectionTestResult = $moduleInterface->call("TestConnection", $params);
            $isSuccess = array_key_exists("success", $connectionTestResult) && $connectionTestResult["success"];
            $errorMsg = "";
            if ($isSuccess) {
                $response["success"] = true;
                $response["errorMsg"] = "";
                $response["growlTitle"] = AdminLang::trans("global.success");
                $response["growlMessage"] = AdminLang::trans("configservers.testconnectionsuccess");
                $response["autoPopulateValues"] = $moduleInterface->call("AutoPopulateServerConfig", $params);
            } else {
                $errorMsg = array_key_exists("error", $connectionTestResult) && is_string($connectionTestResult["error"]) ? $connectionTestResult["error"] : $aInt->lang("configservers", "testconnectionunknownerror");
                throw new Exception($errorMsg);
            }
        } catch (WHMCS\Exception\Validation\InvalidPort $e) {
            $response["errorMsg"] = AdminLang::trans("validation.regex", [":attribute" => AdminLang::trans("fields.port")]);
        } catch (WHMCS\Exception\Validation\InvalidHostAddress $e) {
            $response["errorMsg"] = AdminLang::trans("validation.regex", [":attribute" => AdminLang::trans("fields.hostnameOrIp")]);
        } catch (Exception $e) {
            $response["errorMsg"] = $e->getMessage();
        }
    }
    $aInt->jsonResponse($response);
}
if ($action == "singlesignon") {
    check_token("WHMCS.admin.default");
    $serverId = (int) $whmcs->get_req_var("serverid");
    $server = Illuminate\Database\Capsule\Manager::table("tblservers")->find($serverId);
    $allowedRoleIds = Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->where("server_id", "=", $serverId)->pluck("role_id")->all();
    if (count($allowedRoleIds) == 0) {
        $allowAccess = true;
    } else {
        $allowAccess = false;
        $adminAuth = new WHMCS\Auth();
        $adminAuth->getInfobyID(WHMCS\Session::get("adminid"));
        $adminRoleId = $adminAuth->getAdminRoleId();
        if (in_array($adminRoleId, $allowedRoleIds)) {
            $allowAccess = true;
        }
    }
    if (!$allowAccess) {
        $session = new WHMCS\Session();
        $session->create($whmcs->getWHMCSInstanceID());
        logAdminActivity("Single Sign-on Access Denied: '" . $server->name . "' - Server ID: " . $serverId);
        WHMCS\Session::set("ServerModuleCallError", "You do not have permisson to sign-in to this server. If you feel this message to be an error, please contact the system administrator.");
        redir("action=singlesignon&error=1");
    }
    try {
        $moduleInterface = new WHMCS\Module\Server();
        $redirectUrl = $moduleInterface->getSingleSignOnUrlForAdmin($serverId);
        logAdminActivity("Single Sign-on Completed: '" . $server->name . "' - Server ID: " . $serverId);
    } catch (WHMCS\Exception\Module\SingleSignOnError $e) {
        $session = new WHMCS\Session();
        $session->create($whmcs->getWHMCSInstanceID());
        WHMCS\Session::set("ServerModuleCallError", $e->getMessage());
        redir("action=singlesignon&error=1");
    } catch (Exception $e) {
        logActivity("Single Sign-On Request Failed with a Fatal Error: " . $e->getMessage());
        $session = new WHMCS\Session();
        $session->create($whmcs->getWHMCSInstanceID());
        WHMCS\Session::set("ServerModuleCallError", "A fatal error occurred. Please see activity log for more details.");
        redir("action=singlesignon&error=1");
    }
    header("Location: " . $redirectUrl);
    throw new WHMCS\Exception\ProgramExit();
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    $server = WHMCS\Product\Server::find($id);
    if (0 < $server->activeAccountsCount) {
        redir("deleteerror=true");
    } else {
        run_hook("ServerDelete", ["serverid" => $id]);
        logAdminActivity("Server Deleted: '" . $server->name . "' - Server ID: " . $id);
        $server->groups()->detach();
        $server->delete();
        redir("deletesuccess=true");
    }
}
if ($action == "deletegroup") {
    check_token("WHMCS.admin.default");
    $serverGroup = Illuminate\Database\Capsule\Manager::table("tblservergroups")->find($id);
    logAdminActivity("Server Group Deleted: '" . $serverGroup->name . "' - Server Group ID: " . $id);
    delete_query("tblservergroups", ["id" => $id]);
    delete_query("tblservergroupsrel", ["serverid" => $id]);
    redir("deletegroupsuccess=true");
}
if ($action == "save") {
    check_token("WHMCS.admin.default");
    $hostAddress = NULL;
    try {
        $hostAddress = new WHMCS\Filter\HostAddress(App::getFromRequest("hostname"), App::getFromRequest("ipaddress"), App::getFromRequest("port"));
    } catch (WHMCS\Exception\Validation\InvalidPort $e) {
        $errorMsg = AdminLang::trans("validation.regex", [":attribute" => AdminLang::trans("fields.port")]);
    } catch (WHMCS\Exception\Validation\InvalidHostname $e) {
        $errorMsg = AdminLang::trans("validation.regex", [":attribute" => AdminLang::trans("fields.hostname")]);
    } catch (WHMCS\Exception\Validation\InvalidIpAddress $e) {
        $errorMsg = AdminLang::trans("validation.regex", [":attribute" => AdminLang::trans("fields.ipaddress")]);
    } catch (WHMCS\Exception\Validation\InvalidHostAddress $e) {
        $errorMsg = AdminLang::trans("validation.regex", [":attribute" => AdminLang::trans("fields.hostnameOrIp")]);
    }
    $id = $whmcs->get_req_var("serverid");
    $name = $whmcs->get_req_var("name");
    $hostname = $hostAddress ? $hostAddress->getHostname() : App::getFromRequest("hostname");
    $ipaddress = $hostAddress ? $hostAddress->getIpAddress() : App::getFromRequest("ipaddress");
    $port = $hostAddress ? $hostAddress->getPort() : App::getFromRequest("port");
    $assignedips = $whmcs->get_req_var("assignedips");
    $monthlycost = (double) $whmcs->get_req_var("monthlycost");
    $noc = $whmcs->get_req_var("noc");
    $maxaccounts = (int) $whmcs->get_req_var("maxaccounts");
    $statusaddress = $whmcs->get_req_var("statusaddress");
    $disabled = (int) (bool) $whmcs->get_req_var("disabled");
    $nameserver1 = $whmcs->get_req_var("nameserver1");
    $nameserver1ip = $whmcs->get_req_var("nameserver1ip");
    $nameserver2 = $whmcs->get_req_var("nameserver2");
    $nameserver2ip = $whmcs->get_req_var("nameserver2ip");
    $nameserver3 = $whmcs->get_req_var("nameserver3");
    $nameserver3ip = $whmcs->get_req_var("nameserver3ip");
    $nameserver4 = $whmcs->get_req_var("nameserver4");
    $nameserver4ip = $whmcs->get_req_var("nameserver4ip");
    $nameserver5 = $whmcs->get_req_var("nameserver5");
    $nameserver5ip = $whmcs->get_req_var("nameserver5ip");
    $type = $whmcs->get_req_var("type");
    $username = $whmcs->get_req_var("username");
    $password = $whmcs->get_req_var("password");
    $accesshash = $whmcs->get_req_var("accesshash");
    $secure = $whmcs->get_req_var("secure");
    $restrictsso = (int) $whmcs->get_req_var("restrictsso");
    $moduleInfo = getmoduleinfo($type);
    $defaultPort = $moduleInfo["default" . ($secure ? "" : "non") . "sslport"];
    if (!$port || $port == $defaultPort) {
        $port = "NULL";
    }
    if (!$errorMsg) {
        if ($id) {
            $changes = [];
            $server = Illuminate\Database\Capsule\Manager::table("tblservers")->find($id);
            $active = $type == $server->type ? $server->active : "";
            if ($name != $server->name) {
                $changes[] = "Name Modified: '" . $server->name . "' to '" . $name . "'";
            }
            if ($hostname != $server->hostname) {
                $changes[] = "Hostname Modified: '" . $server->hostname . "' to '" . $hostname . "'";
            }
            if ($ipaddress != $server->ipaddress) {
                $changes[] = "IP Address Modified: '" . $server->ipaddress . "' to '" . $ipaddress . "'";
            }
            if ($assignedips != $server->assignedips) {
                $changes[] = "Assigned IP Addresses Modified";
            }
            if ($monthlycost != $server->monthlycost) {
                $changes[] = "Monthly Cost Modified: '" . $server->monthlycost . "' to '" . $monthlycost . "'";
            }
            if ($noc != $server->noc) {
                $changes[] = "Datacenter/NOC Modified: '" . $server->noc . "' to '" . $noc . "'";
            }
            if ($maxaccounts != $server->maxaccounts) {
                $changes[] = "Maximum No. of Accounts Modified: '" . $server->maxaccounts . "' to '" . $maxaccounts . "'";
            }
            if ($statusaddress != $server->statusaddress) {
                $changes[] = "Server Status Address Modified: '" . $server->statusaddress . "' to '" . $statusaddress . "'";
            }
            if ($disabled != $server->disabled) {
                if ($disabled) {
                    $changes[] = "Server Disabled";
                } else {
                    $changes[] = "Server Enabled";
                }
            }
            if ($nameserver1 != $server->nameserver1) {
                $changes[] = "Primary Nameserver Modified: '" . $server->nameserver1 . "' to '" . $nameserver1 . "'";
            }
            if ($nameserver1ip != $server->nameserver1ip) {
                $changes[] = "Primary Nameserver IP Modified: '" . $server->nameserver1ip . "' to '" . $nameserver1ip . "'";
            }
            if ($nameserver2 != $server->nameserver2) {
                $changes[] = "Secondary Nameserver Modified: '" . $server->nameserver2 . "' to '" . $nameserver2 . "'";
            }
            if ($nameserver2ip != $server->nameserver2ip) {
                $changes[] = "Secondary Nameserver IP Modified: '" . $server->nameserver2ip . "' to '" . $nameserver2ip . "'";
            }
            if ($nameserver3 != $server->nameserver3) {
                $changes[] = "Third Nameserver Modified: '" . $server->nameserver3 . "' to '" . $nameserver3 . "'";
            }
            if ($nameserver3ip != $server->nameserver3ip) {
                $changes[] = "Third Nameserver IP Modified: '" . $server->nameserver3ip . "' to '" . $nameserver3ip . "'";
            }
            if ($nameserver4 != $server->nameserver4) {
                $changes[] = "Fourth Nameserver Modified: '" . $server->nameserver4 . "' to '" . $nameserver4 . "'";
            }
            if ($nameserver4ip != $server->nameserver4ip) {
                $changes[] = "Fourth Nameserver IP Modified: '" . $server->nameserver4ip . "' to '" . $nameserver4ip . "'";
            }
            if ($nameserver5 != $server->nameserver5) {
                $changes[] = "Fifth Nameserver Modified: '" . $server->nameserver5 . "' to '" . $nameserver5 . "'";
            }
            if ($nameserver5ip != $server->nameserver5ip) {
                $changes[] = "Fifth Nameserver IP Modified: '" . $server->nameserver5ip . "' to '" . $nameserver5ip . "'";
            }
            if ($type != $server->type) {
                $changes[] = "Type Modified: '" . $server->type . "' to '" . $type . "'";
            }
            if ($username != $server->username) {
                $changes[] = "Username Modified: '" . $server->username . "' to '" . $username . "'";
            }
            if ($accesshash != $server->accesshash) {
                $changes[] = "Access Hash Modified";
            }
            if ($secure != $server->secure) {
                if ($secure) {
                    $changes[] = "Secure Connection Enabled";
                } else {
                    $changes[] = "Secure Connection Disabled";
                }
            }
            if ($port != $server->port && $port != "NULL") {
                $changes[] = "Port Modified: '" . $server->port . "' to '" . $port . "'";
            }
            $saveData = ["name" => $name, "type" => $type, "ipaddress" => trim($ipaddress), "assignedips" => trim($assignedips), "hostname" => trim($hostname), "monthlycost" => trim($monthlycost), "noc" => $noc, "statusaddress" => trim($statusaddress), "nameserver1" => trim($nameserver1), "nameserver1ip" => trim($nameserver1ip), "nameserver2" => trim($nameserver2), "nameserver2ip" => trim($nameserver2ip), "nameserver3" => trim($nameserver3), "nameserver3ip" => trim($nameserver3ip), "nameserver4" => trim($nameserver4), "nameserver4ip" => trim($nameserver4ip), "nameserver5" => trim($nameserver5), "nameserver5ip" => trim($nameserver5ip), "maxaccounts" => trim($maxaccounts), "username" => trim($username), "accesshash" => trim($accesshash), "secure" => $secure, "port" => $port, "disabled" => $disabled, "active" => $active];
            $newPassword = trim($whmcs->get_req_var("password"));
            $originalPassword = decrypt(get_query_val("tblservers", "password", ["id" => $id]));
            $valueToStore = interpretMaskedPasswordChangeForStorage($newPassword, $originalPassword);
            if ($valueToStore !== false) {
                $saveData["password"] = $valueToStore;
                if ($newPassword != $originalPassword) {
                    $changes[] = "Password Modified";
                }
            }
            update_query("tblservers", $saveData, ["id" => $id]);
            if ($restrictsso) {
                $newSsoRoleRestrictions = $whmcs->get_req_var("restrictssoroles");
                if (!is_array($newSsoRoleRestrictions)) {
                    $newSsoRoleRestrictions = [];
                }
                $adminRoleNames = [];
                $changedPermissions = ["added" => [], "removed" => []];
                $newSsoRoleRestrictions[] = "0";
                $existingAccesses = Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->where("server_id", "=", $id)->get()->all();
                if (!$existingAccesses) {
                    $changes[] = "Access Control Enabled";
                }
                Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->whereNotIn("role_id", $newSsoRoleRestrictions)->where("server_id", "=", $id)->delete();
                $currentSsoRoleRestrictions = Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->where("server_id", "=", $id)->pluck("role_id")->all();
                foreach ($newSsoRoleRestrictions as $roleId) {
                    if (!in_array($roleId, $currentSsoRoleRestrictions)) {
                        if (!isset($adminRoleNames[$roleId])) {
                            $adminRoleNames[$roleId] = Illuminate\Database\Capsule\Manager::table("tbladminroles")->find($roleId, ["name"])->name;
                        }
                        Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->insert(["server_id" => $id, "role_id" => $roleId]);
                        $changedPermissions["added"][] = $adminRoleNames[$roleId];
                    }
                }
                foreach ($existingAccesses as $existingAccess) {
                    if (!in_array($existingAccess->role_id, $newSsoRoleRestrictions)) {
                        if (!isset($adminRoleNames[$existingAccess->role_id])) {
                            $adminRoleNames[$existingAccess->role_id] = Illuminate\Database\Capsule\Manager::table("tbladminroles")->find($existingAccess->role_id, ["name"])->name;
                        }
                        $changedPermissions["removed"][] = $adminRoleNames[$existingAccess->role_id];
                    }
                }
                if ($changedPermissions) {
                    if (array_filter($changedPermissions["added"])) {
                        $changes[] = "Added Access Control Group(s): " . implode(", ", $changedPermissions["added"]);
                    }
                    if (array_filter($changedPermissions["removed"])) {
                        $changes[] = "Removed Access Control Group(s): " . implode(", ", $changedPermissions["removed"]);
                    }
                }
            } else {
                $deletedRows = Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->where("server_id", "=", $id)->delete();
                if ($deletedRows) {
                    $changes[] = "Access Control Disabled";
                }
            }
            if ($changes) {
                logAdminActivity("Server Modified: '" . $name . "' - Changes: " . implode(". ", $changes) . " - Server ID: " . $id);
            }
            run_hook("ServerEdit", ["serverid" => $id]);
            WHMCS\Session::setAndRelease("ServerIdNeedsRefresh", $id);
            redir("savesuccess=true");
        } else {
            try {
                $server = new WHMCS\Admin\Setup\Servers();
                $serverId = $server->add($name, $type, $ipaddress, $assignedips, $hostname, $monthlycost, $noc, $statusaddress, $nameserver1, $nameserver1ip, $nameserver2, $nameserver2ip, $nameserver3, $nameserver3ip, $nameserver4, $nameserver4ip, $nameserver5, $nameserver5ip, $maxaccounts, $username, $password, $accesshash, $secure, $port, $restrictsso ? $restrictssoroles : [], $disabled);
                WHMCS\Session::setAndRelease("ServerIdNeedsRefresh", $serverId);
                redir("createsuccess=true");
            } catch (Exception $e) {
                $action = "manage";
                $errorMsg = $e->getMessage();
            }
        }
    } else {
        $action = "manage";
    }
}
if ($action == "savegroup") {
    check_token("WHMCS.admin.default");
    $name = $whmcs->get_req_var("name");
    $filltype = (int) $whmcs->get_req_var("filltype");
    $selectedservers = $whmcs->get_req_var("selectedservers") ?: [];
    $changes = $serverList = [];
    $serverUpdated = false;
    if ($id) {
        $serverGroup = Illuminate\Database\Capsule\Manager::table("tblservergroups")->find($id);
        if ($name != $serverGroup->name) {
            $changes[] = "Name Modified: '" . $serverGroup->name . "' to '" . $name . "'";
        }
        if ($filltype != $serverGroup->filltype) {
            if ($filltype == 1) {
                $changes[] = "Fill Type Modified: Add to the least full server";
            } else {
                $changes[] = "Fill Type Modified: Fill active server until full then switch to next least used";
            }
        }
        $serverUpdated = true;
        update_query("tblservergroups", ["name" => $name, "filltype" => $filltype], ["id" => $id]);
        $existingServers = Illuminate\Database\Capsule\Manager::table("tblservergroupsrel")->where("groupid", "=", $id)->get()->all();
        foreach ($existingServers as $existingServer) {
            $serverList[] = $existingServer->serverid;
        }
        delete_query("tblservergroupsrel", ["groupid" => $id]);
    } else {
        $id = insert_query("tblservergroups", ["name" => $name, "filltype" => $filltype]);
        logAdminActivity("Server Group Created: '" . $name . "' - Server Group ID: " . $id);
    }
    if ($selectedservers) {
        $allocated = false;
        foreach ($selectedservers as $serverid) {
            insert_query("tblservergroupsrel", ["groupid" => $id, "serverid" => $serverid]);
            if (!in_array($serverid, $serverList) && $allocated === false) {
                $changes[] = "Server(s) Added to Group";
                $allocated = true;
            }
        }
        foreach ($serverList as $serverId) {
            if (!in_array($serverId, $selectedservers)) {
                $changes[] = "Server(s) Removed from Group";
            }
        }
    } else {
        if (!$selectedservers && $serverList) {
            $changes[] = "All Servers Removed from Group";
        }
    }
    if ($serverUpdated && $changes) {
        logAdminActivity("Server Group Modified: '" . $name . "' - Changes: " . implode(". ", $changes) . " - Server Group ID: " . $id);
    }
    redir("savesuccess=1");
}
if ($action == "enable") {
    check_token("WHMCS.admin.default");
    $server = Illuminate\Database\Capsule\Manager::table("tblservers")->find($id);
    if ($server->disabled) {
        logAdminActivity("Server Enabled: '" . $server->name . "' - Server ID: " . $id);
        update_query("tblservers", ["disabled" => "0"], ["id" => $id]);
    }
    redir("enablesuccess=1");
}
if ($action == "disable") {
    check_token("WHMCS.admin.default");
    $server = Illuminate\Database\Capsule\Manager::table("tblservers")->find($id);
    if (!$server->disabled) {
        logAdminActivity("Server Disabled: '" . $server->name . "' - Server ID: " . $id);
        update_query("tblservers", ["disabled" => "1"], ["id" => $id]);
    }
    redir("disablesuccess=1");
}
if ($action == "makedefault") {
    check_token("WHMCS.admin.default");
    $result = select_query("tblservers", "", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $type = $data["type"];
    if (!$data["active"]) {
        logAdminActivity("Server Set to Default for " . $type . ": '" . $data["name"] . "' - Server ID: " . $id);
        update_query("tblservers", ["active" => ""], ["type" => $type]);
        update_query("tblservers", ["active" => "1"], ["id" => $id]);
    }
    redir("makedefault=1");
}
ob_start();
$aInt->addHeadOutput(WHMCS\View\Asset::jsInclude("AdminConfigServersInterface.js?v=" . WHMCS\View\Helper::getAssetVersionHash()));
if ($action == "") {
    $serverIdNeedsRefresh = 0;
    if (isset($createsuccess) && $createsuccess) {
        infoBox(AdminLang::trans("configservers.addedsuccessful"), AdminLang::trans("configservers.addedsuccessfuldesc"));
        $serverIdNeedsRefresh = WHMCS\Session::getAndDelete("ServerIdNeedsRefresh");
    } else {
        if (isset($deletesuccess) && $deletesuccess) {
            infoBox(AdminLang::trans("configservers.delsuccessful"), AdminLang::trans("configservers.delsuccessfuldesc"));
        } else {
            if (isset($deletegroupsuccess) && $deletegroupsuccess) {
                infoBox(AdminLang::trans("configservers.groupdelsuccessful"), AdminLang::trans("configservers.groupdelsuccessfuldesc"));
            } else {
                if (isset($deleteerror) && $deleteerror) {
                    infoBox(AdminLang::trans("configservers.error"), AdminLang::trans("configservers.errordesc"));
                } else {
                    if (isset($savesuccess) && $savesuccess) {
                        infoBox(AdminLang::trans("global.changesuccess"), AdminLang::trans("configservers.changesuccessdesc"));
                        $serverIdNeedsRefresh = WHMCS\Session::getAndDelete("ServerIdNeedsRefresh");
                    } else {
                        if (isset($enablesuccess) && $enablesuccess) {
                            infoBox(AdminLang::trans("configservers.enabled"), AdminLang::trans("configservers.enableddesc"));
                        } else {
                            if (isset($disablesuccess) && $disablesuccess) {
                                infoBox(AdminLang::trans("configservers.disabled"), AdminLang::trans("configservers.disableddesc"));
                            } else {
                                if (isset($makedefault) && $makedefault) {
                                    infoBox(AdminLang::trans("configservers.defaultchange"), AdminLang::trans("configservers.defaultchangedesc"));
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if ($whmcs->get_req_var("error") && WHMCS\Session::get("ServerModuleCallError")) {
        infoBox($aInt->lang("global", "erroroccurred"), WHMCS\Session::get("ServerModuleCallError"));
        WHMCS\Session::delete("ServerModuleCallError");
    }
    echo $infobox;
    $aInt->deleteJSConfirm("doDelete", "configservers", "delserverconfirm", "?action=delete&id=");
    $aInt->deleteJSConfirm("doDeleteGroup", "configservers", "delgroupconfirm", "?action=deletegroup&id=");
    $refreshAll = AdminLang::trans("configservers.refreshAllData");
    echo "\n<p>";
    echo AdminLang::trans("configservers.pagedesc");
    echo "</p>\n\n<p>\n    <div class=\"btn-group\" role=\"group\">\n        <a href=\"";
    echo $whmcs->getPhpSelf();
    echo "?action=manage\" class=\"btn btn-default\">\n            <i class=\"fas fa-plus\"></i> ";
    echo AdminLang::trans("configservers.addnewserver");
    echo "        </a>\n        <a href=\"";
    echo $whmcs->getPhpSelf();
    echo "?action=managegroup\" class=\"btn btn-default\">\n            <i class=\"fas fa-plus-square\"></i> ";
    echo AdminLang::trans("configservers.createnewgroup");
    echo "        </a>\n    </div>\n\n    <div class=\"pull-right\">\n        <a id=\"btnRefreshAllData\" href=\"#\" class=\"btn btn-default\" title=\"";
    echo $refreshAll;
    echo "\" data-toggle=\"tooltip\" data-placement=\"left auto\">\n            <i class=\"fas fa-sync\" aria-hidden=\"true\"></i>\n            <span class=\"sr-only\">";
    echo $refreshAll;
    echo "></span>\n        </a>\n    </div>\n</p>\n\n";
    $adminAuth = new WHMCS\Auth();
    $adminAuth->getInfobyID(WHMCS\Session::get("adminid"));
    $adminRoleId = $adminAuth->getAdminRoleId();
    $server = new WHMCS\Module\Server();
    $modulesarray = $server->getList();
    $aInt->sortableTableInit("nopagination");
    $serversByType = WHMCS\Product\Server::with("remote")->orderBy("type", "ASC")->get()->groupBy("type");
    $refresh = AdminLang::trans("global.refresh");
    foreach ($serversByType as $moduleName => $typeServers) {
        $module = new WHMCS\Module\Server();
        $module->load($moduleName);
        $moduleDisplayName = $module->getDisplayName();
        $tabledata[] = ["dividingline", $moduleDisplayName];
        $disableddata = [];
        $typeServers = $typeServers->sortBy("name");
        foreach ($typeServers as $serverModel) {
            $id = $serverModel->id;
            $name = $serverModel->name;
            $ipaddress = $serverModel->ipAddress;
            $hostname = $serverModel->hostname;
            $maxaccounts = $serverModel->maxAccounts ?: 1;
            $username = $serverModel->username;
            $password = decrypt($serverModel->password);
            $accesshash = $serverModel->accessHash;
            $secure = $serverModel->secure;
            $active = $serverModel->active;
            $type = $serverModel->type;
            $disabled = $serverModel->disabled;
            $active = $active ? "*" : "";
            $numaccounts = $serverModel->activeAccountsCount;
            $percentuse = @round($numaccounts / $maxaccounts * 100, 0);
            $serverModelRemote = $serverModel->remote;
            $serverUsageCount = "<small>" . AdminLang::trans("global.notAvailable") . "</small>";
            if ($serverModelRemote) {
                $usageString = AdminLang::trans("configservers.accounts");
                if (array_key_exists("max_domains", $serverModelRemote->metaData) && 0 < $serverModelRemote->metaData["max_domains"]) {
                    $usageString = AdminLang::trans("configservers.domains");
                }
                $serverUsageCount = $usageString . ": " . $serverModelRemote->numAccounts;
                if (array_key_exists("service_count", $serverModelRemote->metaData)) {
                    $serverUsageCount .= "<br><small>" . AdminLang::trans("configservers.services") . ": " . $serverModelRemote->metaData["service_count"] . "</small>";
                }
            }
            $adminlogincode = "";
            $actionButtons = [];
            $remoteMetaData = "";
            $refreshItem = "";
            if (in_array($type, $modulesarray)) {
                $server->load($type);
                $params = $server->getServerParams($serverModel);
                if ($server->functionExists("AdminSingleSignOn")) {
                    $ssoBtnLabel = $server->getMetaDataValue("AdminSingleSignOnLabel") ?: AdminLang::trans("sso.adminlogin");
                    $ssoRoleRestrictions = Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->where("server_id", "=", $id)->pluck("role_id")->all();
                    $ssoBtnDisabled = 0 < count($ssoRoleRestrictions) && !in_array($adminRoleId, $ssoRoleRestrictions) ? " disabled=\"disabled\"" : "";
                    $ssoBtnClass = 0 < count($ssoRoleRestrictions) && !in_array($adminRoleId, $ssoRoleRestrictions) ? " btn-disabled" : " btn-default";
                    $adminlogincode = "<form action=\"configservers.php\" method=\"post\" target=\"_blank\">\n    <input type=\"hidden\" name=\"action\" value=\"singlesignon\" />\n    <input type=\"hidden\" name=\"serverid\" value=\"" . $id . "\" />\n    <input type=\"submit\" value=\"" . $ssoBtnLabel . "\" " . $ssoBtnDisabled . " class=\"btn btn-sm" . $ssoBtnClass . "\"/>\n</form>";
                } else {
                    if ($server->functionExists("AdminLink")) {
                        $adminlogincode = $server->call("AdminLink", $params);
                        $adminlogincode = str_replace("input type=\"submit\"", "input type=\"submit\" class=\"btn btn-sm btn-default\"", $adminlogincode);
                    }
                }
                if ($server->functionExists("ListAccounts")) {
                    $actionButtons[] = "<form method=\"post\" action=\"" . routePath("admin-utilities-tools-serversync-analyse", $id) . "\"><button type=\"submit\" class=\"btn btn-default btn-sm\">Sync Accounts</button></form>";
                }
                if ($server->functionExists("RenderRemoteMetaData")) {
                    if ($serverModelRemote) {
                        $remoteMetaData = $server->call("RenderRemoteMetaData", ["remoteData" => $serverModelRemote]);
                    }
                    if ($remoteMetaData) {
                        $remoteMetaData = "<div class=\"remote-meta-data\">" . $remoteMetaData . "<br>" . AdminLang::trans("global.lastUpdated") . ": " . $serverModelRemote->updatedAt->diffForHumans() . "</div>";
                    }
                    if (!$remoteMetaData) {
                        $remoteMetaData = "<div class=\"remote-meta-data\"></div>";
                    }
                }
                if ($server->functionExists("GetRemoteMetaData") || $server->functionExists("GetUserCount")) {
                    $classes = "btn btn-xs btn-default refresh-server-item";
                    if ($serverIdNeedsRefresh == $serverModel->id) {
                        $classes .= " force-meta-refresh";
                    }
                    $refreshItem = "<a href=\"#\" class=\"" . $classes . "\" title=\"" . $refresh . "\" data-server-id=\"" . $id . "\" data-toggle=\"tooltip\" data-placement=\"right auto\">\n    <i class=\"fas fa-sync\" aria-hidden=\"true\"></i>\n    <span class=\"sr-only\">" . $refresh . "></span>\n</a>";
                }
                if (empty($adminlogincode)) {
                    $adminlogincode = "-";
                }
            } else {
                $adminlogincode = AdminLang::trans("global.modulefilemissing");
            }
            $token = generate_token("link");
            $deleteText = AdminLang::trans("global.delete");
            $deleteLink = "<a href=\"#\" onclick=\"doDelete('" . $id . "');return false;\" title=\"" . $deleteText . "\">\n    <img src=\"images/delete.gif\" alt=\"" . $deleteText . "\">\n</a>";
            $editText = AdminLang::trans("global.edit");
            $editLink = "<a href=\"?action=manage&id=" . $id . "\" title=\"" . $editText . "\">\n    <img src=\"images/edit.gif\" alt=\"" . $editText . "\">\n</a>";
            if ($disabled) {
                $enableText = AdminLang::trans("configservers.enableserver");
                $enableLink = "<div class=\"text-center\">\n    <a href=\"?action=enable&id=" . $id . $token . "\" title=\"" . $enableText . "\">\n        <img src=\"images/icons/disabled.png\" alt=\"" . $enableText . "\">\n    </a>\n</div>";
                $disableddata[] = ["<i>" . $name . " (" . AdminLang::trans("emailtpls.disabled") . ")</i>", "<i>" . $ipaddress . "</i>", "<div class=\"text-center\"><i>" . $numaccounts . "/" . $maxaccounts . " (" . $percentuse . "%)</i></div>", "<div class=\"server-usage-count text-center\"><i>" . $serverUsageCount . "</i></div>", $adminlogincode, "", $enableLink, $editLink, $deleteLink];
            } else {
                $defaultText = AdminLang::trans("configservers.defaultsignups");
                $defaultLink = "<a href=\"?action=makedefault&id=" . $id . $token . "\" title=\"" . $defaultText . "\">\n    " . $name . "\n</a>\n" . $active . "\n" . $refreshItem;
                $disableText = AdminLang::trans("configservers.disableserverclick");
                $disableLink = "<div class=\"text-center\">\n    <a href=\"?action=disable&id=" . $id . $token . "\" title=\"" . $disableText . "\">\n        <img src=\"images/icons/tick.png\" alt=\"" . $disableText . "\">\n    </a>\n</div>";
                $tabledata[] = [$defaultLink . $remoteMetaData, $ipaddress ? $ipaddress : "-", "<div class=\"text-center\">" . $numaccounts . "/" . $maxaccounts . " (" . $percentuse . "%)</div>", "<div class=\"server-usage-count text-center\">" . $serverUsageCount . "</div>", $adminlogincode, implode(" ", $actionButtons), $disableLink, $editLink, $deleteLink];
            }
        }
        foreach ($disableddata as $data) {
            $tabledata[] = $data;
        }
    }
    echo $aInt->sortableTable([AdminLang::trans("configservers.servername"), AdminLang::trans("fields.ipaddress"), AdminLang::trans("configservers.whmcsUsage"), AdminLang::trans("configservers.remoteUsage"), " ", " ", AdminLang::trans("fields.status"), "", ""], $tabledata);
    echo "\n<h2>";
    echo AdminLang::trans("configservers.groups");
    echo "</h2>\n\n<p>";
    echo AdminLang::trans("configservers.groupsdesc");
    echo "</p>\n\n";
    $tabledata = [];
    $result = select_query("tblservergroups", "", "", "name", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $name = $data["name"];
        $filltype = $data["filltype"];
        if ($filltype == 1) {
            $filltype = $aInt->lang("configservers", "addleast");
        } else {
            if ($filltype == 2) {
                $filltype = $aInt->lang("configservers", "fillactive");
            }
        }
        $servers = "";
        $result2 = select_query("tblservergroupsrel", "tblservers.name", ["groupid" => $id], "name", "ASC", "", "tblservers ON tblservers.id=tblservergroupsrel.serverid");
        while ($data = mysql_fetch_array($result2)) {
            $servers .= $data["name"] . ", ";
        }
        $servers = substr($servers, 0, -2);
        $tabledata[] = [$name, $filltype, $servers, "<a href=\"?action=managegroup&id=" . $id . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>", "<a href=\"#\" onClick=\"doDeleteGroup('" . $id . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
    }
    echo $aInt->sortableTable([$aInt->lang("configservers", "groupname"), $aInt->lang("fields", "filltype"), $aInt->lang("setup", "servers"), "", ""], $tabledata);
} else {
    if ($action == "manage") {
        if ($id) {
            $result = select_query("tblservers", "", ["id" => $id]);
            $data = mysql_fetch_array($result);
            $id = $data["id"];
            $type = $data["type"];
            $name = $data["name"];
            $ipaddress = $data["ipaddress"];
            $assignedips = $data["assignedips"];
            $hostname = $data["hostname"];
            $monthlycost = $data["monthlycost"];
            $noc = $data["noc"];
            $statusaddress = $data["statusaddress"];
            $nameserver1 = $data["nameserver1"];
            $nameserver1ip = $data["nameserver1ip"];
            $nameserver2 = $data["nameserver2"];
            $nameserver2ip = $data["nameserver2ip"];
            $nameserver3 = $data["nameserver3"];
            $nameserver3ip = $data["nameserver3ip"];
            $nameserver4 = $data["nameserver4"];
            $nameserver4ip = $data["nameserver4ip"];
            $nameserver5 = $data["nameserver5"];
            $nameserver5ip = $data["nameserver5ip"];
            $maxaccounts = $data["maxaccounts"];
            $username = $data["username"];
            $password = decrypt($data["password"]);
            $accesshash = $data["accesshash"];
            $secure = $data["secure"];
            $port = $data["port"];
            $active = $data["active"];
            $disabled = $data["disabled"];
            $managetitle = $aInt->lang("configservers", "editserver");
            $isSsoRestricted = Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->where("server_id", "=", $id)->count();
        } else {
            $managetitle = $aInt->lang("configservers", "addserver");
            if (empty($maxaccounts)) {
                $maxaccounts = "200";
            }
            $id = "";
            $type = App::getFromRequest("type");
            $secure = "on";
            $port = "";
        }
        $currentSsoRoleRestrictions = Illuminate\Database\Capsule\Manager::table("tblserversssoperms")->where("server_id", "=", $id)->pluck("role_id")->all();
        $moduleInfo = getmoduleinfo($type);
        $defaultPort = $moduleInfo["default" . ($secure ? "" : "non") . "sslport"];
        $serverModules = [];
        $server = new WHMCS\Module\Server();
        foreach ($server->getList() as $moduleName) {
            $server->load($moduleName);
            if ($server->getMetaDataValue("RequiresServer") !== false) {
                $serverModules[$moduleName] = $server->getDisplayName();
            }
        }
        foreach (["directadmin", "plesk", "cpanel"] as $module) {
            if (array_key_exists($module, $serverModules)) {
                $tempValue = [$module => $serverModules[$module]];
                unset($serverModules[$module]);
                $serverModules = $tempValue + $serverModules;
            }
        }
        $serverModuleDropdownHtml = "";
        foreach ($serverModules as $moduleName => $displayName) {
            $serverModuleDropdownHtml .= "<option value=\"" . $moduleName . "\"" . ($moduleName == $type ? " selected" : "") . ">" . $displayName . "</option>";
            if ($moduleName == "directadmin") {
                $serverModuleDropdownHtml .= "<option value=\"\">---</option>";
            }
        }
        $action = App::getPhpSelf() . "?action=save";
        $class = "";
        echo "<h2>" . $managetitle . "</h2>";
        if ($errorMsg) {
            echo infoBox(AdminLang::trans("global.validationerror"), $errorMsg);
        }
        echo "    <div id=\"newServerWizardConnecting\" class=\"hidden\">\n        <i class=\"fas fa-spinner fa-spin fa-fw\"></i>\n        ";
        echo AdminLang::trans("configservers.testconnectionloading");
        echo "    </div>\n    ";
        if (!$id && !$errorMsg) {
            echo "        <div id=\"preAddForm\" class=\"admin-tabs-v2 constrained-width\">\n            <div class=\"row margin-top-bottom-20\">\n                <div class=\"col-sm-offset-6\">\n                    <button id=\"advServerAdd\" class=\"btn btn-info btn-xs\">";
            echo AdminLang::trans("configservers.newWizardIntro");
            echo "</button>\n                </div>\n            </div>\n            <form class=\"form-horizontal\">\n                <div class=\"form-group\">\n                    <label for=\"addType\" class=\"col-lg-3 col-sm-4 control-label\">\n                        ";
            echo AdminLang::trans("fields.module");
            echo "<br>\n                        <small>\n                            ";
            echo AdminLang::trans("configservers.moduleDescription");
            echo "                        </small>\n                    </label>\n                    <div class=\"col-lg-4 col-sm-4\">\n                        <select id=\"addType\"\n                                class=\"form-control select-inline\"\n                                data-related-id=\"inputServerType\"\n                        >\n                            ";
            echo $serverModuleDropdownHtml;
            echo "                        </select>\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"addHostname\" class=\"col-lg-3 col-sm-4 control-label\">\n                        ";
            echo AdminLang::trans("fields.hostnameOrIp");
            echo "<br>\n                        <small>\n                            ";
            echo AdminLang::trans("configservers.hostnameDescription");
            echo "                        </small>\n                    </label>\n                    <div class=\"col-lg-4 col-sm-4\">\n                        <input id=\"addHostname\"\n                               type=\"text\"\n                               class=\"form-control input-400\"\n                               data-related-id=\"inputHostname\"\n                               autofocus\n                        >\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"addUsername\" class=\"col-lg-3 col-sm-4 control-label\">\n                        ";
            echo AdminLang::trans("fields.username");
            echo "                    </label>\n                    <div class=\"col-lg-4 col-sm-4\">\n                        <input id=\"addUsername\"\n                               type=\"text\"\n                               autocomplete=\"off\"\n                               class=\"form-control input-200\"\n                               data-related-id=\"inputUsername\"\n                        >\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"addPassword\" class=\"col-lg-3 col-sm-4 control-label\">\n                        ";
            echo AdminLang::trans("fields.password");
            echo "                    </label>\n                    <div class=\"col-lg-4 col-sm-4\">\n                        <input id=\"addPassword\"\n                               type=\"password\"\n                               autocomplete=\"off\"\n                               class=\"form-control input-200\"\n                               data-related-id=\"inputPassword\"\n                        >\n                    </div>\n                </div>\n                <div class=\"form-group\">\n                    <label for=\"newHash\" class=\"col-lg-3 col-sm-4 control-label\">\n                        <span class=\"access-hash\">\n                            ";
            echo AdminLang::trans("configservers.accesshash");
            echo "                        </span>\n                        <span class=\"api-key hidden\">\n                            ";
            echo AdminLang::trans("configservers.apiToken");
            echo "                        </span>\n                    </label>\n                    <div class=\"col-lg-4 col-sm-4\">\n                        <input id=\"newToken\"\n                               type=\"password\"\n                               class=\"form-control input-500 hidden\"\n                               disabled=\"disabled\"\n                               data-related-id=\"apiToken\"\n                        />\n                        <textarea id=\"newHash\"\n                                  rows=\"8\"\n                                  class=\"form-control input-500\"\n                                  data-related-id=\"serverHash\"\n                        ></textarea>\n\n                    </div>\n                </div>\n                <div class=\"alert alert-grey connection-test-result hidden\"></div>\n                <div class=\"btn-container\">\n                    <button type=\"button\" id=\"newTestConn\" class=\"btn btn-primary\">\n                        ";
            echo AdminLang::trans("configservers.testconnection");
            echo "                        &raquo;\n                    </button>\n                    <button type=\"button\" id=\"newCont\" class=\"btn btn-primary hidden\">\n                        ";
            echo AdminLang::trans("global.continue");
            echo "                        &raquo;\n                    </button>\n                    <button type=\"button\" id=\"newContAny\" class=\"btn btn-default\" disabled=\"disabled\">\n                        ";
            echo AdminLang::trans("global.continueAnyway");
            echo "                    </button>\n                </div>\n            </form>\n        </div>\n    ";
            $class = "class=\"hidden\"";
            $action .= "&id=" . $id;
        }
        echo "\n<form method=\"post\" ";
        echo $class;
        echo "\" action=\"";
        echo $action;
        echo "\" id=\"frmServerConfig\">\n<input type=\"hidden\" name=\"serverid\" value=\"";
        echo $id;
        echo "\" />\n    <div class=\"alert alert-success hidden\" id=\"newServerWizardSuccess\">\n        <i class=\"fas fa-check fa-fw\"></i>\n        ";
        echo AdminLang::trans("configservers.testConnectionSuccessWithAutoFill");
        echo "    </div>\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"23%\" class=\"fieldlabel\">\n                ";
        echo $aInt->lang("fields", "name");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"name\" size=\"30\" value=\"";
        echo $name ?? "";
        echo "\" class=\"form-control input-400\" id=\"inputName\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("fields", "hostname");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"hostname\" size=\"40\" value=\"";
        echo $hostname ?? "";
        echo "\" class=\"form-control input-400\" id=\"inputHostname\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("fields", "ipaddress");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"ipaddress\" size=\"20\" value=\"";
        echo $ipaddress ?? "";
        echo "\" class=\"form-control input-200\" id=\"inputPrimaryIp\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "assignedips");
        echo "<br/>\n                ";
        echo $aInt->lang("configservers", "assignedipsdesc");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <textarea id=\"assignedIps\" name=\"assignedips\" cols=\"60\" rows=\"8\" class=\"form-control input-400\">";
        echo $assignedips ?? "";
        echo "</textarea>\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "monthlycost");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"monthlycost\" size=\"10\" value=\"";
        echo $monthlycost ?? "";
        echo "\" class=\"form-control input-100\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "datacenter");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"noc\" size=\"30\" value=\"";
        echo $noc ?? "";
        echo "\" class=\"form-control input-300\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "maxaccounts");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"maxaccounts\" size=\"6\" value=\"";
        echo $maxaccounts ?? "";
        echo "\" class=\"form-control input-100\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "statusaddress");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"statusaddress\" size=\"60\" value=\"";
        echo $statusaddress ?? "";
        echo "\" class=\"form-control input-600\" />\n                ";
        echo $aInt->lang("configservers", "statusaddressdesc");
        echo "            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("general", "enabledisable");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <label class=\"checkbox-inline\">\n                    <input type=\"checkbox\" name=\"disabled\" value=\"1\" class=\"checkbox\" ";
        if (isset($disabled) && $disabled) {
            echo "checked ";
        }
        echo "/>\n                    ";
        echo $aInt->lang("configservers", "disableserver");
        echo "                </label>\n            </td>\n        </tr>\n    </table>\n    <p>\n        <b>\n            ";
        echo $aInt->lang("configservers", "nameservers");
        echo "        </b>\n    </p>\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"23%\" class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "primarynameserver");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"nameserver1\" size=\"40\" value=\"";
        echo $nameserver1 ?? "";
        echo "\" class=\"form-control input-400 input-inline\" />\n                ";
        echo $aInt->lang("fields", "ipaddress");
        echo ": <input type=\"text\" name=\"nameserver1ip\" size=\"25\" value=\"";
        echo $nameserver1ip ?? "";
        echo "\" class=\"form-control input-200 input-inline\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "secondarynameserver");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"nameserver2\" size=\"40\" value=\"";
        echo $nameserver2 ?? "";
        echo "\" class=\"form-control input-400 input-inline\" />\n                ";
        echo $aInt->lang("fields", "ipaddress");
        echo ": <input type=\"text\" name=\"nameserver2ip\" size=\"25\" value=\"";
        echo $nameserver2ip ?? "";
        echo "\" class=\"form-control input-200 input-inline\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "thirdnameserver");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"nameserver3\" size=\"40\" value=\"";
        echo $nameserver3 ?? "";
        echo "\" class=\"form-control input-400 input-inline\" />\n                ";
        echo $aInt->lang("fields", "ipaddress");
        echo ": <input type=\"text\" name=\"nameserver3ip\" size=\"25\" value=\"";
        echo $nameserver3ip ?? "";
        echo "\" class=\"form-control input-200 input-inline\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "fourthnameserver");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"nameserver4\" size=\"40\" value=\"";
        echo $nameserver4 ?? "";
        echo "\" class=\"form-control input-400 input-inline\" />\n                ";
        echo $aInt->lang("fields", "ipaddress");
        echo ": <input type=\"text\" name=\"nameserver4ip\" size=\"25\" value=\"";
        echo $nameserver4ip ?? "";
        echo "\" class=\"form-control input-200 input-inline\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "fifthnameserver");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"nameserver5\" size=\"40\" value=\"";
        echo $nameserver5 ?? "";
        echo "\" class=\"form-control input-400 input-inline\" />\n                ";
        echo $aInt->lang("fields", "ipaddress");
        echo ": <input type=\"text\" name=\"nameserver5ip\" size=\"25\" value=\"";
        echo $nameserver5ip ?? "";
        echo "\" class=\"form-control input-200 input-inline\" />\n            </td>\n        </tr>\n    </table>\n    <p>\n        <b>\n            ";
        echo $aInt->lang("configservers", "serverdetails");
        echo "        </b>\n    </p>\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"23%\" class=\"fieldlabel\">\n                ";
        echo AdminLang::trans("fields.module");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <select name=\"type\" class=\"form-control select-inline\" id=\"inputServerType\">";
        echo $serverModuleDropdownHtml;
        echo "</select>\n                <input type=\"button\" value=\"";
        echo $aInt->lang("configservers", "testconnection");
        echo "\" id=\"connectionTestBtn\" class=\"btn btn-danger btn-xs\"";
        echo $moduleInfo["cantestconnection"] ? "" : " style=\"display:none;\"";
        echo " />\n                <div class=\"alert alert-grey connection-test-result hidden\" style=\"display:inline-block;margin:0;padding:4px 15px;\">\n                    <i class=\"fas fa-spinner fa-spin fa-fw\"></i>\n                    ";
        echo AdminLang::trans("configservers.testconnectionloading");
        echo "                </div>\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("fields", "username");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"username\" id=\"inputUsername\" value=\"";
        echo $username ?? "";
        echo "\" autocomplete=\"off\" class=\"form-control input-200\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("fields", "password");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"password\" name=\"password\" id=\"inputPassword\" value=\"";
        echo replacePasswordWithMasks($password ?? "");
        echo "\" autocomplete=\"off\" class=\"form-control input-200\" />\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        $apiTokenClass = " hidden";
        $accessHashClass = "";
        if ($type == "cpanel") {
            $apiTokenClass = "";
            $accessHashClass = " hidden";
        }
        echo "<span class=\"access-hash" . $accessHashClass . "\">\n    " . $aInt->lang("configservers", "accesshash") . "\n</span>\n<span class=\"api-key" . $apiTokenClass . "\">\n    " . $aInt->lang("configservers", "apiToken") . "\n</span>";
        echo "            </td>\n            <td class=\"fieldarea\">\n                ";
        $apiTokenDisabled = " disabled=\"disabled\"";
        $apiTokenClass = " hidden";
        $accessHashDisabled = "";
        $accessHashClass = "";
        $toolTip = AdminLang::trans("configservers.apiTokenInfo");
        if ($type == "cpanel" && (!$accesshash || $accesshash && !stristr($accesshash, "\r\n"))) {
            $apiTokenDisabled = "";
            $apiTokenClass = "";
            $accessHashDisabled = " disabled=\"disabled\"";
            $accessHashClass = " hidden";
        }
        echo "<input id=\"apiToken\" type=\"password\" name=\"accesshash\" class=\"form-control input-500" . $apiTokenClass . "\"" . $apiTokenDisabled . " value=\"" . $accesshash . "\" data-toggle=\"tooltip\" data-placement=\"auto right\" data-trigger=\"focus\" title=\"" . $toolTip . "\"/>\n<textarea id=\"serverHash\" name=\"accesshash\" rows=\"8\" class=\"form-control input-500" . $accessHashClass . "\"" . $accessHashDisabled . ">" . $accesshash . "</textarea>";
        echo "            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "secure");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <label class=\"checkbox-inline\">\n                    <input type=\"checkbox\" name=\"secure\" id=\"inputSecure\"";
        if ($secure) {
            echo " checked";
        }
        echo " class=\"checkbox\"/>\n                    ";
        echo $aInt->lang("configservers", "usessl");
        echo "                </label>\n            </td>\n        </tr>\n        <tr id=\"trPort\"";
        if (!$moduleInfo["defaultsslport"] && !$moduleInfo["defaultnonsslport"]) {
            echo " style=\"display:none;\"";
        }
        echo ">\n            <td class=\"fieldlabel\">\n                ";
        echo $aInt->lang("configservers", "port");
        echo "            </td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"port\" id=\"inputPort\" class=\"form-control input-75 input-inline\" value=\"";
        echo $port ? $port : $defaultPort;
        echo "\" size=\"8\"";
        if (!$port) {
            echo " disabled=\"disabled\"";
        }
        echo " />\n                <label class=\"checkbox-inline\">\n                    <input type=\"checkbox\" id=\"inputOverridePort\"";
        if ($port) {
            echo " checked";
        }
        echo " />\n                    ";
        echo $aInt->lang("configservers", "portoverride");
        echo "                </label>\n            </td>\n        </tr>\n    </table>\n\n<div id=\"containerAccessControl\"";
        if (!$moduleInfo["supportsadminsso"]) {
            echo " style=\"display:none;\"";
        }
        echo ">\n\n<p><b>SSO Access Control</b></p>\n<p>This server module supports Single Sign-On for admin users. Below you can configure access permissions for this.</p>\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"23%\" class=\"fieldlabel\">Access Control</td><td class=\"fieldarea\">\n<label class=\"radio-inline\"><input type=\"radio\" name=\"restrictsso\" value=\"0\" onclick=\"hideAccessControl()\"";
        if (empty($isSsoRestricted)) {
            echo " checked";
        }
        echo "> Unrestricted - Allow all admin users to connect to this server</label><br />\n<label class=\"radio-inline\"><input type=\"radio\" name=\"restrictsso\" value=\"1\" onclick=\"showAccessControl()\"";
        if (isset($isSsoRestricted) && $isSsoRestricted) {
            echo " checked";
        }
        echo "> Restricted - Only allow access to select admin role groups and/or users</label><br />\n</td></tr>\n<tr class=\"trAccessControl\"";
        if (empty($isSsoRestricted)) {
            echo " style=\"display:none;\"";
        }
        echo "><td width=\"23%\" class=\"fieldlabel\">Admin Role Groups</td><td class=\"fieldarea\">\nAllow access to any admin users in the following admin role groups:<br />\n";
        $adminRoles = Illuminate\Database\Capsule\Manager::table("tbladminroles")->orderBy("name", "asc")->pluck("name", "id")->all();
        foreach ($adminRoles as $id => $name) {
            echo sprintf("<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"restrictssoroles[]\" value=\"%s\"%s />%s</label>", $id, in_array($id, $currentSsoRoleRestrictions) ? " checked" : "", $name);
        }
        echo "</td></tr>\n</table>\n\n</div>\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
        echo $aInt->lang("global", "savechanges");
        echo "\" class=\"button btn btn-primary\">\n    <input type=\"button\" value=\"";
        echo $aInt->lang("global", "cancelchanges");
        echo "\" class=\"btn btn-default\" onclick=\"window.location='configservers.php'\" />\n</div>\n\n</form>\n\n";
        $connectionTestJSCode = "\nvar defaultSSLPort = \"" . $moduleInfo["defaultsslport"] . "\";\nvar defaultNonSSLPort = \"" . $moduleInfo["defaultnonsslport"] . "\";\nvar connectionTestSupported = " . ($moduleInfo["cantestconnection"] ? 1 : 0) . ";\n";
        $aInt->addHeadJsCode($connectionTestJSCode);
    } else {
        if ($action == "managegroup") {
            if ($id) {
                $managetitle = $aInt->lang("configservers", "editgroup");
                $result = select_query("tblservergroups", "", ["id" => $id]);
                $data = mysql_fetch_array($result);
                $id = $data["id"];
                $name = $data["name"];
                $filltype = $data["filltype"];
            } else {
                $managetitle = $aInt->lang("configservers", "newgroup");
                $filltype = "1";
            }
            echo "<h2>" . $managetitle . "</h2>";
            echo "\n<form method=\"post\" action=\"";
            echo $_SERVER["PHP_SELF"];
            echo "?action=savegroup&id=";
            echo $id;
            echo "\">\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"15%\" class=\"fieldlabel\">";
            echo AdminLang::trans("fields.name");
            echo "</td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" class=\"form-control input-400\" name=\"name\" value=\"";
            echo $name ?? "";
            echo "\">\n            </td>\n        </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
            echo AdminLang::trans("fields.filltype");
            echo "</td>\n        <td class=\"fieldarea\">\n            <input type=\"radio\" name=\"filltype\" value=\"1\" ";
            echo $filltype == 1 ? "checked" : "";
            echo ">\n            ";
            echo AdminLang::trans("configservers.addleast");
            echo "<br/>\n            <input type=\"radio\" name=\"filltype\" value=\"2\" ";
            echo $filltype == 2 ? "checked" : "";
            echo ">\n            ";
            echo AdminLang::trans("configservers.fillactive");
            echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
            echo AdminLang::trans("fields.selectedservers");
            echo "</td>\n        <td class=\"fieldarea\">\n            <table>\n                <td><td>\n                    <select size=\"10\" class=\"form-control\" multiple=\"multiple\" id=\"serverslist\"\n                            style=\"width:200px;\">\n                        ";
            $selectedServers = [];
            $groups = Illuminate\Database\Capsule\Manager::table("tblservergroupsrel")->join("tblservers", "tblservers.id", "=", "tblservergroupsrel.serverid")->where("groupid", $id)->orderBy("name")->get(["tblservers.id", "tblservers.name", "tblservers.disabled"]);
            foreach ($groups as $group) {
                $id = $group->id;
                $name = $group->name;
                $disabled = $group->disabled;
                $name .= $disabled ? " (" . AdminLang::trans("emailtpls.disabled") . ")" : "";
                $selectedServers[$id] = $name;
            }
            $servers = WHMCS\Product\Server::all()->sortBy("name");
            foreach ($servers as $server) {
                $id = $server->id;
                $name = $server->name;
                $disabled = $server->disabled;
                $name .= $disabled ? " (" . AdminLang::trans("emailtpls.disabled") . ")" : "";
                if (!array_key_exists($id, $selectedServers)) {
                    echo "<option value=\"" . $id . "\">" . $name . "</option>";
                }
            }
            echo "                    </select>\n                </td>\n                <td align=\"center\">\n                    <input type=\"button\" id=\"serveradd\" value=\"";
            echo AdminLang::trans("global.add");
            echo " &raquo;\"\n                           class=\"btn btn-sm\" /><br/><br/>\n                    <input type=\"button\" id=\"serverrem\"\n                           value=\"&laquo; ";
            echo AdminLang::trans("global.remove");
            echo "\" class=\"btn btn-sm\" />\n                </td>\n                <td>\n                    <select size=\"10\" class=\"form-control\" multiple=\"multiple\" id=\"selectedservers\"\n                            name=\"selectedservers[]\" style=\"width:200px;\">\n                        ";
            foreach ($selectedServers as $id => $name) {
                echo "<option value=\"" . $id . "\">" . $name . "</option>";
            }
            echo "                    </select>\n                </td>\n            </table>\n        </td>\n    </tr>\n    </table>\n    <div class=\"btn-container\">\n        <input type=\"submit\" value=\"";
            echo AdminLang::trans("global.savechanges");
            echo "\"\n               onclick=\"\$('#selectedservers *').prop('selected', true)\" class=\"btn btn-primary\">\n        <input type=\"button\" value=\"";
            echo AdminLang::trans("global.cancelchanges");
            echo "\"\n               class=\"btn btn-default\" onclick=\"window.location='configservers.php'\" />\n    </div>\n</form>\n\n";
        }
    }
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->display();
function getModuleInfo($moduleName)
{
    $returnData = ["cantestconnection" => false, "supportsadminsso" => false, "defaultsslport" => "", "defaultnonsslport" => ""];
    $moduleInterface = new WHMCS\Module\Server();
    if ($moduleInterface->load($moduleName)) {
        if ($moduleInterface->functionExists("TestConnection")) {
            $returnData["cantestconnection"] = true;
        }
        if ($moduleInterface->functionExists("AdminSingleSignOn")) {
            $returnData["supportsadminsso"] = true;
        }
        $returnData["defaultsslport"] = $moduleInterface->getMetaDataValue("DefaultSSLPort");
        $returnData["defaultnonsslport"] = $moduleInterface->getMetaDataValue("DefaultNonSSLPort");
    }
    if ($moduleName == "cpanel") {
        $returnData["apiTokens"] = true;
    }
    return $returnData;
}
