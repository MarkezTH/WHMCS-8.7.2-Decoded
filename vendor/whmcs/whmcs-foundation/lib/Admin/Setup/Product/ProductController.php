<?php

namespace WHMCS\Admin\Setup\Product;

class ProductController
{
    public function newAddon(\WHMCS\Http\Message\ServerRequest $request)
    {
        $assetHelper = \DI::make("asset");
        $baseWebRoot = $assetHelper->getWebRoot();
        $webRoot = $baseWebRoot . "/" . \App::get_admin_folder_name();
        $hasMcPermission = \WHMCS\User\Admin\Permission::currentAdminHasPermissionName("Manage MarketConnect");
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("addons.productaddons"))->setSidebarName("config")->setFavicon("productaddons")->setHelpLink("Product_Addons");
        $moduleInterface = new \WHMCS\Module\Server();
        $moduleList = collect($moduleInterface->getListWithDisplayNames());
        $promotedModules = collect(["cpanel", "plesk", "directadmin", "licensing", "autorelease"]);
        $inputModule = $request->get("module");
        $hookResults = run_hook("AdminPredefinedAddons", []);
        $predefinedAddons = [];
        foreach ($hookResults as $hookResult) {
            $predefinedAddons = array_merge($predefinedAddons, $hookResult);
        }
        if ($hasMcPermission) {
            $iconFormat = "/assets/img/marketconnect/%s/logo-sml.";
            foreach (\WHMCS\MarketConnect\MarketConnect::SERVICES as $service => $serviceDetails) {
                $iconPath = sprintf($iconFormat, $serviceDetails["vendorSystemName"]);
                $iconExtension = "png";
                if (file_exists(ROOTDIR . $iconPath . "svg")) {
                    $iconExtension = "svg";
                }
                $predefinedAddons[] = ["service" => $service, "active" => \WHMCS\MarketConnect\MarketConnect::isActive($service), "iconvalue" => $baseWebRoot . $iconPath . $iconExtension, "paneltitle" => $serviceDetails["vendorName"] . " " . $serviceDetails["serviceTitle"], "paneldescription" => $serviceDetails["description"]];
            }
            unset($iconFormat);
            unset($iconPath);
            unset($iconExtension);
        }
        $view->setBodyContent(view("admin.setup.product.addon.create", ["webRoot" => $webRoot, "moduleInterface" => $moduleInterface, "moduleList" => $moduleList, "promotedModules" => $promotedModules, "inputModule" => $inputModule, "predefinedAddons" => $predefinedAddons, "hasMcPermission" => $hasMcPermission, "account" => ["linked" => \WHMCS\MarketConnect\MarketConnect::isAccountConfigured()]]));
        return $view;
    }

    public function createAddon($RedirectResponse, $request)
    {
        $isPredefinedAddon = (bool) $request->get("feature");
        $assign = $request->get("assign");
        $module = $request->get("module");
        $type = $request->get("type");
        $email = $request->get("email");
        $packages = [];
        $serverInterface = new \WHMCS\Module\Server();
        $serverInterface->load($module);
        if ($type === "feature") {
            if (!$serverInterface->functionExists(\WHMCS\Admin\Setup\AddonSetup::GET_ADD_ON_FEATURES_FUNCTION)) {
                $title = \AdminLang::trans("global.error");
                $error = \AdminLang::trans("addons.invalidModuleForType");
                \WHMCS\FlashMessages::add("<strong>" . $title . "</strong><br>" . $error, "danger");
                return new \WHMCS\Http\RedirectResponse(routePath("admin-setup-product-addon-new"));
            }
            if ($assign) {
                $productTypes = [];
                if ($request->get("feature")) {
                    $productTypes = $serverInterface->call("getProductTypesForAddOn", ["Feature Name" => $request->get("feature")]);
                    if (!is_array($productTypes)) {
                        $productTypes = [];
                    }
                }
                $packages = \WHMCS\Product\Product::ofModule($module);
                if ($productTypes) {
                    $packages->whereIn("type", $productTypes);
                }
                $packages = $packages->pluck("id")->toArray();
            }
        }
        $weight = (int) \WHMCS\Product\Addon::max("weight") + 1;
        $welcomeEmailId = 0;
        if ($email) {
            $welcomeEmailId = \WHMCS\Mail\Template::where("name", $email)->first()->id;
        }
        $addon = new \WHMCS\Product\Addon();
        $addon->name = $request->get("name");
        $addon->description = $request->get("description") ?? "";
        $addon->billingCycle = "free";
        $addon->allowMultipleQuantities = \WHMCS\Cart\CartCalculator::QUANTITY_NONE;
        $addon->packages = $packages;
        $addon->applyTax = $isPredefinedAddon;
        $addon->showOnOrderForm = $isPredefinedAddon;
        $addon->isHidden = (bool) $request->get("hidden");
        $addon->retired = false;
        $addon->autoActivate = $isPredefinedAddon ? "payment" : "";
        $addon->suspendProduct = !$isPredefinedAddon;
        $addon->downloads = [];
        $addon->welcomeEmailTemplateId = (int) ($welcomeEmailId ?? 0);
        $addon->weight = $weight;
        $addon->module = $module;
        $addon->type = "other";
        $addon->serverGroupId = 0;
        $addon->prorate = $isPredefinedAddon;
        $addon->save();
        $moduleConfiguration = new \WHMCS\Config\Module\ModuleConfiguration();
        $moduleConfiguration->entityType = "addon";
        $moduleConfiguration->entityId = $addon->id;
        $moduleConfiguration->friendlyName = "provisioningType";
        $moduleConfiguration->settingName = "provisioningType";
        $moduleConfiguration->value = $type;
        $moduleConfiguration->save();
        if ($isPredefinedAddon) {
            $moduleConfiguration = new \WHMCS\Config\Module\ModuleConfiguration();
            $moduleConfiguration->entityType = "addon";
            $moduleConfiguration->entityId = $addon->id;
            $moduleConfiguration->friendlyName = "Feature Name";
            $moduleConfiguration->settingName = "configoption1";
            $moduleConfiguration->value = $request->get("feature");
            $moduleConfiguration->save();
        }
        $newPricing = [];
        $pricing = ["msetupfee" => 0, "monthly" => -1, "qsetupfee" => 0, "quarterly" => -1, "ssetupfee" => 0, "semiannually" => -1, "asetupfee" => 0, "annually" => -1, "bsetupfee" => 0, "biennially" => -1, "tsetupfee" => 0, "triennially" => -1, "type" => \WHMCS\Billing\PricingInterface::TYPE_ADDON];
        foreach (\WHMCS\Billing\Currency::all() as $currency) {
            $newPricing[] = array_merge($pricing, ["currency" => $currency->id, "relid" => $addon->id]);
        }
        \WHMCS\Database\Capsule::table("tblpricing")->insert($newPricing);
        logAdminActivity("Product Addon Created: '" . $addon->name . "' - Product Addon ID: " . $addon->id);
        $assetHelper = \DI::make("asset");
        $webRoot = $assetHelper->getWebRoot() . "/" . \App::get_admin_folder_name();
        $predefinedUrlParams = $isPredefinedAddon ? "&predefined=true#tab=2" : "";
        return new \WHMCS\Http\RedirectResponse($webRoot . "/configaddons.php?action=manage&id=" . $addon->id . "&created=true" . $predefinedUrlParams);
    }

    public function validateSlug(\WHMCS\Http\Message\ServerRequest $request)
    {
        $productId = $request->get("productId");
        $productName = $request->get("productName");
        $groupId = $request->get("groupId");
        $slug = $request->get("slug");
        $product = !empty($productId) ? \WHMCS\Product\Product::find($productId) : new \WHMCS\Product\Product();
        $product->productGroupId = $groupId;
        return $this->doValidateSlug($product, $productName, $slug);
    }

    public function validateGroupSlug(\WHMCS\Http\Message\ServerRequest $request)
    {
        $groupId = $request->get("groupid");
        $groupName = $request->get("groupname");
        $slug = $request->get("slug");
        $group = !empty($groupId) ? \WHMCS\Product\Group::find($groupId) : new \WHMCS\Product\Group();
        return $this->doValidateSlug($group, $groupName, $slug);
    }

    public function groupSlug($ResponseInterface, $request)
    {
        $groupId = $request->get("groupid");
        try {
            $group = \WHMCS\Product\Group::findOrFail($groupId);
            return new \WHMCS\Http\Message\JsonResponse(["slug" => fqdnRoutePath("store-product-group", $group->slug) . "/"]);
        } catch (\Throwable $t) {
            return new \WHMCS\Http\Message\JsonResponse(["invalidSlug" => true, "invalidReason" => "Invalid Product Group ID"]);
        }
    }

    protected function doValidateSlug($ResponseInterface, $model, $inputName, $slug)
    {
        foreach (\WHMCS\Admin\Setup\ProductSetup::VALIDATION_CHECKS as $validationCheck) {
            try {
                $checkResult = $model->{$validationCheck}($slug);
            } catch (\WHMCS\Exception\Validation\DuplicateValue $e) {
                $checkResult = "slugInUse";
            } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
                $checkResult = $e->getMessage();
                if (!in_array($checkResult, [\WHMCS\Product\Interfaces\SlugInterface::INVALID_EMPTY, \WHMCS\Product\Interfaces\SlugInterface::INVALID_HYPHEN, \WHMCS\Product\Interfaces\SlugInterface::INVALID_NUMERIC])) {
                    throw new \WHMCS\Exception($checkResult);
                }
                if ($inputName !== "" && str_replace("-", "", $slug) === "") {
                    $transliteratedSlug = \voku\helper\ASCII::to_transliterate($inputName);
                    try {
                        if ($transliteratedSlug) {
                            $transliteratedSlug = \Illuminate\Support\Str::slug($transliteratedSlug);
                            if ($model->validateSlugIsUnique($transliteratedSlug) && $model->validateSlugFormat($transliteratedSlug)) {
                                return new \WHMCS\Http\Message\JsonResponse(["invalidSlug" => false, "slug" => $transliteratedSlug]);
                            }
                        }
                    } catch (\WHMCS\Exception\Validation\DuplicateValue $e) {
                    } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
                    }
                }
            }
            if ($checkResult !== true) {
                return new \WHMCS\Http\Message\JsonResponse(["invalidSlug" => true, "invalidReason" => \AdminLang::trans("products." . $checkResult)]);
            }
        }
        $slug = \Illuminate\Support\Str::slug($slug);
        return new \WHMCS\Http\Message\JsonResponse(["invalidSlug" => false, "slug" => $slug]);
    }

    public function removeSlug(\WHMCS\Http\Message\ServerRequest $request)
    {
        $slugId = $request->get("slugId");
        \WHMCS\Product\Product\Slug::destroy($slugId);
        return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
    }
}
