<?php

namespace WHMCS\Updater\Version;

class Version830alpha1 extends IncrementalVersion
{
    protected $updateActions = ["createProductEventActionTables", "createNewProductsSlugsTable", "autoSetProductSlugs", "addWordPressInstallationWelcomeEmail", "renameShowNotesFieldOnCheckoutColumn"];

    public function createProductEventActionTables()
    {
        (new \WHMCS\Product\EventAction\EventAction())->createTable();
        return $this;
    }

    protected function createNewProductsSlugsTable()
    {
        (new \WHMCS\Product\Product\Slug())->createTable();
        (new \WHMCS\Product\Product\SlugTracking())->createTable();
        return $this;
    }

    protected function autoSetProductSlugs()
    {
        foreach (\WHMCS\Product\Product::all() as $product) {
            if ($product->productGroup) {
                $slug = new \WHMCS\Product\Product\Slug(["group_id" => $product->productGroup->id, "group_slug" => $product->productGroup->slug, "slug" => $product->autoGenerateUniqueSlug(), "active" => true]);
                $product->slugs()->save($slug);
            }
        }
        return $this;
    }

    protected function addWordPressInstallationWelcomeEmail()
    {
        $templateName = "WordPress Installation Welcome Email";
        $exists = \WHMCS\Mail\Template::master()->where("name", $templateName)->first();
        if (!$exists) {
            $template = new \WHMCS\Mail\Template();
            $template->type = "product";
            $template->name = $templateName;
            $template->subject = "Welcome to WordPress";
            $template->message = "<p>Dear {\$client_name},</p>\n<p>Your new WordPress® blog is ready. This email contains all of the important information to get you started.</p>\n<p>You and your visitors can find your new WordPress installation at: {\$instance_url}</p>\n<p>To configure your blog and add content, use the WordPress Administration Area: {\$instance_admin_url}</p>\n<p>You can use these details to log in:<br>\nUsername: {\$admin_username}<br>\nPassword: {\$admin_password}</p>\n<p>{\$signature}</p>";
            $template->save();
        }
        return $this;
    }

    public function renameShowNotesFieldOnCheckoutColumn()
    {
        \WHMCS\Database\Capsule::table("tblconfiguration")->where("setting", "=", "ShowNotesFieldonCheckout")->update(["setting" => "ShowNotesFieldOnCheckout"]);
        return $this;
    }

    public function getFeatureHighlights()
    {
        $utmString = "?utm_source=in-product&utm_medium=whatsnew83";
        return [new \WHMCS\Notification\FeatureHighlight("WordPress® Hosting Made Easy", "Set up hosting products with instant on-demand WordPress installations. ", NULL, "icon-wordpress.png", "Start selling WordPress hosting with no extra work required.", "https://docs.whmcs.com/WordPress_Hosting" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Stripe and PayPal® Disputes", "Work with Stripe and PayPal disputes in WHMCS.", NULL, "icon-gateway.png", "Use one simple interface to view disputes, close them, submit evidence, and more.", "https://docs.whmcs.com/Disputes" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Friendly Product URLs", "Friendly product URLs add attractive links and tracking for your products.", NULL, "icon-seo.png", "Optimize your products for SEO with friendly product URLs.", "https://docs.whmcs.com/Friendly_URLs" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("SSL Certificate Validation", "WHMCS now supports DNS validation for MarketConnect’s DigiCert SSL certificates.", NULL, "icon-ssl.png", "Plus improvements making it easy to find and access validation data.", "https://docs.whmcs.com/SSL_Certificates_via_WHMCS_MarketConnect" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Affiliate Commission Reversals", "Affiliate commission reversals ensure that you won't pay commission for refunds or disputes.", NULL, "icon-reverse.png", "Improved commission reversals to protect your business.", "https://docs.whmcs.com/Affiliates" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("New Client Profile Enhancements", "An enhanced experience to help you find products and services, addons, and more.", NULL, "icon-enhance.png", "Find the new and improved experience in the Summary tab.")];
    }
}
