<?php

namespace WHMCS\Admin\Utilities\Tools\ServerSync;

class Controller
{
    public function analyse(\WHMCS\Http\Message\ServerRequest $request)
    {
        $serverId = $request->attributes()->get("serverid");
        $server = \WHMCS\Product\Server::findOrFail($serverId);
        $moduleInterface = $server->getModuleInterface();
        if (!$moduleInterface->functionExists("ListAccounts")) {
            throw new \WHMCS\Exception\Module\NotServicable("Server does not support account sync");
        }
        $uniqueIdDisplayName = $moduleInterface->getMetaDataValue("ListAccountsUniqueIdentifierDisplayName");
        $uniqueIdField = $moduleInterface->getMetaDataValue("ListAccountsUniqueIdentifierField");
        $productField = $moduleInterface->getMetaDataValue("ListAccountsProductField");
        $response = $moduleInterface->call("ListAccounts", $moduleInterface->getServerParams($server));
        $error = "";
        $syncItems = [];
        $services = NULL;
        if (!$response["success"] && array_key_exists("error", $response) && $response["error"]) {
            $error = \AdminLang::trans("utilities.serverSync.unableToConnect") . ": " . $response["error"];
        }
        if (!$error) {
            $services = $server->services;
            $fieldsToLower = ["username", "domain"];
            $services->transform(function (\WHMCS\Service\Service $item, $key) {
                foreach ($fieldsToLower as $field) {
                    $value = $item->{$field};
                    if (function_exists("mb_convert_case")) {
                        $value = mb_convert_case($value, MB_CASE_LOWER);
                    } else {
                        if (function_exists("mb_strtolower")) {
                            $value = mb_strtolower($value, MB_CASE_LOWER);
                        } else {
                            $value = strtolower($value);
                        }
                    }
                    $item->{$field} = $value;
                }
                return $item;
            });
            foreach ($response["accounts"] as $values) {
                $syncItems[] = new SyncItem($values, $uniqueIdField, $services, $productField);
            }
        }
        $clientWelcomeEmails = \WHMCS\Mail\Template::master()->where("type", "general")->orderBy("name")->pluck("name");
        $templateData = ["error" => $error, "server" => $server, "syncItems" => $syncItems, "syncedServiceIds" => [], "services" => $services, "sync" => $request->attributes()->get("sync", []), "terminate" => $request->attributes()->get("terminate", []), "import" => $request->attributes()->get("import", []), "uniqueIdDisplayName" => $uniqueIdDisplayName ? $uniqueIdDisplayName : "Domain", "clientWelcomeEmails" => $clientWelcomeEmails, "uniqueIdField" => $uniqueIdField];
        $body = view("admin.utilities.tools.serversync.analyse", $templateData);
        $view = new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper();
        $view->setTitle(\AdminLang::trans("utilities.serverSync.title"))->setSidebarName("utilities")->setHelpLink("Server Sync")->setFavicon("refresh")->setBodyContent($body);
        return $view;
    }

    public function process(\WHMCS\Http\Message\ServerRequest $request)
    {
        $serverRequest = $request->request();
        $serverId = $request->attributes()->get("serverid");
        $sync = $serverRequest->get("sync", []);
        $import = $serverRequest->get("import", []);
        $terminate = $serverRequest->get("terminate", []);
        $clientWelcomeEmail = $serverRequest->get("client_welcome");
        $clientWelcomeEmailTemplate = "";
        if ($clientWelcomeEmail) {
            $clientWelcomeEmailTemplate = $serverRequest->get("client_welcome_email");
        }
        $passwordReset = $serverRequest->get("password_reset");
        $serviceWelcomeEmail = $serverRequest->get("service_welcome");
        $setBilling = $serverRequest->get("set_billing");
        $nextDueDate = "";
        $billingCycle = "";
        if ($setBilling) {
            $nextDueDate = $serverRequest->get("next_due_date");
            $billingCycle = $serverRequest->get("billing_cycle");
        }
        $additional = ["clientWelcomeEmail" => $clientWelcomeEmailTemplate, "passwordReset" => $passwordReset, "serviceWelcomeEmail" => $serviceWelcomeEmail, "nextDueDate" => $nextDueDate, "billingCycle" => $billingCycle];
        $server = \WHMCS\Product\Server::findOrFail($serverId);
        $moduleInterface = $server->getModuleInterface();
        if (!$moduleInterface->functionExists("ListAccounts")) {
            throw new \WHMCS\Exception\Module\NotServicable("Server does not support account sync");
        }
        $services = $server->services;
        $fieldsToLower = ["username", "domain"];
        $services->transform(function (\WHMCS\Service\Service $item, $key) {
            foreach ($fieldsToLower as $field) {
                $value = $item->{$field};
                if (function_exists("mb_convert_case")) {
                    $value = mb_convert_case($value, MB_CASE_LOWER);
                } else {
                    if (function_exists("mb_strtolower")) {
                        $value = mb_strtolower($value, MB_CASE_LOWER);
                    } else {
                        $value = strtolower($value);
                    }
                }
                $item->{$field} = $value;
            }
            return $item;
        });
        $uniqueIdDisplayName = $moduleInterface->getMetaDataValue("ListAccountsUniqueIdentifierDisplayName");
        $uniqueIdField = $moduleInterface->getMetaDataValue("ListAccountsUniqueIdentifierField");
        $productField = $moduleInterface->getMetaDataValue("ListAccountsProductField");
        $response = $moduleInterface->call("ListAccounts", $moduleInterface->getServerParams($server));
        $imported = [];
        $synced = [];
        $terminated = [];
        $syncItems = [];
        foreach ($response["accounts"] as $values) {
            $syncItems[] = new SyncItem($values, $uniqueIdField, $services, $productField);
        }
        $importErrors = [];
        foreach ($syncItems as $syncItem) {
            $uniqueId = $syncItem->getUniqueIdentifier();
            if (in_array($uniqueId, $import)) {
                try {
                    Process::import($syncItem, $moduleInterface, $serverId, $additional);
                    $imported[] = $uniqueId;
                } catch (\Exception $e) {
                    $importErrors[] = $e->getMessage();
                }
            } else {
                $syncServices = $syncItem->getServices();
                foreach ($syncServices as $syncService) {
                    $syncValue = $uniqueId . "||" . $syncService->getId();
                    if (in_array($syncValue, $sync)) {
                        try {
                            Process::sync($syncItem, $syncService, $serverId);
                            $synced[] = $syncItem;
                        } catch (\WHMCS\Exception\Module\InvalidConfiguration $e) {
                            $importErrors[] = $e->getMessage();
                            foreach ($terminate as $serviceId) {
                                $service = $services->where("id", $serviceId)->first();
                                Process::terminate($service);
                                $terminated[] = $serviceId;
                            }
                            if (!function_exists("rebuildModuleHookCache")) {
                                require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "modulefunctions.php";
                            }
                            rebuildModuleHookCache();
                            $templateData = ["server" => $server, "syncItems" => $syncItems, "services" => $services, "import" => $import, "sync" => $sync, "terminate" => $terminate, "imported" => $imported, "synced" => $synced, "terminated" => $terminated, "hasErrors" => 0 < count($importErrors), "errors" => $importErrors];
                            $body = view("admin.utilities.tools.serversync.summary", $templateData);
                            $view = new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper();
                            $view->setTitle(\AdminLang::trans("utilities.serverSync.title"))->setSidebarName("utilities")->setHelpLink("Server Sync")->setFavicon("refresh")->setBodyContent($body);
                            return $view;
                        } catch (\Exception $e) {
                            $importErrors[] = $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
