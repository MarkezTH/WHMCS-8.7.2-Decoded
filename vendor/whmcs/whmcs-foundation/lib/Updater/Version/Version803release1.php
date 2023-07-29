<?php

namespace WHMCS\Updater\Version;

class Version803release1 extends IncrementalVersion
{
    protected $updateActions = ["updateClientUserInviteEmail"];

    protected function updateClientUserInviteEmail()
    {
        $md5Value = "1934bfeb457362858b68df7ea997238a";
        $template = \WHMCS\Mail\Template::master()->where("name", "Account Access Invitation")->where("language", "")->first();
        if ($template && md5($template->message) === $md5Value) {
            $message = "\n<h2>You've been given access to {\$invite_account_name}.</h2>\n<p>{if \$invite_sent_by_admin}An agent of {\$company_name}{else}{\$invite_sender_name}{/if} has given you access to the {\$invite_account_name} account with {\$company_name}.</p>\n<p>To accept the invite, please click on the link below.</p>\n<p><a href=\"{\$invite_accept_url}\">Accept invitation</a></p>\n<p>Invitations are valid for 7 days from the time of issue. After that time, you will need to request a new invitation from the account administrator.</p>\n<p>{\$signature}</p>";
            $template->subject = "{if \$invite_sent_by_admin}An agent of {\$company_name} has sent you an invite{else}{\$invite_sender_name} has invited you to their account{/if}";
            $template->message = $message;
            $template->save();
        }
        return $this;
    }
}
