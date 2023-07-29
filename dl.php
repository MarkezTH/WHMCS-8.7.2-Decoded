<?php

define("CLIENTAREA", true);
require "init.php";
$type = App::getFromRequest("type");
switch ($type) {
    case "i":
        dlActionInvoice();
        break;
    case "d":
        dlActionDownload();
        break;
    case "f":
        dlActionFile();
        break;
    case "q":
        dlActionQuote();
        break;
    default:
        if (in_array($type, ["a", "ar", "an"])) {
            dlActionAttachment();
        } else {
            redir("", "index.php");
        }
}

function downloadLogin()
{
    global $smartyvalues;
    $whmcs = App::self();
    $pageTitle = Lang::trans("downloadstitle");
    $tagline = Lang::trans("downloadLoginRequiredTagline");
    $breadCrumb = "<a href=\"" . $whmcs->getSystemURL() . "\">" . Lang::trans("globalsystemname") . "</a>" . " > " . "<a href=\"" . routePath("download-index") . "\">" . Lang::trans("downloadstitle") . "</a>";
    initialiseClientArea($pageTitle, $pageTitle, $tagline, "", $breadCrumb);
    if (class_exists("Auth")) {
        Auth::logout();
        Auth::requireLogin(true);
    } else {
        require "login.php";
        exit;
    }
}

function dlServeFile(WHMCS\File\Filesystem $storage = NULL, $file_name = "", $display_name = "")
{
    if (is_null($storage) || !trim($file_name)) {
        redir("", "index.php");
    }
    try {
        $fileSize = $storage->getSizeStrict($file_name);
    } catch (Throwable $e) {
        if (WHMCS\Admin::getID()) {
            $extraMessage = "This could indicate that the file is missing or that <a href=\"" . routePath("admin-setup-storage-index") . "\" target=\"_blank\">storage configuration settings" . "</a> are misconfigured. " . "<a href=\"https://docs.whmcs.com/Storage_Settings" . "#Troubleshooting_a_File_Not_Found_Error\" target=\"_blank\">" . "Learn more</a>";
        } else {
            $extraMessage = "Please contact support.";
        }
        throw new WHMCS\Exception\Fatal("File not found. " . $extraMessage);
    }
    run_hook("FileDownload", []);
    header("Pragma: public");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0, private");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"" . $display_name . "\"");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . $fileSize);
    $stream = $storage->readStream($file_name);
    echo stream_get_contents($stream);
    fclose($stream);
}

function dlActionInvoice()
{
    $id = (int) App::getFromRequest("id");
    $language = App::getFromRequest("language");
    $viewpdf = App::getFromRequest("viewpdf");
    if (!$id) {
        redir("", "clientarea.php");
    }
    $result = select_query("tblinvoices", "", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $invoiceid = $data["id"];
    $invoicenum = $data["invoicenum"];
    $userid = (int) $data["userid"];
    $status = $data["status"];
    if (!$invoiceid) {
        redir("", "clientarea.php");
    }
    if (!function_exists("checkPermission")) {
        require_once ROOTDIR . "/includes/adminfunctions.php";
    }
    if (class_exists("Auth")) {
        $authenticatedClient = Auth::client();
        $authenticatedClientId = $authenticatedClient ? $authenticatedClient->id : 0;
        unset($authenticatedClient);
    } else {
        $authenticatedClientId = (int) WHMCS\Session::get("uid");
    }
    if (!empty($_SESSION["adminid"])) {
        if (!checkPermission("Manage Invoice", true)) {
            exit("You do not have the necessary permissions to download PDF invoices. If you feel this message to be an error, please contact the system administrator.");
        }
    } else {
        if ($authenticatedClientId === $userid) {
            if ($status == "Draft") {
                redir("", "clientarea.php");
            }
        } else {
            downloadlogin();
        }
    }
    if (!$invoicenum) {
        $invoicenum = $invoiceid;
    }
    if ($language) {
        $clientLanguages = WHMCS\Language\ClientLanguage::getLanguages();
        if (!in_array($language, $clientLanguages)) {
            $language = "";
        }
    }
    require_once ROOTDIR . "/includes/invoicefunctions.php";
    $pdfdata = pdfInvoice($id, $language);
    $filenameSuffix = preg_replace("|[\\\\/]+|", "-", $invoicenum);
    header("Pragma: public");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0, private");
    header("Cache-Control: private", false);
    header("Content-Type: application/pdf");
    header("Content-Disposition: " . ($viewpdf ? "inline" : "attachment") . "; filename=\"" . Lang::trans("invoicefilename") . $filenameSuffix . ".pdf\"");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . strlen($pdfdata));
    echo $pdfdata;
    exit;
}

