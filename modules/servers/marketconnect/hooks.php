<?php

add_hook("ClientAreaPrimarySidebar", -1, function (WHMCS\View\Menu\Item $sidebar) {
    if (!$sidebar->getChild("Service Details Actions")) {
        return NULL;
    }
    $service = Menu::context("service");
    if (!$service) {
        return NULL;
    }
    if ($service instanceof WHMCS\Service\Service && $service->product->module != "marketconnect") {
        foreach ($service->addons as $addon) {
            if ($addon->productAddon->module === "marketconnect" && $addon->status === WHMCS\Service\Status::ACTIVE) {
                Menu::addContext("addon", $addon);
                WHMCS\MarketConnect\Provision::factoryFromModel($addon)->hookSidebarActions($sidebar);
            }
        }
        return NULL;
    } else {
        WHMCS\MarketConnect\Provision::factoryFromModel($service)->hookSidebarActions($sidebar);
    }
});
