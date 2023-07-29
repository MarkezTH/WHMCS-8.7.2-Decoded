<?php

namespace WHMCS\Updater\Version;

class Version7103release1 extends IncrementalVersion
{
    protected $updateActions = ["createStripeWebhook"];

    protected function createStripeWebhook()
    {
        try {
            $stripe = \WHMCS\Module\GatewaySetting::getForGateway("stripe");
            $systemUrl = \WHMCS\Config\Setting::getValue("SystemURL");
            if (substr($systemUrl, -1) != "/") {
                $systemUrl .= "/";
            }
            if (!empty($stripe) && !empty($stripe["secretKey"]) && empty($stripe["webhookEndpointSecret"])) {
                \Stripe\Stripe::setAppInfo(\WHMCS\Module\Gateway\Stripe\Constant::$appName, (new \WHMCS\Version\SemanticVersion(\WHMCS\Application::FILES_VERSION))->getMajor(), \WHMCS\Module\Gateway\Stripe\Constant::$appUrl, \WHMCS\Module\Gateway\Stripe\Constant::$appPartnerId);
                \Stripe\Stripe::setApiKey($stripe["secretKey"]);
                \Stripe\Stripe::setApiVersion(\WHMCS\Module\Gateway\Stripe\Constant::$apiVersion);
                $notificationUrl = $systemUrl . "modules/gateways/callback/stripe.php";
                $liveMode = (bool) (strpos($stripe["secretKey"], "sk_live") !== false);
                $webHook = \Stripe\WebhookEndpoint::create(["url" => $notificationUrl, "enabled_events" => ["customer.source.updated", "customer.card.updated", "payment_method.updated"]]);
                if ($liveMode) {
                    \WHMCS\Module\GatewaySetting::setValue("stripe", "webhookEndpointSecret", $webHook->secret);
                } else {
                    \WHMCS\Module\GatewaySetting::setValue("stripe", "webhookEndpointSandboxSecret", $webHook->secret);
                }
            }
        } catch (\Exception $e) {
            logActivity("Updater Error: Unable to create a webhook for Stripe. Please try saving your Stripe configuration to reattempt webhook creation on the Manage Payment Gateways page. Error received: " . $e->getMessage());
        }
    }
}
