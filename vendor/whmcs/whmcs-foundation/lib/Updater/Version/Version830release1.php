<?php

namespace WHMCS\Updater\Version;

class Version830release1 extends IncrementalVersion
{
    protected $updateActions = ["renameShowNotesFieldOnCheckoutColumn", "updateServiceRenewalFailedEmail", "convertStatusesToTranslatableStringsForMailImport"];

    public function renameShowNotesFieldOnCheckoutColumn()
    {
        \WHMCS\Database\Capsule::table("tblconfiguration")->where("setting", "=", "ShowNotesFieldonCheckout")->update(["setting" => "ShowNotesFieldOnCheckout"]);
        return $this;
    }

    public function convertStatusesToTranslatableStringsForMailImport()
    {
        $map = ["successfulNew" => "Ticket Imported Successfully", "successfulReply" => "Ticket Reply Imported Successfully", "failedTicketImport" => "Ticket Import Unsuccessful", "blockedEmailLoop" => "Blocked Potential Email Loop", "deptNotFound" => "Department Not Found", "ticketNotFound" => "Ticket ID Not Found", "unregisteredEmailAddress" => "Ticket ID Not Found", "senderNotAuthorized" => "Sender not authorized to interact with Ticket.", "rateLimited" => "Exceeded Limit of 10 Tickets within 15 Minutes", "unregisteredUser" => "Blocked Ticket Opening from Unregistered User", "autoResponder" => "Prevented an Auto Responder from Opening a Ticket", "reopenViaEmail" => "Ticket Reopen via Email Stopped", "missingSender" => "Skipped importing due to missing from data", "abortedByHook" => "Skipped importing due to hook", "spamPhrase" => "Blocked Phrase", "spamSubject" => "Blocked Subject", "spamSender" => "Blocked Sender", "rejectedByOperator" => "Rejected by Operator"];
        foreach ($map as $newKey => $oldString) {
            \WHMCS\Database\Capsule::table("tblticketmaillog")->where("status", "=", $oldString)->limit(1000)->orderBy("id", "DESC")->update(["status" => $newKey]);
        }
        return $this;
    }

    protected function updateServiceRenewalFailedEmail()
    {
        $updatedMessage = "<p>\n    An automatic renewal attempt was triggered for this service but failed and\n     the system will not attempt it again automatically. Resolve the error and try again.\n</p>\n<p>\n    Client ID: {\$client_id}<br />\n    Service ID: {\$service_id}<br />\n    Product/Service: {\$service_product}<br />\n    Domain: {\$service_domain}<br />{if \$addon_id}\n    Addon ID: {\$addon_id}<br />\n    Addon: {\$addon_name}<br />\n    {/if}Error: {\$error_msg}\n</p>\n<p>\n    <a href=\"{\$whmcs_admin_url}/clientsservices.php?userid={\$client_id}&id={\$service_id}{if \$addon_id}&aid={\$addon_id}{/if}\">\n        Go to {if \$addon_id}addon{else}service{/if}\n    </a>\n</p>";
        $previousTemplateMd5 = "072f487a61bbb82a20b68e252e236774";
        $existingTemplate = \WHMCS\Mail\Template::master()->where("name", "Service Renewal Failed")->get();
        foreach ($existingTemplate as $existing) {
            if (md5($existing->message) === $previousTemplateMd5) {
                $existing->message = $updatedMessage;
                $existing->save();
            }
        }
        return $this;
    }
}
