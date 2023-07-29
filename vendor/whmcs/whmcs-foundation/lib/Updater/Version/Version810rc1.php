<?php

namespace WHMCS\Updater\Version;

class Version810rc1 extends IncrementalVersion
{
    protected $updateActions = ["removeArchivingOnlyMarketConnectProduct", "removeArchivingOnlyMarketConnectAddon", "migrateRemainingSubaccountsToUsers", "markPreExistingAdminsDuoConfig"];

    protected function removeArchivingOnlyMarketConnectProduct()
    {
        \WHMCS\Product\Product::withoutEvents(function () {
            $products = \WHMCS\Product\Product::marketConnect()->withCount("services")->where("configoption1", "spamexperts_archiving")->get()->all();
            foreach ($products as $product) {
                if (0 < $product->services_count) {
                    $product->stockControlEnabled = true;
                    $product->quantityInStock = 0;
                    $product->isRetired = true;
                    $product->isHidden = true;
                    $product->save();
                } else {
                    if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
                        \WHMCS\Language\DynamicTranslation::whereIn("related_type", ["product.{id}.description", "product.{id}.name"])->where("related_id", "=", $product->id)->delete();
                    }
                    $usageItems = \WHMCS\UsageBilling\Product\UsageItem::ofRelated($product)->get();
                    foreach ($usageItems as $usageItem) {
                        $usageItem->delete();
                    }
                    $product->delete();
                }
            }
        });
        return $this;
    }

    protected function removeArchivingOnlyMarketConnectAddon()
    {
        $addons = \WHMCS\Product\Addon::with("serviceAddons")->whereHas("moduleConfiguration", function ($query) {
            $query->where("setting_name", "=", "configoption1")->where("value", "spamexperts_archiving");
        })->where("module", "marketconnect")->get();
        foreach ($addons as $addon) {
            if (0 < $addon->serviceAddons->count()) {
                $addon->serviceAddons()->update(["addonid" => 0, "name" => $addon->getRawAttribute("name")]);
            }
            $addon->delete();
        }
        return $this;
    }

    public function migrateRemainingSubaccountsToUsers()
    {
        if (0 < \WHMCS\User\Client\Contact::legacySubAccount()->count()) {
            \WHMCS\User\Client\Contact::legacySubaccount()->chunkById(100, function ($contacts) {
                foreach ($contacts as $contact) {
                    try {
                        $firstName = $contact->firstName ?: "undefined";
                        $lastName = $contact->lastName ?: "undefined";
                        $password = $contact->passwordHash ?: hash("sha256", \Illuminate\Support\Str::random(16) . $contact->id . $contact->email);
                        $user = \WHMCS\User\User::createUser($firstName, $lastName, $contact->email, $password, $contact->client ? $contact->client->language : NULL, true, true);
                        if ($contact->passwordHash) {
                            $user->password = $contact->passwordHash;
                            $user->save();
                        }
                        if ($contact->client) {
                            $user->clients()->attach($contact->client->id, ["permissions" => $contact->getRawAttribute("permissions")]);
                        }
                        if (0 < $contact->remoteAccountLinks()->count()) {
                            $contact->remoteAccountLinks()->update(["user_id" => $user->id, "client_id" => NULL, "contact_id" => NULL]);
                        }
                        $contact->isSubAccount = false;
                        $contact->permissions = [];
                        $contact->passwordHash = \Illuminate\Support\Str::random(64);
                        $contact->save();
                    } catch (\WHMCS\Exception\User\EmailAlreadyExists $e) {
                    } catch (\Throwable $e) {
                    }
                }
            });
        }
        return $this;
    }

    public function getFeatureHighlights()
    {
        $utmString = "?utm_source=in-product&utm_medium=whatsnew81";
        return [new \WHMCS\Notification\FeatureHighlight("<span>New</span> Client Area Theme", "A refreshed look and feel, built on <strong>Bootstrap 4</strong>", NULL, "new-theme.png", "The new client area theme \"Twenty-One\" includes new functionality, adds visual and usability improvements throughout, and introduces Bootstrap 4.", "https://docs.whmcs.com/New_Twenty-One_Client_Area_Theme" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>Child</span> Themes", "The new, <strong>easier way</strong> to create custom themes", NULL, "child-themes.png", "An improved system for custom themes. Customize the WHMCS Client Area without reinventing the wheel using the new flexible and extensible child themes functionality.", "https://developers.whmcs.com/themes/child-themes/" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("OAuth for <span>POP3/IMAP</span>", "Ensuring <strong>compatibility</strong> for upcoming changes from email services", NULL, "oauth.png", "Import email from Gmail using OAuth. OAuth is a security enhancement that makes traditional password authentication obsolete.", "https://docs.whmcs.com/OAuth2_for_Email_Importing" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("Google Analytics <span>4</span>", "Ensuring <strong>full support</strong> and compatability with the new Google Analytics", NULL, "google-analytics.png", "Now supporting Global Site Tag, Google Analytics and Universal Analytics, the native Google Analytics integration makes using Google Analytics to track visitors easy.", "https://docs.whmcs.com/Google_Analytics" . $utmString, "Learn More"), new \WHMCS\Notification\FeatureHighlight("<span>Automated</span> User Cleanup", "Delete and manage users easily", NULL, "user-cleanup.png", "New interface options allow you to delete Users whenever you need to, as well as scheduling automated deletions for inactive Client Accounts.", "https://docs.whmcs.com/Data_Retention_Policy_Automation" . $utmString, "Learn More")];
    }

    public function markPreExistingAdminsDuoConfig()
    {
        $duoAdmins = \WHMCS\User\Admin::where("authmodule", "duosecurity")->get();
        if (static::$startVersion) {
            $existingAdminIdIsUsername = \WHMCS\Version\SemanticVersion::compare(static::$startVersion, new \WHMCS\Version\SemanticVersion("8.1.0-alpha.1"), "<");
        }
        foreach ($duoAdmins as $admin) {
            $config = $admin->getSecondFactorConfig();
            if (!isset($config["duo_auth_identifier"])) {
                $config["duo_auth_identifier"] = $existingAdminIdIsUsername ? "username" : "email";
                $admin->setSecondFactorConfig($config);
                $admin->save();
            }
        }
        return $this;
    }
}
