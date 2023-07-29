<?php

namespace WHMCS\Updater\Version;

class Version820rc1 extends IncrementalVersion
{
    protected $updateActions = ["addWPTKWelcomeEmail"];

    public function addWPTKWelcomeEmail()
    {
        $exists = \WHMCS\Mail\Template::master()->where("name", "WP Toolkit Welcome Email")->first();
        if (!$exists) {
            $template = new \WHMCS\Mail\Template();
            $template->type = "product";
            $template->name = "WP Toolkit Welcome Email";
            $template->subject = "{\$service_product_name}";
            $template->message = "<p>Dear {\$client_name},</p>\n<p>Thank you for purchasing {\$service_product_name}!</p>\n<p>{\$service_product_name} has now been activated for your account with domain {\$service_domain} and you can begin using the advanced features of {\$service_product_name} immediately.</p>\n<p>You can access {\$service_product_name} via your hosting service or by logging in to your client area using the link below.</p>\n<p>{\$whmcs_link}</p>\n<p>{\$signature}</p>";
            $template->save();
        }
        return $this;
    }
}
