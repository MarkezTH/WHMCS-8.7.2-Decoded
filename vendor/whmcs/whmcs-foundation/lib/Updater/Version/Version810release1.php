<?php

namespace WHMCS\Updater\Version;

class Version810release1 extends IncrementalVersion
{
    protected $updateActions = ["addMissingUuidForAdminAccounts", "replaceOrderItemsInAdminNewOrderNotificationEmailTemplate", "updatePleskPrimaryKeys"];

    protected function addMissingUuidForAdminAccounts()
    {
        try {
            \WHMCS\User\Admin::where("uuid", "")->get()->each(function ($admin) {
                try {
                    $admin->uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
                    $admin->save();
                } catch (\WHMCS\Exception $e) {
                }
            });
        } catch (\WHMCS\Exception $e) {
        }
        return $this;
    }

    protected function replaceOrderItemsInAdminNewOrderNotificationEmailTemplate()
    {
        $emailTemplate = \WHMCS\Mail\Template::where("name", "New Order Notification")->get();
        $replacement = "{assign \"displayTotalToday\" true}\n{foreach \$order_items_array as \$key => \$value}\n    {if \$value.service}\n        Product/Service: {\$value.service}<br>\n        {if \$value.domain}Domain: {\$value.domain}<br>{/if}\n        {foreach \$value.extra as \$extra}{\$extra}<br>{/foreach}\n        First Payment Amount: {\$value.firstPayment}<br>\n        {if \$value.recurringPayment}Recurring Amount: {\$value.recurringPayment}<br>{/if}\n        Billing Cycle: {\$value.cycle}<br>\n        {if \$value.qty}\n            Quantity: {\$value.qty}<br>\n            Total: {\$value.totalDue}<br>\n        {/if}<br>\n    {elseif \$value.domain}\n        Domain Action: {\$value.type}<br>\n        Domain: {\$value.domain}<br>\n        {if \$value.firstPayment}First Payment Amount: {\$value.firstPayment}<br>{/if}\n        {if \$value.recurringPayment}Recurring Amount: {\$value.recurringPayment}<br>{/if}\n        Registration Period: {\$value.registrationPeriod}<br>\n        {if \$value.dnsManagement}DNS Management<br>{/if}\n        {if \$value.emailForwarding}Email Forwarding<br>{/if}\n        {if \$value.idProtection}ID Protection<br>{/if}\n    {elseif \$value.addon}\n        {if \$value.qty}{\$value.qty} x {/if}Addon: {\$value.addon}<br>\n        Setup Fee: {\$value.setupFee}<br>\n        {if \$value.recurringPayment}Recurring Amount: {\$value.recurringPayment}<br>{/if}\n        Billing Cycle: {\$value.cycle}<br>\n    {elseif \$value.upgrade}\n        {\$value.upgrade}\n\t\t{\$displayTotalToday = false}\n    {/if}<br>\n{/foreach}\n{if \$displayTotalToday eq true}Total Due Today: {\$total_due_today}{/if}";
        foreach ($emailTemplate as $template) {
            if (stristr($template->message, "{\$order_items}") !== false) {
                $template->message = str_replace("{\$order_items}", $replacement, $template->message);
                $template->save();
            }
        }
        return $this;
    }

    protected function updatePleskPrimaryKeys()
    {
        if (\WHMCS\Database\Capsule::schema()->hasTable("mod_pleskaccounts")) {
            try {
                $update = \WHMCS\Database\Capsule::connection()->getPdo();
                $index = $update->query("show keys from mod_pleskaccounts where key_name = 'primary'")->rowCount();
                if (!empty($index)) {
                    $update->exec("alter table `mod_pleskaccounts` drop primary key, add primary key (userid, panelexternalid)");
                    $update->exec("alter table `mod_pleskaccounts` modify `userid` integer not null");
                    $update->exec("alter table `mod_pleskaccounts` modify `usertype` varchar(255) not null");
                } else {
                    $update->exec("alter table `mod_pleskaccounts` add primary key (userid, panelexternalid)");
                    $update->exec("alter table `mod_pleskaccounts` modify `userid` integer not null");
                    $update->exec("alter table `mod_pleskaccounts` modify `usertype` varchar(255) not null");
                }
            } catch (\Throwable $t) {
                logActivity("SQL update error for mod_pleskaccounts table: " . $t->getMessage());
            }
        }
    }
}
