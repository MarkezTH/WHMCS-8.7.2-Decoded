<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Edit Clients Products/Services");
$aInt->title = $aInt->lang("clients", "transferownership");
ob_start();
switch ($type) {
    case "domain":
        $typeChoose = "domain";
        $invoiceTypes = [WHMCS\Billing\Invoice\Item::TYPE_DOMAIN, WHMCS\Billing\Invoice\Item::TYPE_DOMAIN_DNS_MANAGEMENT, WHMCS\Billing\Invoice\Item::TYPE_DOMAIN_EMAIL_FORWARDING, WHMCS\Billing\Invoice\Item::TYPE_DOMAIN_ID_PROTECTION, WHMCS\Billing\Invoice\Item::TYPE_DOMAIN_REGISTRATION, WHMCS\Billing\Invoice\Item::TYPE_DOMAIN_TRANSFER];
        $service = WHMCS\Domain\Domain::find(trim($id));
        break;
    case "hosting":
    default:
        $typeChoose = "product/service";
        $invoiceTypes = [WHMCS\Billing\Invoice\Item::TYPE_SERVICE];
        $service = WHMCS\Service\Service::find(trim($id));
        if ($action == "") {
            $validationError = NULL;
            if ($service) {
                $allowTransfer = true;
                $unpaidInvoices = WHMCS\Billing\Invoice::unpaid()->with("items")->whereHas("items", function ($query) use($service, $invoiceTypes) {
                    $query->where("relid", "=", $service->id)->whereIn("type", $invoiceTypes);
                })->get();
                if (0 < $unpaidInvoices->count()) {
                    $allowTransfer = false;
                    $validationError = AdminLang::trans("clients.moveServiceUnpaidInvoices", [":type" => ucfirst($type)]);
                    $validationError .= "<ul>";
                    foreach ($unpaidInvoices as $invoice) {
                        $invoiceNumber = $invoice->invoicenum ?: $invoice->id;
                        $validationError .= "<li>\n    <a href=\"invoices.php?action=edit&id=" . $invoice->id . "\" target=\"_blank\">Invoice #" . $invoiceNumber . "</a>\n</li>";
                    }
                    $validationError .= "</ul>";
                    $validationError .= "<a class=\"btn btn-primary pull-right\" href=\"#\" onclick=\"window.close()\">" . AdminLang::trans("global.close") . "</a>";
                }
            } else {
                $validationError = "Service Not Found";
            }
            echo "<script type=\"text/javascript\">\n\$(document).ready(function(){\n    \$(\"#clientsearchval\").keyup(function () {\n        var useridsearchlength = \$(\"#clientsearchval\").val().length;\n        if (useridsearchlength>2) {\n        WHMCS.http.jqClient.post(whmcsBaseUrl + adminBaseRoutePath + \"/search.php\", { clientsearch: 1, value: \$(\"#clientsearchval\").val(), token: \"" . generate_token("plain") . "\" },\n            function(data){\n                if (data) {\n                    \$(\"#clientsearchresults\").html(data);\n                    \$(\"#clientsearchresults\").slideDown(\"slow\");\n                }\n            });\n        }\n    });\n});\nfunction searchselectclient(userid,name,email) {\n    \$(\"#newuserid\").val(userid);\n    \$(\"#clientsearchresults\").slideUp();\n}\n\nvar whmcsBaseUrl = \"" . WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "\";\nvar adminBaseRoutePath = \"" . WHMCS\Admin\AdminServiceProvider::getAdminRouteBase() . "\";\n</script>\n";
            if ($error) {
                echo "<div class=\"errorbox\">" . $aInt->lang("clients", "invalidowner") . "</div><br />";
            }
            if ($validationError) {
                echo WHMCS\View\Helper::alert($validationError);
            }
            if ($allowTransfer) {
                echo "\n<form method=\"post\" action=\"";
                echo $whmcs->getPhpSelf();
                echo "?action=transfer&type=";
                echo $type;
                echo "&id=";
                echo $id;
                echo "\">\n";
                echo AdminLang::trans("clients.transferchoose", [":entity" => AdminLang::trans($type)]);
                echo "<br /><br />\n<div align=\"center\">\n";
                echo $aInt->lang("fields", "clientid");
                echo ": <input type=\"text\" name=\"newuserid\" id=\"newuserid\" size=\"10\" /> <input type=\"submit\" value=\"";
                echo $aInt->lang("domains", "transfer");
                echo "\" class=\"button btn btn-default\" /><br /><br />\n";
                echo $aInt->lang("global", "clientsintellisearch");
                echo ": <input type=\"text\" id=\"clientsearchval\" size=\"25\" />\n</div>\n<br />\n<div id=\"clientsearchresults\">\n<div class=\"searchresultheader\">";
                echo AdminLang::trans("global.searchresults");
                echo "</div>\n<div class=\"searchresult\" align=\"center\">";
                echo AdminLang::trans("search.autoSearchOnType");
                echo "</div>\n</div>\n</form>\n\n";
            }
        } else {
            check_token("WHMCS.admin.default");
            $newuserid = trim($newuserid);
            $result = select_query("tblclients", "id", ["id" => $newuserid]);
            $data = mysql_fetch_array($result);
            $newuserid = $data["id"];
            if (!$newuserid) {
                redir("type=" . $type . "&id=" . $id . "&error=1");
            }
            if ($type == "hosting") {
                $result = select_query("tblhosting", "userid", ["id" => $id]);
                $data = mysql_fetch_array($result);
                $moduleInterface = "";
                $hasAppLinks = false;
                try {
                    $moduleInterface = new WHMCS\Module\Server();
                    if ($moduleInterface->loadByServiceID($id) && $moduleInterface->isApplicationLinkSupported() && $moduleInterface->isApplicationLinkingEnabled()) {
                        $call = "Delete";
                        $moduleInterface->doSingleApplicationLinkCall($call);
                        $hasAppLinks = true;
                    }
                } catch (Exception $e) {
                }
                $userid = $data["userid"];
                logActivity("Moved Service ID: " . $id . " from User ID: " . $userid . " to User ID: " . $newuserid, $newuserid);
                update_query("tblhosting", ["userid" => $newuserid], ["id" => $id]);
                $addons = Illuminate\Database\Capsule\Manager::table("tblhostingaddons")->where("hostingid", $id)->get()->all();
                $addonsWithAppLinks = [];
                $addonModuleInterface = "";
                $hasAddonAppLinks = false;
                foreach ($addons as $addon) {
                    try {
                        $addonModuleInterface = new WHMCS\Module\Server();
                        if ($addonModuleInterface->loadByAddonId($addon->id) && $addonModuleInterface->isApplicationLinkSupported() && $addonModuleInterface->isApplicationLinkingEnabled()) {
                            $addonsWithAppLinks[] = $addon->id;
                            $call = "Delete";
                            $addonModuleInterface->doSingleApplicationLinkCall($call);
                            $hasAddonAppLinks = true;
                        }
                    } catch (Exception $e) {
                    }
                }
                Illuminate\Database\Capsule\Manager::table("tblhostingaddons")->where("hostingid", $id)->update(["userid" => $newuserid]);
                Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("serviceid", "=", $id)->update(["userid" => $newuserid]);
                if ($hasAppLinks) {
                    try {
                        $moduleInterface = new WHMCS\Module\Server();
                        if ($moduleInterface->loadByServiceID($id) && $moduleInterface->isApplicationLinkSupported() && $moduleInterface->isApplicationLinkingEnabled()) {
                            $call = "Create";
                            $moduleInterface->doSingleApplicationLinkCall($call);
                        }
                    } catch (Exception $e) {
                    }
                }
                if ($hasAddonAppLinks) {
                    foreach ($addonsWithAppLinks as $addonId) {
                        try {
                            $addonModuleInterface = new WHMCS\Module\Server();
                            if ($addonModuleInterface->loadByAddonId($addonId) && $addonModuleInterface->isApplicationLinkSupported() && $addonModuleInterface->isApplicationLinkingEnabled()) {
                                $call = "Create";
                                $addonModuleInterface->doSingleApplicationLinkCall($call);
                            }
                        } catch (Exception $e) {
                        }
                    }
                }
                echo "<script language=\"javascript\">\nwindow.opener.location.href = \"clientshosting.php?userid=";
                echo $newuserid;
                echo "&id=";
                echo $id;
                echo "\";\nwindow.close();\n</script>\n";
            } else {
                if ($type == "domain") {
                    $result = select_query("tbldomains", "userid", ["id" => $id]);
                    $data = mysql_fetch_array($result);
                    $userid = $data["userid"];
                    logActivity("Moved Domain ID: " . $id . " from User ID: " . $userid . " to User ID: " . $newuserid, $newuserid);
                    update_query("tbldomains", ["userid" => $newuserid], ["id" => $id]);
                    echo "<script language=\"javascript\">\nwindow.opener.location.href = \"clientsdomains.php?userid=";
                    echo $newuserid;
                    echo "&id=";
                    echo $id;
                    echo "\";\nwindow.close();\n</script>\n";
                }
            }
        }
        $content = ob_get_contents();
        ob_end_clean();
        $aInt->content = $content;
        $aInt->displayPopUp();
}
