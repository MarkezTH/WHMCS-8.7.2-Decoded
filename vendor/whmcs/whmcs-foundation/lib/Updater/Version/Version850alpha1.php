<?php

namespace WHMCS\Updater\Version;

class Version850alpha1 extends IncrementalVersion implements \WHMCS\Scheduling\Contract\JobInterface
{
    use \WHMCS\Scheduling\Jobs\JobTrait;
    protected $updateActions = ["createCartsTable", "createTicketImportNotificationJob", "removeOrphanedSslOrderRecords", "addMultiYearPricingToMarketConnectSSL", "registerSslReissuesCronTask", "createNewSSLCertificateEmailTemplates", "createCPanelSEOWelcomeEmailTemplate"];
    const JOB_NAME = "ticketImport.notification.migrate";

    public static function jobFactory()
    {
        return new self(new \WHMCS\Version\SemanticVersion(\WHMCS\Application::FILES_VERSION));
    }

    public function createCartsTable()
    {
        (new \WHMCS\Cart\Models\Cart())->createTable();
        return $this;
    }

    public function createTicketImportNotificationJob()
    {
        (new \WHMCS\Support\Ticket\TicketImportNotification())->createTable();
        \WHMCS\Scheduling\Jobs\Queue::addOrUpdate(static::JOB_NAME, static::class, "populateTicketImportNotifications", []);
        logActivity("The system has scheduled an automated task to migrate ticket import notifications.");
        return $this;
    }

    protected function addMultiYearPricingToMarketConnectSSL()
    {
        $sslProductsNotDigicert = \WHMCS\Product\Product::rapidssl()->orGeotrust()->pluck("id");
        $sslProductsDigicert = \WHMCS\Product\Product::digicert()->pluck("id");
        $sslAddonsNotDigicert = \WHMCS\Product\Addon::rapidssl()->orGeotrust()->pluck("id");
        $sslAddonsDigicert = \WHMCS\Product\Addon::digicert()->pluck("id");
        $sslProducts = [["addon" => false, "ids" => $sslProductsNotDigicert, "biennial" => 0, "triennial" => 0], ["addon" => false, "ids" => $sslProductsDigicert, "biennial" => 0, "triennial" => 0], ["addon" => true, "ids" => $sslAddonsNotDigicert, "biennial" => 0, "triennial" => 0], ["addon" => true, "ids" => $sslAddonsDigicert, "biennial" => 0, "triennial" => 0]];
        foreach ($sslProducts as $data) {
            $biennialMultiplier = $data["biennial"];
            $triennialMultiplier = $data["triennial"];
            if ($data["addon"]) {
                $pricing = \WHMCS\Billing\Addon\Pricing::whereIn("relid", $data["ids"])->get();
            } else {
                $pricing = \WHMCS\Billing\Pricing::where("type", \WHMCS\Billing\Pricing::TYPE_PRODUCT)->whereIn("relid", $data["ids"])->get();
            }
            foreach ($pricing as $price) {
                if (0 < $price->annually) {
                    $price->biennially = $price->annually * $biennialMultiplier;
                    $price->triennially = $price->annually * $triennialMultiplier;
                    $price->bsetupfee = $price->tsetupfee = $price->asetupfee;
                    $price->save();
                }
            }
        }
        return $this;
    }

    public function populateTicketImportNotifications(int $skip = 0, int $limit = 3000)
    {
        $ticketImports = \WHMCS\Log\TicketImport::requiresReview()->skip($skip)->take($limit)->get();
        if (empty($ticketImports) || $ticketImports->count() === 0) {
            \WHMCS\Scheduling\Jobs\Queue::remove(static::JOB_NAME);
            logActivity("The system has completed the automated migration of ticket import notifications.");
        } else {
            foreach ($ticketImports as $ticketImport) {
                \WHMCS\Support\Ticket\TicketImportNotification::storeEntry($ticketImport);
            }
            \WHMCS\Scheduling\Jobs\Queue::addOrUpdate(static::JOB_NAME, static::class, "populateTicketImportNotifications", [$skip + $limit, $limit]);
        }
    }

    public function removeOrphanedSslOrderRecords($self, $iteration = 1000, int $deleteBatchSize)
    {
        $orphanQuery = \WHMCS\Database\Capsule::table("tblsslorders as sub")->addSelect("sub.id")->leftJoin("tblhostingaddons", "sub.addon_id", "=", "tblhostingaddons.id")->leftJoin("tblhosting", "sub.serviceid", "=", "tblhosting.id")->whereNull("tblhostingaddons.id")->whereNull("tblhosting.id");
        \WHMCS\Database\Capsule::table("tblsslorders as pri")->joinSub($orphanQuery->limit($deleteBatchSize), "subset", "pri.id", "=", "subset.id")->delete();
        if (0 < $orphanQuery->count()) {
            if ($iteration === 0) {
                logActivity("The system has scheduled an automated task to remove orphaned SSL orders.");
            }
            \WHMCS\Scheduling\Jobs\Queue::useSchemaVersion(\WHMCS\Scheduling\Jobs\Queue::SCHEMA_V840BETA1);
            try {
                \WHMCS\Scheduling\Jobs\Queue::addOrUpdate("update.version850alpha1.removeorphanedsslorderrecords", static::class, "removeOrphanedSslOrderRecords", [++$iteration, $deleteBatchSize]);
            } finally {
                \WHMCS\Scheduling\Jobs\Queue::resetSchemaVersion();
            }
        } else {
            logActivity("The system has completed the automated removal of orphaned SSL orders.");
            return $this;
        }
    }

    public function registerSslReissuesCronTask()
    {
        \WHMCS\Cron\Task\SslReissues::register();
        return $this;
    }

