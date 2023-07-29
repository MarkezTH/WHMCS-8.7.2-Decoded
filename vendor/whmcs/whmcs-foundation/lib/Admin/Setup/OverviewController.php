<?php

namespace WHMCS\Admin\Setup;

class OverviewController
{
    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $setupTasks = (new SetupTasks())->evaluateAndGet();
        $completedTaskCount = 0;
        foreach ($setupTasks as $task) {
            if ($task["completed"]) {
                $completedTaskCount++;
            }
        }
        $output = view("setup.index", ["links" => $this->getLinks(), "categories" => $this->getCategories(), "highlightAssetInclude" => \WHMCS\View\Asset::jsInclude("jquery.highlight-5.js"), "setupTasks" => $setupTasks, "totalTaskCount" => count($setupTasks), "completedTaskCount" => $completedTaskCount, "setupTaskPercent" => round($completedTaskCount / count($setupTasks) * 100, 0), "recentlyVisited" => (new \WHMCS\VisitTracking("setup", 10))->get()->reverse()->take(10)]);
        return (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("setup.title"))->setFavicon("setup")->addContentAreaClass("theme-content-bg")->setBodyContent($output);
    }

    protected function getCategories()
    {
        return [["category" => "all", "title" => \AdminLang::trans("global.all"), "displayTitle" => \AdminLang::trans("setup.categories.all")], ["category" => "system", "title" => \AdminLang::trans("setup.categories.system")], ["category" => "apps", "title" => \AdminLang::trans("setup.categories.apps")], ["category" => "users", "title" => \AdminLang::trans("setup.categories.users")], ["category" => "products", "title" => \AdminLang::trans("setup.categories.products")], ["category" => "support", "title" => \AdminLang::trans("setup.categories.support")], ["category" => "security", "title" => \AdminLang::trans("setup.categories.api")]];
    }

    protected function getLinks()
    {
        return [["category" => "system", "title" => \AdminLang::trans("setup.general"), "icon" => "fas fa-cog", "description" => \AdminLang::trans("setupDescription.general"), "link" => "configgeneral.php", "id" => "Setup-General-Settings"], ["category" => "apps", "title" => \AdminLang::trans("setup.appsAndIntegrations"), "icon" => "fas fa-cubes", "badge" => "<span class=\"label label-info\">Updated</span>", "description" => \AdminLang::trans("setupDescription.appsAndIntegrations"), "link" => routePath("admin-apps-index"), "id" => "Setup-Apps-Integrations"], ["category" => "system", "title" => \AdminLang::trans("setup.automation"), "icon" => "far fa-clock", "badge" => "<span class=\"label label-info\">Updated</span>", "description" => \AdminLang::trans("setupDescription.automation"), "link" => "configauto.php", "id" => "Setup-Automation-Settings"], ["category" => "apps", "title" => \AdminLang::trans("setup.marketconnect"), "icon" => "fas fa-bullseye", "badge" => "<span class=\"label label-success\">New Service</span>", "description" => \AdminLang::trans("setupDescription.marketconnect"), "link" => "marketconnect.php", "id" => "Setup-Marketconnect"], ["category" => "products", "title" => \AdminLang::trans("setup.products"), "icon" => "fas fa-cube", "description" => \AdminLang::trans("setupDescription.products"), "link" => "configproducts.php", "id" => "Setup-Products"], ["category" => "products", "title" => \AdminLang::trans("setup.configoptions"), "icon" => "fas fa-sliders-h", "description" => \AdminLang::trans("setupDescription.configoptions"), "link" => "configproductoptions.php", "id" => "Setup-Configurable-Options"], ["category" => "products", "title" => \AdminLang::trans("setup.addons"), "icon" => "far fa-sticky-note", "badge" => "<span class=\"label label-info\">Updated</span>", "description" => \AdminLang::trans("setupDescription.addons"), "link" => "configaddons.php", "id" => "Setup-Product-Addons"], ["category" => "products", "title" => \AdminLang::trans("setup.bundles"), "icon" => "fas fa-cubes", "description" => \AdminLang::trans("setupDescription.bundles"), "link" => "configbundles.php", "id" => "Setup-Product-Bundles"], ["category" => "products", "title" => \AdminLang::trans("setup.promotions"), "icon" => "fas fa-ticket-alt", "description" => \AdminLang::trans("setupDescription.promotions"), "link" => "configpromotions.php", "id" => "Setup-Promotions"], ["category" => "products", "title" => \AdminLang::trans("setup.domainpricing"), "icon" => "fas fa-table", "description" => \AdminLang::trans("setupDescription.domainpricing"), "link" => "configdomains.php", "id" => "Setup-Domain-Pricing"], ["category" => "products", "title" => \AdminLang::trans("setup.servers"), "icon" => "fas fa-server", "description" => \AdminLang::trans("setupDescription.servers"), "link" => "configservers.php", "id" => "Setup-Servers"], ["category" => "apps", "title" => \AdminLang::trans("setup.addonmodules"), "icon" => "fas fa-cube", "description" => \AdminLang::trans("setupDescription.addonmodules"), "link" => "configaddonmods.php", "id" => "Setup-Addon-Modules"], ["category" => "support", "title" => \AdminLang::trans("setup.supportdepartments"), "icon" => "fas fa-life-ring", "description" => \AdminLang::trans("setupDescription.supportdepartments"), "link" => "configticketdepartments.php", "id" => "Setup-Support-Departments"], ["category" => "support", "title" => \AdminLang::trans("setup.escalationrules"), "icon" => "fas fa-random", "description" => \AdminLang::trans("setupDescription.escalationrules"), "link" => "configticketescalations.php", "id" => "Setup-Ticket-Escalations"], ["category" => "support", "title" => \AdminLang::trans("setup.spam"), "icon" => "fas fa-filter", "description" => \AdminLang::trans("setupDescription.spam"), "link" => "configticketspamcontrol.php", "id" => "Setup-Spam-Control"], ["category" => "system", "title" => \AdminLang::trans("setup.emailtpls"), "icon" => "fas fa-envelope", "badge" => "<span class=\"label label-info\">Updated</span>", "description" => \AdminLang::trans("setupDescription.emailtpls"), "link" => "configemailtemplates.php", "id" => "Setup-Email-Templates"], ["category" => "system", "title" => \AdminLang::trans("setup.clientgroups"), "icon" => "fas fa-user", "description" => \AdminLang::trans("setupDescription.clientgroups"), "link" => "configclientgroups.php", "id" => "Setup-Client-Groups"], ["category" => "system", "title" => \AdminLang::trans("setup.customfields"), "icon" => "fas fa-tags", "description" => \AdminLang::trans("setupDescription.customfields"), "link" => "configcustomfields.php", "id" => "Setup-Custom-Fields"], ["category" => "products", "title" => \AdminLang::trans("setup.orderstatuses"), "icon" => "fas fa-file-alt", "description" => \AdminLang::trans("setupDescription.orderstatuses"), "link" => "configorderstatuses.php", "id" => "Setup-Order-Statuses"], ["category" => "security", "title" => \AdminLang::trans("setup.bannedips"), "icon" => "fas fa-location-arrow", "description" => \AdminLang::trans("setupDescription.bannedips"), "link" => "configbannedips.php", "id" => "Setup-Banned-Ips"], ["category" => "security", "title" => \AdminLang::trans("setup.bannedemails"), "icon" => "far fa-envelope", "description" => \AdminLang::trans("setupDescription.bannedemails"), "link" => "configbannedemails.php", "id" => "Setup-Banned-Emails"], ["category" => "apps", "title" => \AdminLang::trans("setup.signInIntegrations"), "icon" => "fas fa-link", "description" => \AdminLang::trans("setupDescription.signInIntegrations"), "link" => routePath("admin-setup-authn-view"), "id" => "Setup-Sign-In-Integrations"], ["category" => "apps", "title" => \AdminLang::trans("setup.notifications"), "icon" => "fas fa-bell", "description" => \AdminLang::trans("setupDescription.notifications"), "link" => routePath("admin-setup-notifications-overview"), "id" => "Setup-Notifications"], ["category" => "apps", "title" => \AdminLang::trans("setup.gateways"), "icon" => "fas fa-university", "description" => \AdminLang::trans("setupDescription.gateways"), "link" => "configgateways.php", "id" => "Setup-Gateways"], ["category" => "apps", "title" => \AdminLang::trans("setup.registrars"), "icon" => "fas fa-globe", "description" => \AdminLang::trans("setupDescription.registrars"), "link" => "configregistrars.php", "id" => "Setup-Domain-Registrars"], ["category" => "system", "title" => \AdminLang::trans("setup.currencies"), "icon" => "fas fa-money-bill-alt", "description" => \AdminLang::trans("setupDescription.currencies"), "link" => "configcurrencies.php", "id" => "Setup-Currencies"], ["category" => "system", "title" => \AdminLang::trans("setup.tax"), "icon" => "fas fa-gavel", "badge" => "<span class=\"label label-info\">Updated</span>", "description" => \AdminLang::trans("setupDescription.tax"), "link" => routePath("admin-setup-payments-tax-index"), "id" => "Setup-Tax-Configuration"], ["category" => "security", "title" => \AdminLang::trans("setup.fraud"), "icon" => "fas fa-rocket", "description" => \AdminLang::trans("setupDescription.fraud"), "link" => "configfraud.php", "id" => "Setup-Fraud-Protection"], ["category" => "users", "title" => \AdminLang::trans("setup.admins"), "icon" => "fas fa-user", "description" => \AdminLang::trans("setupDescription.admins"), "link" => "configadmins.php", "id" => "Setup-Administrator-Users"], ["category" => "users", "title" => \AdminLang::trans("setup.roles"), "icon" => "fas fa-lock", "description" => \AdminLang::trans("setupDescription.roles"), "link" => "configadminroles.php", "id" => "Setup-Administrator-Roles"], ["category" => "security", "title" => \AdminLang::trans("setup.twofa"), "icon" => "fas fa-mobile-alt", "description" => \AdminLang::trans("setupDescription.twofa"), "link" => "configtwofa.php", "id" => "Setup-Two-Factor-Authentication"], ["category" => "support", "title" => \AdminLang::trans("setup.ticketstatuses"), "icon" => "fas fa-star", "description" => \AdminLang::trans("setupDescription.ticketstatuses"), "link" => "configticketstatuses.php", "id" => "Setup-Ticket-Statuses"], ["category" => "security", "title" => \AdminLang::trans("setup.securityqs"), "icon" => "far fa-question-circle", "description" => \AdminLang::trans("setupDescription.securityqs"), "link" => "configsecurityqs.php", "id" => "Setup-Security-Questions"], ["category" => "security", "title" => \AdminLang::trans("apicreds.title"), "icon" => "fas fa-laptop", "description" => \AdminLang::trans("setupDescription.apicreds"), "link" => "configapicredentials.php", "id" => "Setup-Api-Credentials"], ["category" => "apps", "title" => \AdminLang::trans("setup.applicationLinks"), "icon" => "far fa-handshake", "description" => \AdminLang::trans("setupDescription.applicationLinks"), "link" => "configapplinks.php", "id" => "Setup-Application-Links"], ["category" => "security", "title" => \AdminLang::trans("setup.openIdConnect"), "icon" => "fas fa-universal-access", "description" => \AdminLang::trans("setupDescription.openIdConnect"), "link" => "configopenid.php", "id" => "Setup-Openid-Connect"], ["category" => "system", "title" => \AdminLang::trans("setup.storage"), "icon" => "far fa-hdd", "description" => \AdminLang::trans("setupDescription.storage"), "link" => routePath("admin-setup-storage-index"), "id" => "Setup-Storage-Settings"], ["category" => "security", "title" => \AdminLang::trans("setup.backups"), "icon" => "fas fa-server", "description" => \AdminLang::trans("setupDescription.backups"), "link" => "configbackups.php", "id" => "Setup-Database-Backups"]];
    }
}
