<?php

namespace WHMCS\View\Client\Menu;

class SecondaryNavbarFactory extends PrimaryNavbarFactory
{
    protected $rootItemName = "Secondary Navbar";

    public function navbar($firstName = "", $conditionalLinks = [])
    {
        $menuStructure = \Auth::user() ? $this->getLoggedInNavBarStructure(\Auth::user()->firstName, $conditionalLinks) : $this->getLoggedOutNavBarStructure($conditionalLinks);
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    protected function getLoggedOutNavBarStructure($conditionalLinks = [])
    {
        $navbarStructure = [["name" => "Account", "label" => \Lang::trans("account"), "uri" => "#", "order" => 10, "children" => [["name" => "Login", "label" => \Lang::trans("login"), "uri" => "clientarea.php", "order" => 10], ["name" => "Divider", "label" => "-----", "order" => 30, "attributes" => ["class" => "nav-divider"]], ["name" => "Forgot Password?", "label" => \Lang::trans("forgotpw"), "uri" => routePath("password-reset-begin"), "order" => 40]]]];
        if (!empty($conditionalLinks["allowClientRegistration"])) {
            $navbarStructure[0]["children"][] = ["name" => "Register", "label" => \Lang::trans("register"), "uri" => "register.php", "order" => 20];
        }
        return $navbarStructure;
    }

    protected function getLoggedInNavBarStructure($firstName = "", $conditionalLinks = [])
    {
        return [["name" => "Account", "label" => sprintf(\Lang::trans("helloname"), \WHMCS\Input\Sanitize::makeSafeForOutput($firstName)), "uri" => "#", "order" => 10, "children" => $this->buildAccountChildren($conditionalLinks), "attributes" => ["class" => "account"]]];
    }

    protected function buildAccountChildren($conditionalLinks = [])
    {
        $accountChildren = [["name" => "Edit Account Details", "label" => \Lang::trans("clientareanavdetails"), "uri" => "clientarea.php?action=details", "order" => 10]];
        if (\Auth::client() && \Auth::client()->authedUserIsOwner() && !\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            $accountChildren[] = ["name" => "User Management", "label" => \Lang::trans("navUserManagement"), "uri" => routePath("account-users"), "order" => 15];
        }
        if (!empty($conditionalLinks["updatecc"])) {
            $accountChildren[] = ["name" => "Payment Methods", "label" => \Lang::trans("paymentMethods.title"), "uri" => routePath("account-paymentmethods"), "order" => 20];
        }
        $accountChildren[] = ["name" => "Contacts", "label" => \Lang::trans("navContacts"), "uri" => routePath("account-contacts"), "order" => 30];
        if (!empty($conditionalLinks["sso"])) {
            $accountChildren[] = ["name" => "Account Security", "label" => \Lang::trans("navAccountSecurity"), "uri" => "clientarea.php?action=security", "order" => 50];
        }
        $accountChildren[] = ["name" => "Email History", "label" => \Lang::trans("navemailssent"), "uri" => "clientarea.php?action=emails", "order" => 70];
        $accountChildren[] = ["name" => "Switch Divider", "label" => "-----", "order" => 80, "attributes" => ["class" => "nav-divider"]];
        $accountChildren[] = ["name" => "Profile", "label" => \Lang::trans("yourProfile"), "uri" => routePath("user-profile"), "order" => 81];
        if (\Auth::hasMultipleClients()) {
            $accountChildren[] = ["name" => "Switch Account", "label" => \Lang::trans("navSwitchAccount"), "uri" => routePath("user-accounts"), "order" => 82];
        }
        $accountChildren[] = ["name" => "Change Password", "label" => \Lang::trans("clientareanavchangepw"), "uri" => routePath("user-password"), "order" => 83];
        if (!empty($conditionalLinks["security"])) {
            $accountChildren[] = ["name" => "Security Settings", "label" => \Lang::trans("clientareanavsecurity"), "uri" => routePath("user-security"), "order" => 84];
        }
        $accountChildren[] = ["name" => "Divider", "label" => "-----", "order" => 85, "attributes" => ["class" => "nav-divider"]];
        $accountChildren[] = ["name" => "Logout", "label" => \Lang::trans("clientareanavlogout"), "uri" => "logout.php", "order" => 90];
        return $accountChildren;
    }
}