    protected function createNewSSLCertificateEmailTemplates()
    {
        $newTemplates = ["SSL Certificate Multi-Year Reissue Due" => ["subject" => "[Action Required] Your SSL certificate is due for reissuance", "message" => "<p>Dear {\$client_name},</p>\n<p>Your multi-year SSL certificate is nearing the end of its current term.</p>\n<p>\n    Certificate Product: {\$service_product_name}<br>\n    Domain: {\$service_domain}<br>\n    Registration Date: {\$service_reg_date}<br>\n    Next Due Date: {\$service_next_due_date}\n</p>\n\n<p>Due to the limits from the Certification Authority Browser Forum, you must reissue multi-year SSL certificates during their term.</p>\n<p>\n    Unfortunately, we cannot automatically reissue your certificate because {if \$noSupport}it does not currently support automatic reissuance{else}the reissuance request failed{/if}.<br>\n    You must initiate reissuance manually.\n</p>\n<p>To manage your certificate, visit our Client Area using the link below. You can initiate reissuance by selecting \"Reissue Certificate\" and following the displayed steps.</p>\n<p>{\$certificate_manage_link}</p>\n<p>For assistance regarding your SSL certificate, contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.</p>\n<p>{\$signature}</p>"], "SSL Certificate Validation Manual Intervention" => ["subject" => "[Action Required] SSL Certificate Validation", "message" => "<p>Dear {\$client_name},</p>\n<p>Your recent SSL certificate order requires validation that we cannot complete automatically.</p>\n<p>\n    Certificate Product: {\$service_product_name}<br>\n    Domain: {\$service_domain}<br>\n    Registration Date: {\$service_reg_date}<br>\n    Next Due Date: {\$service_next_due_date}\n</p>\n<p>{if \$file_validation}</p>\n<p>Validation Method: HTTP File</p>\n<p>To complete validation successfully, you must create a file with the details below in the root directory of your hosting account.</p>\n<p>\n    Filepath/Filename: {\$file_name}<br>\n    File Contents: {\$validation_contents}\n</p>\n<p>{else if \$dns_validation}</p>\n<p>Validation Method: DNS</p>\n<p>To complete validation successfully, you must create the following DNS record with your domain registrar or DNS provider.</p>\n<p>\n    Record Type: TXT<br>\n    Record Contents: {\$validation_contents}\n</p>\n<p>{/if}</p>\n<p>When you complete the above steps, our systems will detect this automatically and continue processing your order. No further action is required.</p>\n<p>For assistance regarding your SSL certificate, contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.</p>\n<p>{\$signature}</p>"], "SSL Certificate Installed" => ["subject" => "Your website is now secured with SSL!", "message" => "<p>Dear {\$client_name},</p>\n<p>Your recent SSL certificate order is complete and you have secured your website with SSL. No further action is required.</p>\n<p>\n    Certificate Product: {\$service_product_name}<br>\n    Domain: {\$service_domain}<br>\n    Registration Date: {\$service_reg_date}<br>\n    Next Due Date: {\$service_next_due_date}<br>\n</p>\n<p>To manage your certificate, visit our Client Area using the link below.</p>\n<p>{\$certificate_manage_link}</p>\n<p>For assistance regarding your SSL certificate, contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.</p>\n<p>{\$signature}</p>"], "SSL Certificate Issued" => ["subject" => "Your SSL certificate is now ready for installation!", "message" => "<p>Dear {\$client_name},</p>\n<p>Your recent SSL certificate order is complete and is now ready for installation.</p>\n<p>\n    Certificate Product: {\$service_product_name}<br>\n    Domain: {\$service_domain}<br>\n    Registration Date: {\$service_reg_date}<br>\n    Next Due Date: {\$service_next_due_date}\n</p>\n<p>You are receiving this email because we cannot automatically install certificate. You must install it manually.</p>\n<p>To manage your certificate, visit our Client Area using the link below.</p>\n<p>{\$certificate_manage_link}</p>\n<p>For assistance regarding your SSL certificate, contact our <a href=\"{\$whmcs_url}submitticket.php\">support team</a>.</p>\n<p>{\$signature}</p>"]];
        foreach ($newTemplates as $name => $data) {
            $template = \WHMCS\Mail\Template::name($name)->master()->first();
            if (!$template) {
                $template = new \WHMCS\Mail\Template();
                $template->name = $name;
                $template->subject = $data["subject"];
                $template->message = $data["message"];
                $template->custom = false;
                $template->attachments = [];
                $template->type = "product";
                $template->copyTo = [];
                $template->blindCopyTo = [];
                $template->disabled = false;
                $template->language = "";
                $template->plaintext = false;
                $template->save();
            }
        }
        return $this;
    }

    public function createCPanelSEOWelcomeEmailTemplate()
    {
        $templateName = "cPanel SEO Welcome Email";
        $exists = \WHMCS\Mail\Template::master()->where("name", $templateName)->first();
        if (!$exists) {
            $template = new \WHMCS\Mail\Template();
            $template->type = "product";
            $template->name = $templateName;
            $template->subject = "Get Started with cPanel SEO";
            $template->message = "<p>Dear {\$client_name},</p>\n<p>Welcome to cPanel SEO! You're ready to find relevant keywords, optimize your content, and get to the top of the Google® search results.</p>\n<p>Log in now to complete the setup wizard so that cPanel SEO can begin analyzing your website. After it finishes, you'll be able to view your rankings, keywords, and visibility and get started improving your position in the cPanel SEO Advisor.</p>\n<p>\n    To get started, log in to our Client Area and follow the link to access cPanel SEO:<br>\n    {\$whmcs_url}clientarea.php\n</p>\n<p>If you need any further assistance, you may contact our support team at any time.</p>\n<p>{\$signature}</p>";
            $template->save();
        }
        return $this;
    }
}
