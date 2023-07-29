<?php

define("CLIENTAREA", true);
require "init.php";
$pagetitle = Lang::trans("contacttitle");
$breadcrumbnav = "<a href=\"index.php\">" . Lang::trans("globalsystemname") . "</a> > <a href=\"contact.php\">" . Lang::trans("contacttitle") . "</a>";
$templatefile = "contact";
$pageicon = "images/contact_big.gif";
$displayTitle = Lang::trans("contactus");
$tagline = Lang::trans("readyforquestions");
initialiseClientArea($pagetitle, $displayTitle, $tagline, $pageicon, $breadcrumbnav);
$action = $whmcs->get_req_var("action");
$name = $whmcs->get_req_var("name");
$email = $whmcs->get_req_var("email");
$subject = $whmcs->get_req_var("subject");
$messageContent = $whmcs->get_req_var("message");
if (WHMCS\Config\Setting::getValue("ContactFormDept")) {
    redir("step=2&deptid=" . WHMCS\Config\Setting::getValue("ContactFormDept"), "submitticket.php");
}
$captcha = new WHMCS\Utility\Captcha();
$validate = new WHMCS\Validate();
$contactFormSent = false;
$sendError = "";
if ($action == "send") {
    check_token();
    $validate->validate("required", "name", "contacterrorname");
    if ($validate->validate("required", "email", "clientareaerroremail")) {
        $validate->validate("email", "email", "clientareaerroremailinvalid");
    }
    $validate->validate("required", "subject", "contacterrorsubject");
    $validate->validate("required", "message", "contacterrormessage");
    $captcha->validateAppropriateCaptcha(WHMCS\Utility\Captcha::FORM_CONTACT_US, $validate);
    if (!$validate->hasErrors()) {
        $sendmessage = "<font style=\"font-family:Verdana;font-size:11px\"><p>" . nl2br($messageContent) . "</p>";
        try {
            $systemFromEmail = WHMCS\Config\Setting::getValue("SystemEmailsFromEmail");
            if (!WHMCS\Config\Setting::getValue("ContactFormTo")) {
                $contactformemail = WHMCS\Config\Setting::getValue("Email");
            } else {
                $contactformemail = WHMCS\Config\Setting::getValue("ContactFormTo");
            }
            $message = new WHMCS\Mail\Message();
            $message->setSubject(Lang::trans("contactform") . ": " . $subject)->setType("admin")->setBodyAndPlainText($sendmessage)->addRecipient("to", $contactformemail)->setFromEmail($systemFromEmail)->setFromName(WHMCS\Config\Setting::getValue("SystemEmailsFromName"))->setReplyTo($email, $name);
            WHMCS\Module\Mail::factory()->send($message);
            $contactFormSent = true;
        } catch (WHMCS\Exception\Mail\EmailSendingDisabled $e) {
            $sendError = "<li>" . Lang::trans("clientareaerroroccured") . "</li>";
        } catch (WHMCS\Exception\Mail\SendFailure $e) {
            $sendError = "<li>" . Lang::trans("clientareaerroroccured") . "</li>";
            logActivity("Contact form mail sending failed with a Mailer Exception: " . $e->getMessage() . " (Subject: " . $subject . ")");
        } catch (Exception $e) {
            $sendError = "<li>" . Lang::trans("clientareaerroroccured") . "</li>";
            logActivity("Contact form mail sending failed with this error: " . $e->getMessage());
        }
    }
}
$smarty->assign("sent", $contactFormSent);
if ($validate->hasErrors() || $sendError) {
    $smarty->assign("errormessage", implode("\n", [$validate->getHTMLErrorOutput(), $sendError]));
}
$smarty->assign("name", $name);
$smarty->assign("email", $email);
$smarty->assign("subject", $subject);
$smarty->assign("message", $messageContent);
$smarty->assign("captcha", $captcha);
$smarty->assign("captchaForm", WHMCS\Utility\Captcha::FORM_CONTACT_US);
$smarty->assign("recaptchahtml", clientAreaReCaptchaHTML());
$smarty->assign("capatacha", $captcha);
$smarty->assign("recapatchahtml", clientAreaReCaptchaHTML());
outputClientArea($templatefile, false, ["ClientAreaPageContact"]);
