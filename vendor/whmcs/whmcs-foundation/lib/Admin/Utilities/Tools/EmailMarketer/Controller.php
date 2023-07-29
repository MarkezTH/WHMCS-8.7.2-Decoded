<?php

namespace WHMCS\Admin\Utilities\Tools\EmailMarketer;

class Controller
{
    public function manage(\WHMCS\Http\Message\ServerRequest $request)
    {
        $id = $request->get("id", 0);
        $ruleName = "";
        $type = "client";
        $marketing = $disabled = false;
        $clientDays = $minimumServices = $maximumServices = "";
        $clientEmailTemplate = $productEmailTemplate = $numberOfDays = "";
        $selectedProducts = $selectedAddons = $withoutProducts = [];
        $withoutAddons = $selectedStatuses = $selectedCycles = [];
        $daysType = "after_order";
        $clientEmailTemplates = \WHMCS\Mail\Template::master()->where("type", "general")->get();
        $productEmailTemplates = \WHMCS\Mail\Template::master()->where("type", "product")->get();
        $products = \WHMCS\Product\Product::with("productGroup")->get();
        $addons = \WHMCS\Product\Addon::all();
        try {
            if ($id) {
                $rule = \WHMCS\Admin\Utilities\Tools\EmailMarketer::findOrFail($id);
                $id = $rule->id;
                $ruleName = $rule->name;
                $type = $rule->type;
                $marketing = $rule->marketing;
                $disabled = $rule->disabled;
                $settings = $rule->settings;
                $clientDays = $settings["clientnumdays"];
                $minimumServices = $settings["clientsminactive"];
                $maximumServices = $settings["clientsmaxactive"];
                $clientEmailTemplate = $settings["clientemailtpl"];
                $productEmailTemplate = $settings["prodemailtpl"];
                $selectedStatuses = $settings["prodstatus"];
                $selectedCycles = $settings["product_cycle"];
                $numberOfDays = $settings["prodnumdays"];
                $daysType = $settings["prodfiltertype"];
                $withoutAddons = $settings["prodexcludeaid"];
                if (!is_array($selectedStatuses)) {
                    $selectedStatuses = [];
                }
                if (!is_array($selectedCycles)) {
                    $selectedCycles = [];
                }
                if (!is_array($withoutAddons)) {
                    $withoutAddons = [];
                }
                $withoutProducts = $settings["prodexcludepid"];
                if (!is_array($withoutProducts)) {
                    $withoutProducts = [];
                }
                $selectedProducts = $settings["products"];
                if (!is_array($selectedProducts)) {
                    $selectedProducts = [];
                }
                $selectedAddons = $settings["addons"];
                if (!is_array($selectedAddons)) {
                    $selectedAddons = [];
                }
            }
            $response = ["body" => view("admin.utilities.email-marketer.manage", ["id" => $id, "ruleName" => $ruleName, "type" => $type, "marketing" => $marketing, "disabled" => $disabled, "clientDays" => $clientDays, "minimumServices" => $minimumServices, "maximumServices" => $maximumServices, "clientEmailTemplate" => $clientEmailTemplate, "productEmailTemplate" => $productEmailTemplate, "numberOfDays" => $numberOfDays, "selectedProducts" => $selectedProducts, "selectedAddons" => $selectedAddons, "withoutProducts" => $withoutProducts, "withoutAddons" => $withoutAddons, "selectedStatuses" => $selectedStatuses, "selectedCycles" => $selectedCycles, "daysType" => $daysType, "clientEmailTemplates" => $clientEmailTemplates, "productEmailTemplates" => $productEmailTemplates, "products" => $products, "addons" => $addons, "cycles" => (new \WHMCS\Billing\Cycles())->getPublicBillingCycles()])];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = ["error" => true, "dismiss" => true, "errorMsg" => \AdminLang::trans("utilities.emailMarketer.notFound")];
        } catch (\Exception $e) {
            $response = ["error" => true, "dismiss" => true, "errorMsg" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function save(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $id = $request->get("id", 0);
            $rule = NULL;
            $success = "utilities.emailMarketer.added";
            if ($id) {
                $rule = \WHMCS\Admin\Utilities\Tools\EmailMarketer::findOrFail($id);
                $success = "utilities.emailMarketer.updated";
            }
            if (!$rule) {
                $rule = new \WHMCS\Admin\Utilities\Tools\EmailMarketer();
            }
            $rule->name = $request->get("name");
            $type = $request->get("type", "client");
            if (!in_array($type, ["client", "product"])) {
                $type = "client";
            }
            $rule->type = $type;
            $rule->marketing = (bool) (int) $request->get("marketing");
            $rule->disabled = (bool) (int) $request->get("disabled");
            $settings = $rule->defaultSettings();
            $settings["clientnumdays"] = $request->get("client_days", "");
            $settings["clientsminactive"] = $request->get("min_services", "");
            $settings["clientsmaxactive"] = $request->get("max_services", "");
            $settings["clientemailtpl"] = $request->get("email_template_client");
            $settings["products"] = $request->get("products", []);
            $settings["addons"] = $request->get("addons", []);
            $settings["prodstatus"] = $request->get("product_status", []);
            $settings["product_cycle"] = $request->get("product_cycle", []);
            $settings["prodnumdays"] = $request->get("number_of_days", "");
            $settings["prodfiltertype"] = $request->get("days_type");
            $settings["prodexcludepid"] = $request->get("without_product", []);
            $settings["prodexcludeaid"] = $request->get("without_addon", []);
            $settings["prodemailtpl"] = $request->get("email_template_product");
            $rule->settings = $settings;
            $rule->save();
            \WHMCS\Database\Capsule::table("tblemailmarketer_related_pivot")->where("task_id", $rule->id)->delete();
            foreach ($settings["products"] as $product) {
                $rule->products()->attach($product);
            }
            foreach ($settings["addons"] as $addon) {
                $rule->addons()->attach($addon);
            }
            $response = ["dismiss" => true, "success" => true, "reloadPage" => true, "successMsg" => \AdminLang::trans($success), "successMsgTitle" => ""];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = ["error" => true, "errorMsg" => \AdminLang::trans("utilities.emailMarketer.notFound")];
        } catch (\Exception $e) {
            $response = ["error" => true, "errorMsg" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }
}