function dlActionQuote()
{
    $id = (int) App::getFromRequest("id");
    $viewpdf = App::getFromRequest("viewpdf");
    if (class_exists("Auth")) {
        $authenticatedClient = Auth::client();
        $authenticatedClientId = $authenticatedClient ? $authenticatedClient->id : 0;
        unset($authenticatedClient);
    } else {
        $authenticatedClientId = (int) WHMCS\Session::get("uid");
    }
    if (!$authenticatedClientId && empty($_SESSION["adminid"])) {
        downloadlogin();
    }
    if (!empty($_SESSION["adminid"])) {
        if (!function_exists("checkPermission")) {
            require_once ROOTDIR . "/includes/adminfunctions.php";
        }
        if (!checkPermission("Manage Quotes", true)) {
            exit("You do not have the necessary permissions to Manage Quotes. If you feel this message to be an error, please contact the system administrator.");
        }
    }
    $result = select_query("tblquotes", "id,userid", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $id = $data["id"];
    $userid = $data["userid"];
    if (class_exists("Auth")) {
        $authenticatedClient = Auth::client();
        $authenticatedClientId = $authenticatedClient ? $authenticatedClient->id : 0;
        unset($authenticatedClient);
    } else {
        $authenticatedClientId = (int) WHMCS\Session::get("uid");
    }
    if ($userid !== $authenticatedClientId && empty($_SESSION["adminid"])) {
        exit("Permission Denied");
    }
    require_once ROOTDIR . "/includes/clientfunctions.php";
    require_once ROOTDIR . "/includes/invoicefunctions.php";
    require_once ROOTDIR . "/includes/quotefunctions.php";
    $pdfdata = genQuotePDF($id);
    header("Pragma: public");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0, private");
    header("Cache-Control: private", false);
    header("Content-Type: application/pdf");
    header("Content-Disposition: " . ($viewpdf ? "inline" : "attachment") . "; filename=\"" . Lang::trans("quotefilename") . $id . ".pdf\"");
    header("Content-Transfer-Encoding: binary");
    echo $pdfdata;
    exit;
}

function dlActionFile()
{
    $id = (int) App::getFromRequest("id");
    $result = select_query("tblclientsfiles", "userid,filename,adminonly", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $userid = $data["userid"];
    $file_name = $data["filename"];
    $adminonly = $data["adminonly"];
    $display_name = substr($file_name, 11);
    $storage = Storage::clientFiles();
    if (class_exists("Auth")) {
        $authenticatedClient = Auth::client();
        $authenticatedClientId = $authenticatedClient ? $authenticatedClient->id : 0;
        unset($authenticatedClient);
    } else {
        $authenticatedClientId = (int) WHMCS\Session::get("uid");
    }
    if ($userid !== $authenticatedClientId && empty($_SESSION["adminid"])) {
        downloadlogin();
    }
    if (empty($_SESSION["adminid"]) && !empty($adminonly)) {
        exit("Permission Denied");
    }
    dlservefile($storage, $file_name, $display_name);
}

function dlActionAttachment()
{
    $type = App::getFromRequest("type");
    $id = (int) App::getFromRequest("id");
    $i = (int) App::getFromRequest("i");
    $useridOfMasterTicket = $useridOfReply = 0;
    $adminOnly = false;
    $ticketid = $attachments = "";
    switch ($type) {
        case "an":
            $noteData = WHMCS\Database\Capsule::table("tblticketnotes")->find($id, ["ticketid", "attachments"]);
            if ($noteData) {
                $attachments = $noteData->attachments;
                $ticketid = $noteData->ticketid;
                $adminOnly = true;
            }
            break;
        case "ar":
            $replyData = WHMCS\Database\Capsule::table("tblticketreplies")->find($id, ["tid", "userid", "attachment"]);
            if ($replyData) {
                $attachments = $replyData->attachment;
                $ticketid = $replyData->tid;
                $useridOfReply = $replyData->userid;
                $useridOfMasterTicket = (int) get_query_val("tbltickets", "userid", ["id" => $ticketid]);
            }
            break;
        default:
            $ticketData = WHMCS\Database\Capsule::table("tbltickets")->find($id, ["id", "userid", "attachment"]);
            if ($ticketData) {
                $attachments = $ticketData->attachment;
                $ticketid = $ticketData->id;
                $useridOfMasterTicket = (int) $ticketData->userid;
            }
            if (!$ticketid) {
                exit("Ticket ID Not Found");
            }
            if ($adminOnly && !WHMCS\Session::get("adminid")) {
                exit("Access Denied. You do not have the required permissions to view this ticket.");
            }
            if (WHMCS\Session::get("adminid")) {
                if (!function_exists("checkPermission")) {
                    require_once ROOTDIR . "/includes/adminfunctions.php";
                }
                if (!checkPermission("View Support Ticket", true)) {
                    exit("You do not have the necessary permissions to View Support Tickets. If you feel this message to be an error, please contact the system administrator.");
                }
                require_once ROOTDIR . "/includes/ticketfunctions.php";
                $access = validateAdminTicketAccess($ticketid);
                if ($access) {
                    exit("Access Denied. You do not have the required permissions to view this ticket.");
                }
            } else {
                if (!$adminOnly) {
                    if (class_exists("Auth")) {
                        $authenticatedClient = Auth::client();
                        $authenticatedClientId = $authenticatedClient ? $authenticatedClient->id : 0;
                        unset($authenticatedClient);
                    } else {
                        $authenticatedClientId = (int) WHMCS\Session::get("uid");
                    }
                    if ($useridOfMasterTicket) {
                        if ($useridOfMasterTicket !== $authenticatedClientId) {
                            downloadlogin();
                            exit;
                        }
                    } else {
                        if ($useridOfReply) {
                            if ($useridOfReply !== $authenticatedClientId) {
                                downloadlogin();
                                exit;
                            }
                        } else {
                            $AccessedTicketIDs = WHMCS\Session::get("AccessedTicketIDs");
                            $AccessedTicketIDsArray = explode(",", $AccessedTicketIDs);
                            if (!in_array($ticketid, $AccessedTicketIDsArray)) {
                                exit("Ticket Attachments cannot be accessed directly. Please try again using the download link provided within the ticket. If you are registered and have an account with us, you can access your tickets from our client area. Otherwise, please use the link to view the ticket which you should have received via email when the ticket was originally opened or last responded to.");
                            }
                        }
                    }
                }
            }
            $storage = Storage::ticketAttachments();
            $files = explode("|", $attachments);
            $file_name = $files[$i];
            $display_name = substr($file_name, 7);
            dlservefile($storage, $file_name, $display_name);
    }
}

function dlActionDownload()
{
    $smartyvalues = [];
    $id = (int) App::getFromRequest("id");
    $data = get_query_vals("tbldownloads", "id,location,clientsonly,productdownload", ["id" => $id]);
    $downloadID = $data["id"];
    $filename = $data["location"];
    $clientsonly = $data["clientsonly"];
    $wantsProductRestrictedDownload = $data["productdownload"];
    if (!$downloadID) {
        exit("Invalid Download Requested");
    }
    if (class_exists("Auth")) {
        $authenticatedClient = Auth::client();
        $authenticatedClientId = $authenticatedClient ? $authenticatedClient->id : 0;
        unset($authenticatedClient);
    } else {
        $authenticatedClientId = (int) WHMCS\Session::get("uid");
    }
    if (!$authenticatedClientId && ($clientsonly || $wantsProductRestrictedDownload)) {
        downloadlogin();
    }
    if ($wantsProductRestrictedDownload) {
        $serviceID = (int) App::get_req_var("serviceid");
        if ($serviceID) {
            $servicesWhere = ["tblhosting.id" => $serviceID, "userid" => $authenticatedClientId, "tblhosting.domainstatus" => "Active"];
            $addonsWhere = ["tblhostingaddons.hostingid" => $serviceID, "tblhosting.userid" => $authenticatedClientId, "tblhostingaddons.status" => "Active"];
        } else {
            $servicesWhere = ["userid" => $authenticatedClientId, "tblhosting.domainstatus" => "Active"];
            $addonsWhere = ["tblhosting.userid" => $authenticatedClientId, "tblhostingaddons.status" => "Active"];
        }
        $allowAccess = false;
        $supportAndUpdatesAddons = [];
        $result = select_query("tblhosting", "tblhosting.id,tblproducts.id AS productid,tblproducts.servertype,tblproducts.configoption7", $servicesWhere, "", "", "", "tblproducts ON tblproducts.id=tblhosting.packageid");
        $productDownloads = [];
        while ($data = mysql_fetch_array($result)) {
            $productServiceID = $data["id"];
            $productModule = $data["servertype"];
            $supportAndUpdatesAddon = $data["configoption7"];
            if (!isset($productDownloads[$data["productid"]])) {
                $value = WHMCS\Database\Capsule::table("tblproduct_downloads")->where("product_id", $data["productid"])->pluck("download_id");
                if ($value instanceof Illuminate\Support\Collection) {
                    $value = $value->toArray();
                } else {
                    if (!$value || !is_array($value)) {
                        $value = [];
                    }
                }
                $productDownloads[$data["productid"]] = $value;
            }
            $productDownloadsArray = $productDownloads[$data["productid"]];
            if (in_array($downloadID, $productDownloadsArray)) {
                if ($productModule == "licensing" && $supportAndUpdatesAddon && $supportAndUpdatesAddon != "0|None") {
                    $parts = explode("|", $supportAndUpdatesAddon);
                    $requiredAddonID = (int) $parts[0];
                    if ($requiredAddonID) {
                        $supportAndUpdatesAddons[$productServiceID] = $requiredAddonID;
                    }
                } else {
                    $allowAccess = true;
                }
            }
        }
        if (!$allowAccess) {
            $result = select_query("tblhostingaddons", "DISTINCT tbladdons.id,tbladdons.downloads", $addonsWhere, "", "", "", "tbladdons ON tbladdons.id=tblhostingaddons.addonid INNER JOIN tblhosting ON tblhosting.id=tblhostingaddons.hostingid");
            while ($data = mysql_fetch_array($result)) {
                $addondownloads = $data["downloads"];
                $addondownloads = explode(",", $addondownloads);
                if (in_array($downloadID, $addondownloads)) {
                    $allowAccess = true;
                }
            }
        }
        if (!$allowAccess && count($supportAndUpdatesAddons)) {
            foreach ($supportAndUpdatesAddons as $productServiceID => $requiredAddonID) {
                $requiredAddonName = get_query_val("tbladdons", "name", ["id" => $requiredAddonID]);
                $where = "tblhosting.userid='" . $authenticatedClientId . "' AND tblhostingaddons.status='Active' AND (tblhostingaddons.name='" . db_escape_string($requiredAddonName) . "' OR tblhostingaddons.addonid='" . $requiredAddonID . "')";
                if ($serviceID) {
                    $where .= " AND tblhosting.id='" . $serviceID . "'";
                }
                $addonCount = get_query_val("tblhostingaddons", "COUNT(tblhostingaddons.id)", $where, "", "", "", "tblhosting ON tblhosting.id=tblhostingaddons.hostingid");
                if ($addonCount) {
                    $allowAccess = true;
                    if (!$allowAccess) {
                        if ($serviceID) {
                            $productServiceID = $serviceID;
                            $requiredAddonID = $supportAndUpdatesAddons[$serviceID];
                        }
                        $pagetitle = Lang::trans("downloadstitle");
                        $breadcrumbnav = "<a href=\"" . WHMCS\Config\Setting::getValue("SystemURL") . "/index.php\">" . Lang::trans("globalsystemname") . "</a> > <a href=\"" . routePath("download-index") . "\">" . Lang::trans("downloadstitle") . "</a>";
                        $pageicon = "";
                        $displayTitle = Lang::trans("supportAndUpdatesExpired");
                        $tagline = "";
                        initialiseClientArea($pagetitle, $displayTitle, $tagline, $pageicon, $breadcrumbnav);
                        $smartyvalues["reason"] = "supportandupdates";
                        $smartyvalues["serviceid"] = $productServiceID;
                        $smartyvalues["licensekey"] = get_query_val("tblhosting", "domain", ["id" => $productServiceID]);
                        $smartyvalues["addonid"] = $requiredAddonID;
                        Menu::addContext("topFiveDownloads", WHMCS\Download\Download::topDownloads()->get());
                        Menu::primarySidebar("downloadList");
                        Menu::secondarySidebar("downloadList");
                        outputClientArea("downloaddenied", false, [], $smartyvalues);
                        exit;
                    }
                }
            }
        }
        if (!$allowAccess) {
            $pagetitle = Lang::trans("downloadstitle");
            $breadcrumbnav = "<a href=\"" . WHMCS\Config\Setting::getValue("SystemURL") . "/index.php\">" . Lang::trans("globalsystemname") . "</a> > <a href=\"" . routePath("download-index") . "\">" . Lang::trans("downloadstitle") . "</a>";
            $pageicon = "";
            $displayTitle = Lang::trans("accessdenied");
            $tagline = "";
            initialiseClientArea($pagetitle, $displayTitle, $tagline, $pageicon, $breadcrumbnav);
            if ($serviceID) {
                $productsWithMatchingDownload = WHMCS\Product\Product::whereHas("productDownloads", function ($query) use($downloadID) {
                    $download = new WHMCS\Download\Download();
                    $query->where($download->getTable() . ".id", $downloadID);
                })->whereHas("services", function ($query) use($serviceID) {
                    $service = new WHMCS\Service\Service();
                    $query->where($service->getTable() . ".id", $serviceID);
                })->get();
            } else {
                $productsWithMatchingDownload = WHMCS\Product\Product::whereHas("productDownloads", function ($query) use($downloadID) {
                    $download = new WHMCS\Download\Download();
                    $query->where($download->getTable() . ".id", $downloadID);
                })->orderBy("hidden")->orderBy("order")->get();
            }
            $smartyvalues["pid"] = "";
            $smartyvalues["prodname"] = "";
            if (!$productsWithMatchingDownload->isEmpty()) {
                $smartyvalues["pid"] = $productsWithMatchingDownload->first()->id;
                $smartyvalues["prodname"] = $productsWithMatchingDownload->first()->name;
            }
            $smartyvalues["aid"] = "";
            $smartyvalues["addonname"] = "";
            $result = select_query("tbladdons", "id,name,downloads", ["downloads" => ["sqltype" => "NEQ", "value" => ""]]);
            while ($data = mysql_fetch_array($result)) {
                $downloads = $data["downloads"];
                $downloads = explode(",", $downloads);
                if (in_array($downloadID, $downloads)) {
                    $smartyvalues["aid"] = $data["id"];
                    $smartyvalues["addonname"] = $data["name"];
                }
            }
            if (!$smartyvalues["prodname"] && !$smartyvalues["addonname"]) {
                $smartyvalues["prodname"] = "Unable to Determine Required Product. Please contact support.";
            }
            $smartyvalues["reason"] = "accessdenied";
            Menu::addContext("topFiveDownloads", WHMCS\Download\Download::topDownloads()->get());
            Menu::primarySidebar("downloadList");
            Menu::secondarySidebar("downloadList");
            outputClientArea("downloaddenied", false, [], $smartyvalues);
            exit;
        }
    }
    update_query("tbldownloads", ["downloads" => "+1"], ["id" => $id]);
    $storage = Storage::downloads();
    $file_name = $filename;
    $display_name = $filename;
    dlservefile($storage, $file_name, $display_name);
}
