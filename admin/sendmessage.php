<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Mass Mail", false);
$aInt->title = $aInt->lang("sendmessage", "sendmessagetitle");
$aInt->sidebar = "utilities";
$aInt->icon = "massmail";
ob_start();
$preaction = $whmcs->getFromRequest("preaction");
$showform = false;
$errors = [];
$action = $whmcs->getFromRequest("action");
$multiple = App::getFromRequest("multiple");
$type = App::getFromRequest("type");
$selectedclients = App::getFromRequest("selectedclients");
$attachments = [];
$campaign = NULL;
$campaignId = NULL;
$schedule = "now";
if (!is_array($selectedclients)) {
    $selectedclients = [];
}
$massMailConfiguration = App::getFromRequest("massmailconfiguration");
if ($massMailConfiguration) {
    $massMailConfiguration = json_decode(base64_decode($massMailConfiguration), true);
}
if (!is_array($massMailConfiguration)) {
    $massMailConfiguration = [];
}
if (App::isInRequest("campaign")) {
    $campaign = NULL;
    $campaignId = (int) App::getFromRequest("campaign");
    if ($campaignId) {
        try {
            $campaign = WHMCS\Mail\Campaign::findOrFail($campaignId);
            if ($campaign->completed || $campaign->started && !$campaign->paused) {
                throw new InvalidArgumentException();
            }
            $massMailConfiguration = $campaign->configuration;
            $massMailConfiguration["campaign_id"] = $campaign->id;
            $massMailConfiguration["campaign_name"] = $campaign->name;
            $messageData = $campaign->messageData;
            $subject = $messageData["subject"];
            $message = $messageData["message"];
            $fromname = $messageData["fromName"];
            $fromemail = $messageData["fromEmail"];
            $cc = $messageData["copyTo"];
            $bcc = $messageData["blindCopyTo"];
            $emailoptout = $massMailConfiguration["email_opt_out"];
            $startDate = $campaign->sendingStartAt->toAdminDateTimeFormat();
            if ($campaign->sendingStartAt->isFuture()) {
                $schedule = "future";
            }
        } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            WHMCS\Terminus::getInstance()->doDie(AdminLang::trans("global.invalidaccessattempt"));
        } catch (Exception $e) {
            App::redirectToRoutePath("admin-utilities-tools-email-campaigns", [], ["uneditable" => "true"]);
        }
    }
}
if ($action == "send") {
    check_token("WHMCS.admin.default");
    $emailType = App::getFromRequest("type");
    $campaignName = "Campaign Name";
    if ($massMailConfiguration && !empty($massMailConfiguration["email_type"])) {
        $emailType = $massMailConfiguration["email_type"];
    }
    if ($massMailConfiguration && !empty($massMailConfiguration["campaign_name"])) {
        $campaignName = $massMailConfiguration["campaign_name"];
        unset($massMailConfiguration["campaign_name"]);
    }
    if ($massMailConfiguration && !empty($massMailConfiguration["campaign_id"])) {
        $campaignId = $massMailConfiguration["campaign_id"];
        unset($massMailConfiguration["campaign_id"]);
    }
    $save = $whmcs->getFromRequest("save");
    $savename = $whmcs->getFromRequest("savename");
    $message = $whmcs->getFromRequest("message");
    $subject = $whmcs->getFromRequest("subject");
    $fromname = $whmcs->getFromRequest("fromname");
    $fromemail = $whmcs->getFromRequest("fromemail");
    $startDate = App::getFromRequest("start_date");
    $cc = explode(",", $whmcs->getFromRequest("cc"));
    $bcc = explode(",", $whmcs->getFromRequest("bcc"));
    $templateAttachments = [];
    $id = (int) App::getFromRequest("id");
    if (empty($step)) {
        if (!$message) {
            $errors[] = AdminLang::trans("sendmessage.validationerrormsg");
        }
        if (!$subject) {
            $errors[] = AdminLang::trans("sendmessage.validationerrorsub");
        }
        if (!$fromemail) {
            $errors[] = AdminLang::trans("sendmessage.validationerroremail");
        }
        if (!$fromname) {
            $errors[] = AdminLang::trans("sendmessage.validationerrorname");
        }
        if ($save == "on" && !$savename) {
            $errors[] = AdminLang::trans("sendmessage.noSaveNameEntered");
        } else {
            if ($save == "on" && WHMCS\Mail\Template::where("name", "=", $savename)->first()) {
                $errors[] = AdminLang::trans("sendmessage.uniqueSaveNameRequired");
            }
        }
        if ($save == "on") {
            foreach (WHMCS\File\Upload::getUploadedFiles("attachments") as $uploadedFile) {
                try {
                    $templateAttachments[] = $uploadedFile->storeAsEmailTemplateAttachment();
                } catch (Exception $e) {
                    $errors[] = "Could not save file: " . $e->getMessage();
                }
            }
        }
    }
    if ($errors) {
        $showform = true;
    } else {
        $done = false;
        $additionalMergeFields = [];
        if ($save == "on") {
            $saveType = $emailType;
            if ($emailType == "addon") {
                $saveType = "product";
            }
            $template = new WHMCS\Mail\Template();
            $template->type = $saveType;
            $template->name = $savename;
            $template->subject = WHMCS\Input\Sanitize::decode($subject);
            $template->message = WHMCS\Input\Sanitize::decode($message);
            $template->fromName = $fromname;
            $template->fromEmail = $fromemail;
            $template->copyTo = $cc;
            $template->blindCopyTo = $cc;
            $template->custom = true;
            $template->attachments = $templateAttachments;
            $template->save();
            echo "<p>" . $aInt->lang("sendmessage", "msgsavedsuccess") . "</p>";
        }
        foreach (WHMCS\File\Upload::getUploadedFiles("attachments") as $uploadedFile) {
            try {
                $filename = $uploadedFile->storeAsEmailAttachment();
                $attachments[] = ["path" => Storage::emailAttachments()->getAdapter()->getPathPrefix() . $filename, "filename" => $filename, "displayname" => $uploadedFile->getCleanName()];
            } catch (Exception $e) {
                $aInt->gracefulExit("Could not save file: " . $e->getMessage());
            }
        }
        if ($massMailConfiguration && is_array($massMailConfiguration)) {
            $submitType = App::getFromRequest("submit");
            $massMailConfiguration["email_opt_out"] = App::getFromRequest("emailoptout");
            $oneRecipient = WHMCS\Mail\MassMail\Recipients::factory($massMailConfiguration, 0, 1);
            $massMailConfiguration["total_recipients"] = $oneRecipient->getTotalRecipients();
            $templateData = ["type" => $emailType, "subject" => $subject, "message" => $message, "fromName" => $fromname, "fromEmail" => $fromemail, "copyTo" => $cc, "blindCopyTo" => $bcc, "temporaryAttachments" => $attachments];
            if ($campaignId) {
                $campaign = WHMCS\Mail\Campaign::findOrFail($campaignId);
                $campaign->draft = false;
            } else {
                $campaign = new WHMCS\Mail\Campaign();
                $campaign->adminId = (int) WHMCS\Session::get("adminid");
            }
            $campaign->name = $campaignName;
            if ($startDate) {
                $startDate = WHMCS\Carbon::parseDateRangeValue($startDate, true);
                $campaign->sendingStartAt = $startDate["from"];
            }
            $queryParams = ["created" => "true"];
            if ($submitType === "draft") {
                $campaign->draft = true;
                $queryParams = ["draft" => "true"];
            }
            if ($campaignId) {
                $queryParams["updated"] = "true";
            }
            $campaign->configuration = $massMailConfiguration;
            $campaign->messageData = $templateData;
            $campaign->save();
            App::redirectToRoutePath("admin-utilities-tools-email-campaigns", [], $queryParams);
        } else {
            if ($id) {
                $template = new WHMCS\Mail\Template();
                $template->type = $emailType;
                $template->subject = WHMCS\Input\Sanitize::decode($subject);
                $template->message = WHMCS\Input\Sanitize::decode($message);
                $template->fromName = $fromname;
                $template->fromEmail = $fromemail;
                $template->copyTo = $cc;
                $template->blindCopyTo = $bcc;
                sendMessage($template, $id, [], true, $attachments);
                echo "<p><b>" . $aInt->lang("sendmessage", "sendingcompleted") . "</b></p>";
            }
        }
    }
} else {
    $showform = true;
}
if ($showform) {
    $todata = [];
    $hundredRecipients = NULL;
    if (!$type) {
        $type = "general";
    }
    if ($type == "massmail" || $multiple) {
        if (empty($startDate)) {
            $startDate = WHMCS\Carbon::now()->toAdminDateTimeFormat();
        }
        if (App::isInRequest("start_date") && App::getFromRequest("start_date")) {
            $startDate = App::getFromRequest("start_date");
        }
        $aInt->title = AdminLang::trans("utilities.emailCampaigns.title");
        $stepLang = AdminLang::trans("global.stepOfStep", [":step" => 2, ":steps" => 2]);
        $composeMessageText = AdminLang::trans("massmail.composemsg");
        echo "<h2>" . $stepLang . ": " . $composeMessageText . "</h2>";
        if (!$campaign && !$massMailConfiguration) {
            $sendForEach = (bool) App::getFromRequest("sendforeach");
            $massMailConfiguration = ["campaign_name" => App::getFromRequest("campaign_name"), "email_type" => $type != "massmail" ? $type : App::getFromRequest("emailtype"), "selected_ids" => $selectedclients, "client_status" => App::getFromRequest("clientstatus") ?: [], "client_group" => App::getFromRequest("clientgroup") ?: [], "client_country" => App::getFromRequest("clientcountry") ?: [], "client_language" => App::getFromRequest("clientlanguage") ?: [], "custom_fields" => App::getFromRequest("customfield") ?: [], "package_ids" => App::getFromRequest("productids") ?: [], "product_statuses" => App::getFromRequest("productstatus") ?: [], "servers" => App::getFromRequest("server") ?: [], "send_for_each" => $sendForEach, "addon_ids" => App::getFromRequest("addonids") ?: [], "addon_statuses" => App::getFromRequest("addonstatus") ?: [], "domain_statuses" => App::getFromRequest("domainstatus") ?: []];
        } else {
            if (!$campaign) {
                $sendForEach = $massMailConfiguration["send_for_each"];
            }
        }
        if (empty($massMailConfiguration["email_type"])) {
            $massMailConfiguration["email_type"] = "general";
        }
        $hundredRecipients = WHMCS\Mail\MassMail\Recipients::factory($massMailConfiguration, 0, 100);
        $listedUsers = [];
        foreach ($hundredRecipients->getRecipients() as $recipient) {
            $client = $recipient;
            if (!$client instanceof WHMCS\User\Client) {
                $client = $recipient->client;
            }
            if (!$client && $recipient instanceof WHMCS\Service\Addon) {
                $client = $recipient->service->client;
            }
            if ($client) {
                if ($sendForEach || !$sendForEach && !in_array($client->id, $listedUsers)) {
                    $tempTo = $client->fullName;
                    if (($recipient instanceof WHMCS\Domain\Domain || $recipient instanceof WHMCS\Service\Service) && $recipient->domain) {
                        $tempTo .= " - " . $recipient->domain;
                    }
                    $tempTo .= " - " . $client->email;
                    $listedUsers[] = $client->id;
                    $todata[] = $tempTo;
                }
            }
        }
    } else {
        $id = (int) App::get_req_var("id");
        if ($resend) {
            $result = select_query("tblemails", "", ["id" => $emailid]);
            $data = mysql_fetch_array($result);
            $id = $data["userid"];
            $subject = $data["subject"];
            $message = $data["message"];
            $message = str_replace("<p><a href=\"" . $CONFIG["Domain"] . "\" target=\"_blank\"><img src=\"" . $whmcs->getLogoUrlForEmailTemplate() . "\" alt=\"" . $CONFIG["CompanyName"] . "\" border=\"0\"></a></p>", "", $message);
            $message = str_replace("<p><a href=\"" . $CONFIG["Domain"] . "\" target=\"_blank\"><img src=\"" . $whmcs->getLogoUrlForEmailTemplate() . "\" alt=\"" . $CONFIG["CompanyName"] . "\" border=\"0\" /></a></p>", "", $message);
            $message = str_replace(WHMCS\Input\Sanitize::decode($CONFIG["EmailGlobalHeader"]), "", $message);
            $message = str_replace(WHMCS\Input\Sanitize::decode($CONFIG["EmailGlobalFooter"]), "", $message);
            $headerMarkerPos = strpos($message, WHMCS\Mail\Message::HEADER_MARKER);
            if ($headerMarkerPos !== false) {
                $message = substr($message, $headerMarkerPos + strlen(WHMCS\Mail\Message::HEADER_MARKER));
            }
            $footerMarkerPos = strpos($message, WHMCS\Mail\Message::FOOTER_MARKER);
            if ($footerMarkerPos !== false) {
                $message = substr($message, 0, $footerMarkerPos);
            }
            $styleend = strpos($message, "</style>");
            if ($styleend !== false) {
                $message = trim(substr($message, $styleend + 8));
            }
            $type = "general";
        }
        if ($type == "general") {
            $result = select_query("tblclients", "", ["id" => $id]);
            $data = mysql_fetch_array($result);
            if ($data["email"]) {
                $todata[] = $data["firstname"] . " " . $data["lastname"] . " &lt;" . $data["email"] . "&gt;";
            }
        } else {
            if ($type == "product") {
                $result = select_query("tblclients", "tblclients.id,tblclients.firstname,tblclients.lastname,tblclients.email,tblhosting.domain", ["tblhosting.id" => $id], "", "", "", "tblhosting on tblclients.id = tblhosting.userid");
                $data = mysql_fetch_array($result);
                if ($data["email"]) {
                    $todata[] = $data["firstname"] . " " . $data["lastname"] . " - " . $data["domain"] . " &lt;" . $data["email"] . "&gt;";
                }
            } else {
                if ($type == "domain") {
                    $result = select_query("tblclients", "tblclients.id,tblclients.firstname,tblclients.lastname,tblclients.email,tbldomains.domain", ["tbldomains.id" => $id], "", "", "", "tbldomains on tblclients.id = tbldomains.userid");
                    $data = mysql_fetch_array($result);
                    if ($data["email"]) {
                        $todata[] = $data["firstname"] . " " . $data["lastname"] . " - " . $data["domain"] . " &lt;" . $data["email"] . "&gt;";
                    }
                }
            }
        }
    }
    $numRecipients = count($todata);
    if (!is_null($hundredRecipients)) {
        $numRecipients = $hundredRecipients->getTotalRecipients();
    }
    if (!$numRecipients) {
        WHMCS\Session::set("MassMailConfiguration", $massMailConfiguration);
        App::redirect("massmail.php");
    }
    if ($errors) {
        echo infoBox(AdminLang::trans("sendmessage.validationerrortitle"), implode("<br />", $errors));
    }
    if (isset($sub) && $sub == "loadmessage") {
        $language = !$massMailConfiguration && (int) $data["id"] ? get_query_val("tblclients", "language", ["id" => $data["id"]]) : "";
        $messageName = $whmcs->get_req_var("messagename");
        $template = WHMCS\Mail\Template::where("name", "=", $messageName)->where("language", "=", $language)->get()->first();
        if (is_null($template)) {
            $template = WHMCS\Mail\Template::where("name", "=", $messageName)->get()->first();
        }
        $subject = $template->subject;
        $message = $template->message;
        $fromname = $template->fromName;
        $fromemail = $template->fromEmail;
        $plaintext = $template->plaintext;
        if ($plaintext) {
            $message = nl2br($message);
        }
    }
    echo "\n<form method=\"post\" action=\"";
    echo $whmcs->getPhpSelf();
    echo "\" name=\"frmmessage\"\n    id=\"sendmsgfrm\" enctype=\"multipart/form-data\">\n    <input type=\"hidden\" name=\"action\" value=\"send\" /> <input type=\"hidden\"\n        name=\"type\" value=\"";
    echo $type === "massmail" ? $emailtype : $type;
    echo "\" />\n";
    if ($massMailConfiguration) {
        $hiddenOutput = base64_encode(json_encode($massMailConfiguration));
        echo "<input type=\"hidden\" name=\"massmailconfiguration\" value=\"" . $hiddenOutput . "\">";
    }
    if ($massMailConfiguration && !$multiple) {
        echo "<input type=\"hidden\" name=\"sendforeach\" value=\"" . $sendforeach . "\" />";
    } else {
        if ($multiple) {
            echo "<input type=\"hidden\" name=\"multiple\" value=\"true\" />";
        } else {
            echo "<input type=\"hidden\" name=\"id\" value=\"" . $id . "\" />";
        }
    }
    echo "\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"140\" class=\"fieldlabel\">";
    echo $aInt->lang("emails", "from");
    echo "</td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" class=\"form-control input-200 input-inline\" name=\"fromname\" value=\"";
    if (empty($fromname)) {
        echo $CONFIG["CompanyName"];
    } else {
        echo $fromname;
    }
    echo "\">\n                <input type=\"text\" name=\"fromemail\" class=\"form-control input-400 input-inline\" value=\"";
    if (empty($fromemail)) {
        echo $CONFIG["Email"];
    } else {
        echo $fromemail;
    }
    echo "\">\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">";
    echo $aInt->lang("emails", "recipients");
    echo "</td>\n            <td class=\"fieldarea\"><table cellspacing=\"0\" cellpadding=\"0\">\n                    <tr>\n                        <td>";
    echo "<select class=\"form-control\" size=\"4\" style=\"width:450px;\"><option>" . $numRecipients . " recipients matched sending criteria.";
    if (50 < $numRecipients) {
        echo " Showing first 50 only...";
    }
    echo "</option>";
    foreach ($todata as $i => $to) {
        echo "<option>" . $to . "</option>";
        if (49 < $i) {
            echo "</select></td>\n                        <td> &nbsp; ";
            echo $aInt->lang("sendmessage", "emailsentindividually1");
            echo "<br /> &nbsp; ";
            echo $aInt->lang("sendmessage", "emailsentindividually2");
            echo "</td>\n\n                </table></td>\n            </td>\n        </tr>\n    ";
            if ($type != "massmail") {
                $ccText = AdminLang::trans("emails.cc");
                $bccText = AdminLang::trans("emails.bcc");
                $commaText = AdminLang::trans("sendmessage.commaseparateemails");
                echo "        <tr>\n            <td class=\"fieldlabel\">" . $ccText . "</td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"cc\" class=\"form-control input-600 input-inline\" value=\"\"> " . $commaText . "\n            </td>\n        </tr>\n        <tr>\n            <td class=\"fieldlabel\">" . $bccText . "</td>\n            <td class=\"fieldarea\">\n                <input type=\"text\" name=\"bcc\" class=\"form-control input-600 input-inline\" value=\"\"> " . $commaText . "\n            </td>\n        </tr>";
            }
            echo "        <tr>\n            <td class=\"fieldlabel\">Subject</td>\n            <td class=\"fieldarea\">\n                <div class=\"form-group no-margin\">\n                    <input type=\"text\" name=\"subject\"  class=\"form-control\" value=\"";
            echo $subject ?? "";
            echo "\" id=\"subject\">\n                    <span class=\"field-error-msg\">";
            echo AdminLang::trans("validation.filled", [":attribute" => AdminLang::trans("fields.subject")]);
            echo "</span>\n                </div>\n            </td>\n        </tr>\n    </table>\n\n    <script langauge=\"javascript\">\nfrmmessage.subject.select();\n</script>\n\n    <div class=\"form-group no-margin\">\n        <textarea name=\"message\" id=\"email_msg1\" rows=\"25\" class=\"tinymce form-control\">";
            echo $message ?? "";
            echo "</textarea>\n        <span class=\"field-error-msg\">";
            echo AdminLang::trans("sendmessage.validationerrormsg");
            echo "</span>\n    </div>\n    <div class=\"top-margin-5\">\n        <a href=\"";
            echo routePath("admin-utilities-tools-email-campaigns-preview");
            echo "\"\n           class=\"btn btn-default open-modal\"\n           data-modal-size=\"modal-lg\"\n           data-modal-title=\"";
            echo escape(AdminLang::trans("sendmessage.preview"));
            echo "\"\n        >\n            ";
            echo AdminLang::trans("sendmessage.preview");
            echo "        </a>\n        <button id=\"btnToggleEditor\" type=\"button\" class=\"btn btn-default pull-right\" onclick=\"toggleEditor();\">\n            ";
            echo AdminLang::trans("emailtpls.rteditor");
            echo "        </button>\n    </div>\n    <br />\n\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"140\" class=\"fieldlabel\">";
            echo $aInt->lang("support", "attachments");
            echo "</td>\n            <td class=\"fieldarea\">\n                <input type=\"file\" name=\"attachments[]\" style=\"width: 60%;\" /> <a\n                href=\"#\" id=\"addfileupload\"><img src=\"images/icons/add.png\"\n                    align=\"absmiddle\" border=\"0\" /> ";
            echo $aInt->lang("support", "addmore");
            echo "</a><br />\n            <div id=\"fileuploads\"></div></td>\n        </tr>\n";
            if ($massMailConfiguration) {
                echo "<tr>\n            <td class=\"fieldlabel\">";
                echo $aInt->lang("sendmessage", "marketingemail");
                echo "</td>\n            <td class=\"fieldarea\">\n                <label class=\"checkbox-inline\">\n                    <input type=\"checkbox\" id=\"emailoptout\" name=\"emailoptout\"";
                echo !empty($emailoptout) ? " checked=\"checked\"" : "";
                echo ">\n                    ";
                echo $aInt->lang("sendmessage", "dontsendemailunsubscribe");
                echo "                </label>\n            </td>\n        </tr>\n";
            }
            if (checkPermission("Create/Edit Email Templates", true)) {
                echo "<tr>\n            <td class=\"fieldlabel\">";
                echo $aInt->lang("sendmessage", "savemesasge");
                echo "</td>\n            <td class=\"fieldarea\"><label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"save\"";
                echo isset($save) && $save == "on" ? " checked" : "";
                echo "> ";
                echo $aInt->lang("sendmessage", "entersavename");
                echo ":</label>\n                <input type=\"text\" name=\"savename\" class=\"form-control input-300 input-inline\" value=\"";
                echo !empty($savename) ? $savename : "";
                echo "\">\n            </td>\n        </tr>";
            }
            echo "    </table>\n    ";
            if ($massMailConfiguration) {
                echo "        <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n            <tr>\n                <td class=\"fieldlabel\" width=\"140\">\n                    ";
                echo AdminLang::trans("fields.startDate");
                echo "                </td>\n                <td class=\"fieldarea\">\n                    <div class=\"checkbox\">\n                        <label>\n                            <input type=\"radio\" name=\"schedule\"";
                echo $schedule == "now" ? " checked=\"checked\"" : "";
                echo " value=\"now\">\n                            ";
                echo AdminLang::trans("utilities.emailCampaigns.immediately");
                echo "                        </label>\n                    </div>\n                    <div class=\"checkbox\">\n                        <label>\n                            <input type=\"radio\" name=\"schedule\"";
                echo $schedule == "future" ? " checked=\"checked\"" : "";
                echo " value=\"future\">\n                            ";
                echo AdminLang::trans("utilities.emailCampaigns.future");
                echo "                        </label>\n                    </div>\n\n                </td>\n            </tr>\n            <tr id=\"rowFutureDate\"";
                echo $schedule == "now" ? " class=\"hidden\"" : "";
                echo ">\n                <td class=\"fieldlabel\"></td>\n                <td class=\"fieldarea\">\n                    <div class=\"form-group date-picker-prepend-icon\">\n                        <label for=\"inputStartDate\" class=\"field-icon\">\n                            <i class=\"fal fa-calendar-alt\"></i>\n                        </label>\n                        <input id=\"inputStartDate\" type=\"text\" name=\"start_date\" class=\"form-control input-inline date-picker-single time future\" data-original-value=\"";
                echo $startDate ?: "";
                echo "\" value=\"";
                echo $startDate ?: "";
                echo "\">\n                        ";
                echo AdminLang::trans("utilities.emailCampaigns.startDate");
                echo "                    </div>\n                </td>\n            </tr>\n        </table>\n    ";
            }
            echo "\n    <div class=\"btn-container\">\n        ";
            if ($massMailConfiguration) {
                echo "            <button id=\"btnSaveDraft\" type=\"submit\" class=\"btn btn-default\" name=\"submit\" value=\"draft\">\n                ";
                if ($campaignId) {
                    echo AdminLang::trans("global.savechanges");
                } else {
                    echo AdminLang::trans("utilities.emailCampaigns.createDraft");
                }
                echo "            </button>\n        ";
            }
            echo "        <button id=\"btnSendMessage\" type=\"button\" class=\"btn btn-primary\">\n            ";
            if ($massMailConfiguration) {
                echo "                <span";
                echo $schedule == "now" ? "" : " class=\"hidden\"";
                echo ">\n                    ";
                echo AdminLang::trans("utilities.emailCampaigns.sendCampaign");
                echo "                </span>\n                <span";
                echo $schedule == "future" ? "" : " class=\"hidden\"";
                echo ">\n                    ";
                echo AdminLang::trans("utilities.emailCampaigns.scheduleCampaign");
                echo "                </span>\n            ";
            } else {
                echo AdminLang::trans("global.sendmessage") . "&nbsp;&raquo;";
            }
            echo "        </button>\n        <button id=\"btnSendHidden\" type=\"submit\" class=\"btn hidden\" name=\"submit\" value=\"send\">\n            &nbsp;\n        </button>\n    </div>\n\n</form>\n\n";
            $aInt->richTextEditor("sendMessage");
            echo "<div id=\"emailoptoutinfo\">";
            infoBox($aInt->lang("sendmessage", "marketingemail"), sprintf($aInt->lang("sendmessage", "marketingemaildesc"), "{\$unsubscribe_url}"));
            echo $infobox;
            echo "</div>";
            $i = 1;
            $typeBackup = $type;
            if (isset($emailtype) && $emailtype) {
                $type = $emailtype;
            }
            include "mergefields.php";
            $type = $typeBackup;
            echo "\n<form method=\"post\" action=\"";
            echo $_SERVER["PHP_SELF"];
            echo "\">\n    <input type=\"hidden\" name=\"sub\" value=\"loadmessage\"> <input\n        type=\"hidden\" name=\"type\" value=\"";
            echo $type;
            echo "\">\n";
            if ($massMailConfiguration) {
                $hiddenOutput = base64_encode(json_encode($massMailConfiguration));
                echo "<input type=\"hidden\" name=\"massmailconfiguration\" value=\"" . $hiddenOutput . "\">";
            }
            if ($massMailConfiguration && !$multiple) {
                if ($sendforeach) {
                    echo "<input type=\"hidden\" name=\"sendforeach\" value=\"" . $sendforeach . "\">";
                }
            } else {
                if ($multiple) {
                    echo "<input type=\"hidden\" name=\"multiple\" value=\"true\">";
                    foreach ($selectedclients as $selectedclient) {
                        echo "<input type=\"hidden\" name=\"selectedclients[]\" value=\"" . $selectedclient . "\">";
                    }
                } else {
                    echo "<input type=\"hidden\" name=\"id\" value=\"" . $id . "\">";
                }
            }
            echo "<div class=\"contentbox\">\n        <b>";
            echo $aInt->lang("sendmessage", "loadsavedmsg");
            echo ":</b> <select\n            name=\"messagename\" class=\"form-control select-inline\"><option value=\"\">";
            echo $aInt->lang("sendmessage", "choose");
            echo "...";
            $availableTemplates = [];
            if (isset($emailtype)) {
                $availableTemplates = [$emailtype === "addon" ? "product" : $emailtype];
            }
            if (!isset($availableTemplates["general"])) {
                $availableTemplates[] = "general";
            }
            $templates = WHMCS\Mail\Template::where("language", "=", "")->whereIn("type", $availableTemplates)->orderBy("custom")->orderby("name")->get();
            foreach ($templates as $template) {
                echo "<option style=\"background-color: #ffffff\">" . $template->name . "</option>";
            }
            if ($type != "general") {
                $templates = WHMCS\Mail\Template::where("type", "=", $type)->where("language", "=", "")->orderBy("custom")->orderby("name")->get();
                foreach ($templates as $template) {
                    echo "<option";
                    if (!$template->custom) {
                        echo " style=\"background-color: #efefef\"";
                    }
                    echo ">" . $template->name . "</option>";
                }
            }
            echo "</select> <input type=\"submit\" class=\"btn btn-default\"\n            value=\"";
            echo $aInt->lang("sendmessage", "loadMessage");
            echo "\">\n    </div>\n</form>\n\n";
            $confirmQuestion = AdminLang::trans("utilities.emailCampaigns.confirm");
            echo $aInt->modal("ConfirmCampaign", AdminLang::trans("utilities.emailCampaigns.createNew"), "<div class=\"alert alert-info\">" . $confirmQuestion . "</div>", [["title" => AdminLang::trans("utilities.emailCampaigns.sendCampaign"), "onclick" => "submit_sendmsgfrm();", "class" => "btn-primary"], ["title" => AdminLang::trans("global.cancel"), "class" => "btn-danger"]]);
            $btnSendMessageFunc = $massMailConfiguration ? "jQuery(\"#modalConfirmCampaign\").modal(\"show\");" : "submit_sendmsgfrm();";
            $missingSubjectMessage = AdminLang::trans("sendmessage.validationerrorsub");
            $missingMessageMessage = AdminLang::trans("sendmessage.validationerrormsg");
            $jQueryCode = "jQuery('#addfileupload').click(function () {\n    jQuery(\"#fileuploads\").append(\n        \"<input type=\\\"file\\\" name=\\\"attachments[]\\\" style=\\\"width:70%;\\\" /><br />\"\n    );\n    return false;\n});\njQuery(\"#emailoptoutinfo\").hide();\njQuery(\"#emailoptout\").click(function(){\n    if (this.checked) {\n        jQuery(\"#emailoptoutinfo\").slideDown(\"slow\");\n    } else {\n        jQuery(\"#emailoptoutinfo\").slideUp(\"slow\");\n    }\n});\njQuery('#btnSendMessage').on('click', function (e) {\n    var frm = jQuery('#frmmessage'),\n        inputs = [\n            '#subject',\n            'tiny#email_msg1'\n        ],\n        errors = false;\n    frm.find('.form-group').removeClass('has-error');\n    frm.find('.field-error-msg').hide();\n    \n    inputs.forEach(function(value) {\n        var content = '',\n            input;\n        if (value.substring(0, 4) === 'tiny') {\n            value = value.substring(4);\n            content = editorEnabled ? tinymce.activeEditor.getContent() : jQuery(value).val();\n            input = editorEnabled ? jQuery(value).prev('div') : jQuery(value);\n        } else {\n            input = jQuery(value);\n            content = input.val();\n        }\n        if (content === '') {\n            input.showInputError();\n            if (!errors) {\n                jQuery('html, body').animate({\n                    scrollTop: input.offset().top\n                }, 500);\n                errors = true;\n            }\n        }\n    });\n    if (errors === false) {\n        " . $btnSendMessageFunc . "\n    }\n});\njQuery('input[name=\"schedule\"]').on('change', function(e) {\n    var row = jQuery('#rowFutureDate'),\n        btn = jQuery('#btnSendMessage');\n    if (jQuery(this).val() === 'future') {\n        row.removeClass('hidden');\n    } else {\n        row.addClass('hidden');\n    }\n    btn.find('span').toggleClass('hidden');\n});";
            $jscode = "\nfunction submit_sendmsgfrm() {\n    jQuery(\"#btnSendHidden\").click();\n}";
        }
    }
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jQueryCode;
$aInt->jscode = $jscode;
$aInt->display();
