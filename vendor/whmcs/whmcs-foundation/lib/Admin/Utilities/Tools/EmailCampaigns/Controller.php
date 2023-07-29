<?php

namespace WHMCS\Admin\Utilities\Tools\EmailCampaigns;

class Controller
{
    public function manager($BodyContentWrapper, $request)
    {
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("utilities.emailCampaigns.title"))->setSidebarName("utilities")->setFavicon("massmail")->setHelpLink("Email Campaigns");
        $newlyCreated = (bool) ($request->get("created") === "true");
        $draft = (bool) ($request->get("draft") === "true");
        $updated = (bool) ($request->get("updated") === "true");
        $uneditable = (bool) ($request->get("uneditable") === "true");
        $name = "email-campaigns";
        $orderby = "id";
        $sort = "DESC";
        $pageObj = new \WHMCS\Pagination($name, $orderby, $sort);
        $pageObj->digestCookieData();
        $tbl = new \WHMCS\ListTable($pageObj);
        $tbl->setColumns([\AdminLang::trans("fields.id"), \AdminLang::trans("fields.name"), ["", \AdminLang::trans("fields.status"), "120px"], \AdminLang::trans("fields.startDate"), \AdminLang::trans("utilities.emailCampaigns.sendingProgress"), \AdminLang::trans("fields.completedDate"), \AdminLang::trans("fields.created"), ["", \AdminLang::trans("global.actions"), "240px"]]);
        $data = new Campaigns($pageObj);
        $data->execute([]);
        $campaignData = $pageObj->getData();
        $creatorText = \AdminLang::trans("fields.creator");
        $pauseText = \AdminLang::trans("global.pause");
        $resumeText = \AdminLang::trans("global.resume");
        $deleteText = \AdminLang::trans("global.delete");
        $editText = \AdminLang::trans("global.edit");
        $loading = \AdminLang::trans("global.loading");
        $preview = \AdminLang::trans("sendmessage.preview");
        $manage = \AdminLang::trans("utilities.emailCampaigns.manage");
        foreach ($campaignData as $campaignDatum) {
            $id = $campaignDatum->id;
            $name = $campaignDatum->name;
            if (!$name) {
                $name = "Unnamed Email Campaign";
            }
            $configuration = $campaignDatum->configuration;
            $startDate = $completedDate = $createdDate = "-";
            if (!is_null($campaignDatum->getRawAttribute("sending_start_at"))) {
                $startDate = $campaignDatum->sendingStartAt->toAdminDateTimeFormat();
            }
            if (!is_null($campaignDatum->getRawAttribute("completed_at"))) {
                $completedDate = $campaignDatum->completedAt->toAdminDateTimeFormat();
            }
            if (!is_null($campaignDatum->getRawAttribute("created_at"))) {
                $createdDate = $campaignDatum->createdAt->toAdminDateTimeFormat();
            }
            $status = "status.queued";
            $editButtonClass = "edit";
            $editDisabled = "";
            $pauseResumeButtonClass = "";
            $pauseButtonClass = $pauseResumeDisabled = "";
            $resumeButtonClass = "hidden";
            if ($campaignDatum->draft) {
                $status = "status.draft";
                $pauseResumeButtonClass = "disabled";
                $pauseResumeDisabled = " disabled=\"disabled\"";
            } else {
                if ($campaignDatum->paused) {
                    $status = "status.paused";
                    $resumeButtonClass = "";
                    $pauseButtonClass = "hidden";
                } else {
                    if ($campaignDatum->completed) {
                        $status = "status.completed";
                        $pauseResumeButtonClass = "disabled";
                        $pauseResumeDisabled = " disabled=\"disabled\"";
                        $editButtonClass = "disabled";
                        $editDisabled = " disabled=\"disabled\"";
                    } else {
                        if ($campaignDatum->started) {
                            $status = "status.sending";
                            $editButtonClass = "disabled";
                            $editDisabled = " disabled=\"disabled\"";
                        }
                    }
                }
            }
            if ($campaignDatum->admin) {
                $creator = $campaignDatum->admin->fullName;
            } else {
                $creator = \AdminLang::trans("global.deletedUser");
            }
            $creator = $creatorText . ": " . $creator;
            $type = $configuration["email_type"];
            $type = \AdminLang::trans("utilities.emailCampaigns." . $type);
            $previewUrl = fqdnRoutePath("admin-utilities-tools-email-campaigns-preview-campaign", $id);
            $actions = "<div class=\"btn-group\">\n    <button type=\"button\"\n            class=\"btn btn-default btn-sm dropdown-toggle manage\"\n            data-toggle=\"dropdown\"\n            aria-haspopup=\"true\"\n            aria-expanded=\"false\"\n    >\n        <span class=\"default-text\">\n            " . $manage . "\n            <span class=\"caret\"></span>\n        </span>\n        <span class=\"loading hidden\">\n            <i class=\"fas fa-spinner fa-pulse\"></i> " . $loading . "\n        </span>\n    </button>\n    <ul class=\"dropdown-menu\">\n        <li>\n            <a href=\"#\"\n               data-campaign-id=\"" . $id . "\"\n               class=\"" . $editButtonClass . "\"\n               " . $editDisabled . "\n            >\n                <span class=\"edit-text\">" . $editText . "</span>\n            </a>\n        </li>\n        <li>\n            <a href=\"#\"\n               data-campaign-id=\"" . $id . "\"\n               class=\"pause " . $pauseButtonClass . " " . $pauseResumeButtonClass . "\"\n               " . $pauseResumeDisabled . "\n            >\n                <span class=\"pause-text\">" . $pauseText . "</span>\n            </a>\n        </li>\n        <li>\n            <a href=\"#\"\n               data-campaign-id=\"" . $id . "\"\n               class=\"resume " . $resumeButtonClass . " " . $pauseResumeButtonClass . "\"\n               " . $pauseResumeDisabled . "\n            >\n                <span class=\"resume-text\">" . $resumeText . "</span>\n            </a>\n        </li>\n        <li role=\"separator\" class=\"divider\"></li>\n        <li>\n            <a href=\"" . $previewUrl . "\"\n               class=\"open-modal\"\n               data-modal-size=\"modal-lg\"\n               data-modal-title=\"" . $preview . "\"\n            >\n                " . $preview . "\n            </a>\n        </li>\n        <li>\n            <a href=\"#\"\n               data-campaign-id=\"" . $id . "\"\n               class=\"delete danger\"\n            >\n                <span class=\"delete-text\">" . $deleteText . "</span>\n            </a>\n        </li>\n    </ul>\n</div>";
            $status = \AdminLang::trans($status);
            $queued = $campaignDatum->queue()->count();
            $total = $configuration["total_recipients"];
            $percent = 0;
            if ($queued && $total) {
                $percent = round($queued / $total * 100);
            }
            $reportUri = fqdnRoutePath("admin-utilities-tools-email-campaigns-report", $id);
            $title = \AdminLang::trans("utilities.emailCampaigns.report");
            $text = \AdminLang::trans("utilities.emailCampaigns.viewReport");
            $campaignInfo = "<a href=\"" . $reportUri . "\" class=\"open-modal\"" . "data-modal-title=\"" . $title . "\">" . $text . "</a>";
            $tbl->addRow([(string) $id, "<strong>" . $name . "</strong><br><em>" . $type . "</em><br><small>" . $creator . "</small>", "<span class=\"status\">" . $status . "</span>", (string) $startDate, $queued . " / " . $total . " (" . $percent . "%)<br>" . $campaignInfo, (string) $completedDate, (string) $createdDate, "<div class='text-center'>" . $actions . "</div>"]);
        }
        $pageObj->setBasePath(routePath("admin-utilities-tools-email-campaigns"));
        $content = view("admin.utilities.email-campaigns.manager", ["campaignTableOutput" => $tbl->output(), "newCampaignAdded" => $newlyCreated, "draft" => $draft, "updated" => $updated, "uneditable" => $uneditable]);
        $view->setBodyContent($content);
        return $view;
    }

    public function pause($JsonResponse, $request)
    {
        try {
            $campaign = \WHMCS\Mail\Campaign::findOrFail($request->get("id"));
            if ($campaign->completed) {
                throw new \InvalidArgumentException("A completed campaign cannot be paused");
            }
            $campaign->paused = true;
            $campaign->save();
            $response = ["success" => true, "successMessage" => \AdminLang::trans("utilities.emailCampaigns.campaignPaused"), "status" => \AdminLang::trans("status.paused")];
        } catch (\Exception $e) {
            $response = ["error" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function resume($JsonResponse, $request)
    {
        try {
            $campaign = \WHMCS\Mail\Campaign::findOrFail($request->get("id"));
            if ($campaign->completed) {
                throw new \InvalidArgumentException("A completed campaign cannot be resumed");
            }
            $campaign->paused = false;
            $campaign->save();
            $status = "status.queued";
            if ($campaign->started) {
                $status = "status.sending";
            }
            $response = ["success" => true, "successMessage" => \AdminLang::trans("utilities.emailCampaigns.campaignResumed"), "status" => \AdminLang::trans($status)];
        } catch (\Exception $e) {
            $response = ["error" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function delete($JsonResponse, $request)
    {
        try {
            $campaign = \WHMCS\Mail\Campaign::findOrFail($request->get("id"));
            if ($campaign->trashed()) {
                throw new \InvalidArgumentException("Email campaign already deleted");
            }
            if ($campaign->hasPendingEmails()) {
                $campaign->queue()->where("pending", 1)->delete();
            }
            $campaign->delete();
            $response = ["success" => true, "successMessage" => \AdminLang::trans("utilities.emailCampaigns.campaignDeleted"), "status" => \AdminLang::trans("status.deleted")];
        } catch (\Exception $e) {
            $response = ["error" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function report($JsonResponse, $request)
    {
        try {
            $id = $request->get("id", 0);
            $campaign = \WHMCS\Mail\Campaign::findOrFail($id);
            $configuration = $campaign->configuration;
            if ($campaign->draft) {
                $status = "draft";
                $statusLabel = "default";
            } else {
                if ($campaign->paused) {
                    $status = "paused";
                    $statusLabel = "info";
                } else {
                    if ($campaign->completed) {
                        $status = "completed";
                        $statusLabel = "success";
                    } else {
                        if ($campaign->started) {
                            $status = "sending";
                            $statusLabel = "primary";
                        } else {
                            $status = "queued";
                            $statusLabel = "info";
                        }
                    }
                }
            }
            $response = ["body" => view("admin.utilities.email-campaigns.report", ["campaign" => $campaign, "campaignStatus" => $status, "statusLabel" => $statusLabel, "total" => $configuration["total_recipients"], "sent" => $campaign->queue()->sent()->count(), "pending" => $campaign->queue()->pending()->count(), "failed" => $campaign->queue()->failed()->count(), "failedEmails" => $campaign->queue()->failed()->get()])];
        } catch (\Exception $e) {
            $response = ["errorMsgTitle" => "", "errorMsg" => $e->getMessage(), "dismiss" => true];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function preview(\WHMCS\Http\Message\ServerRequest $request)
    {
        $url = routePath("admin-utilities-tools-email-campaigns-preview-show");
        return new \WHMCS\Http\Message\JsonResponse(["body" => "<script>\n    jQuery(document).ready(function () {\n        jQuery('#modalAjax .loader').show();\n        if (jQuery(\"#email_msg1_ifr\").length !== 0) {\n            jQuery(\"#email_msg1\").val(tinymce.activeEditor.getContent());\n        }\n        WHMCS.http.jqClient.jsonPost(\n            {\n                url: '" . $url . "',\n                data: jQuery(\"#sendmsgfrm\").serialize(),\n                success: function(data) {\n                    updateAjaxModal(data);\n                }\n            }\n        );\n    });\n</script>"]);
    }

    public function showPreview(\WHMCS\Http\Message\ServerRequest $request)
    {
        $relatedId = $request->get("id", 0);
        $type = $request->get("type");
        $massMailConfiguration = $request->get("massmailconfiguration");
        if ($massMailConfiguration) {
            $massMailConfiguration = json_decode(base64_decode($massMailConfiguration), true);
        }
        if (!is_array($massMailConfiguration)) {
            $massMailConfiguration = [];
        }
        if ($massMailConfiguration) {
            $relatedId = 0;
            $recipients = \WHMCS\Mail\MassMail\Recipients::factory($massMailConfiguration, 0, 1)->getRecipients();
            $recipient = current($recipients);
            if ($recipient) {
                $relatedId = $recipient->id;
            }
            $type = $massMailConfiguration["email_type"];
        }
        if ($type == "addon") {
            $type = "product";
        }
        $subject = $request->get("subject");
        $message = $request->get("message");
        $template = new \WHMCS\Mail\Template();
        $template->type = $type;
        $template->subject = \WHMCS\Input\Sanitize::decode($subject);
        $template->message = \WHMCS\Input\Sanitize::decode($message);
        $template->fromName = "";
        $template->fromEmail = "";
        $template->copyTo = [];
        $template->blindCopyTo = [];
        $template->disabled = false;
        $template->custom = false;
        $template->plaintext = false;
        $message = "";
        if ($relatedId) {
            try {
                $emailer = \WHMCS\Mail\Emailer::factoryByTemplate($template, $relatedId);
                $preview = $emailer->preview();
                $message = $preview->getBody();
                $subject = $preview->getSubject();
            } catch (\Exception $e) {
            }
        }
        if (!$message) {
            $message = "No related entities found to preview message. Unable to preview.";
        }
        if (!$subject) {
            $subject = "--No Subject Defined--";
        }
        $content = view("admin.client.profile.view-email", ["to" => \AdminLang::trans("sendmessage.preview"), "cc" => "", "bcc" => "", "subject" => \WHMCS\Input\Sanitize::makeSafeForOutput($subject), "message" => \WHMCS\Input\Sanitize::encode($message), "attachments" => []]);
        return new \WHMCS\Http\Message\JsonResponse(["body" => $content]);
    }

    public function retry(\WHMCS\Http\Message\ServerRequest $request)
    {
        $emailId = $request->get("id");
        $queueItem = NULL;
        try {
            $queueItem = \WHMCS\Mail\Queue::findOrFail($emailId)->send()->refresh();
            $campaign = $queueItem->campaign;
            $sent = $campaign->queue()->sent()->count();
            $failed = $campaign->queue()->failed()->count();
            $total = $campaign->configuration["total_recipients"];
            $response = ["success" => true, "sentEmailsText" => \AdminLang::trans("utilities.emailCampaigns.sentEmails", [":count" => $sent]), "failedEmailsText" => \AdminLang::trans("utilities.emailCampaigns.failedEmails", [":count" => $failed]), "sentCount" => $sent, "failedCount" => $failed, "remainingCount" => $total - ($sent + $failed)];
        } catch (\WHMCS\Exception\Mail\SendHookAbort $e) {
            if ($queueItem) {
                $queueItem->failed = true;
                $queueItem->failureReason = "Email Send Aborted By Hook";
                $queueItem->pending = false;
                $queueItem->save();
            }
            $response = ["success" => false, "failureReason" => $queueItem ? $queueItem->failureReason : $e->getMessage()];
        } catch (\WHMCS\Exception\Mail\EmailSendingDisabled $e) {
            if ($queueItem) {
                $queueItem->failed = true;
                $queueItem->failureReason = "Email Send Aborted By Configuration";
                $queueItem->pending = false;
                $queueItem->save();
            }
            $response = ["success" => false, "failureReason" => $queueItem ? $queueItem->failureReason : $e->getMessage()];
        } catch (\WHMCS\Exception\Mail\InvalidAddress $e) {
            if ($queueItem) {
                $queueItem->failed = true;
                $queueItem->failureReason = "Invalid Address Specified";
                $queueItem->pending = false;
                $queueItem->save();
            }
            $response = ["success" => false, "failureReason" => $queueItem ? $queueItem->failureReason : $e->getMessage()];
        } catch (\Exception $e) {
            if ($queueItem) {
                $queueItem->refresh();
                $queueItem->failed = true;
                $queueItem->retryCount++;
                if (3 <= $queueItem->retryCount) {
                    $queueItem->pending = false;
                }
                $queueItem->save();
            }
            $response = ["success" => false, "failureReason" => $queueItem ? $queueItem->failureReason : $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function previewCampaign(\WHMCS\Http\Message\ServerRequest $request)
    {
        $campaignId = (int) $request->get("id");
        $campaign = \WHMCS\Mail\Campaign::find($campaignId);
        $configuration = $campaign->configuration;
        $messageData = $campaign->messageData;
        $recipients = \WHMCS\Mail\MassMail\Recipients::factory($configuration)->getRecipients();
        $template = \WHMCS\Mail\Template::factoryFromArray($messageData);
        $additionalMergeFields = [];
        if ($template->type == "addon") {
            $template->type = "product";
        }
        $emailer = \WHMCS\Mail\Emailer::factoryByTemplate($template, $recipients[0]->id, $additionalMergeFields);
        $preview = $emailer->preview();
        $message = $preview->getBody();
        $subject = $preview->getSubject();
        $content = view("admin.client.profile.view-email", ["to" => \AdminLang::trans("sendmessage.preview"), "cc" => "", "bcc" => "", "subject" => \WHMCS\Input\Sanitize::makeSafeForOutput($subject), "message" => \WHMCS\Input\Sanitize::encode($message), "attachments" => []]);
        return new \WHMCS\Http\Message\JsonResponse(["body" => $content]);
    }
}
