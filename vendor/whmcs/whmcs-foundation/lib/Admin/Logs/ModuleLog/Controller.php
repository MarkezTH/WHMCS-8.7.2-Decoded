<?php

namespace WHMCS\Admin\Logs\ModuleLog;

class Controller
{
    private $moduleLogTable = "tblmodulelog";

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $params = [];
        $params["limit"] = $request->get("limit", 25);
        $params["page"] = $request->get("page", 1);
        if ($params["page"] < 1) {
            $params["page"] = 1;
        }
        $searchData = ["date" => $request->get("date", NULL), "module" => $request->get("module", NULL), "action" => $request->get("action", NULL), "request" => $request->get("request", NULL), "response" => $request->get("response", NULL)];
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("system.moduledebuglog"))->setSidebarName("logs")->setHelpLink("Troubleshooting_Module_Problems");
        $query = \WHMCS\Database\Capsule::table($this->moduleLogTable);
        $params["totalEntries"] = $query->count();
        if ($searchData["date"]) {
            $carbonDate = \WHMCS\Carbon::createFromAdminDateFormat($searchData["date"]);
            if ($carbonDate) {
                $searchData["date"] = $carbonDate->toAdminDateFormat();
                $query->whereBetween("date", [$carbonDate->startOfDay()->toDateTimeString(), $carbonDate->endOfDay()->toDateTimeString()]);
            }
        }
        if ($searchData["module"]) {
            $query->where("module", "LIKE", "%" . $searchData["module"] . "%");
        }
        if ($searchData["action"]) {
            $query->where("action", "LIKE", "%" . $searchData["action"] . "%");
        }
        if ($searchData["request"]) {
            $query->where("request", "LIKE", "%" . $searchData["request"] . "%");
        }
        if ($searchData["response"]) {
            $query->where("response", "LIKE", "%" . $searchData["response"] . "%");
        }
        $params["moduleLogData"] = $query->orderBy("date", "DESC")->skip($params["page"] * $params["limit"] - $params["limit"])->take($params["limit"])->get()->all();
        $params["search"] = $searchData;
        $params["pagination"] = [];
        $params["totalPages"] = (int) ceil($params["totalEntries"] / $params["limit"]);
        if ($params["limit"] < $params["totalEntries"]) {
            $params["pagination"] = $this->paginate($params["totalPages"], $params["page"], $params["search"]);
        }
        if ($params["totalPages"] === 0) {
            $params["totalPages"] = 1;
        }
        $content = $this->pageSummaryHtml($params);
        $view->setBodyContent($content);
        return $view;
    }

    public function toggleLogging($JsonResponse, $request)
    {
        $enabled = (bool) $request->get("enabled", false);
        \WHMCS\Config\Setting::setValue("ModuleDebugMode", $enabled ? "on" : "");
        $logEntry = "Module Log ";
        $logEntry .= $enabled ? "Enabled" : "Disabled";
        logAdminActivity($logEntry);
        $return = ["successMsgTitle" => \AdminLang::trans("global.success"), "successMsg" => $enabled ? \AdminLang::trans("utilities.moduleLogEnabled") : \AdminLang::trans("utilities.moduleLogDisabled")];
        return new \WHMCS\Http\Message\JsonResponse($return);
    }

    public function clearLog($RedirectResponse, $request)
    {
        \WHMCS\Database\Capsule::table($this->moduleLogTable)->truncate();
        logAdminActivity("Module Log Cleared");
        return (new \WHMCS\Http\RedirectResponse(routePath("admin-logs-module-log")))->withSuccess("<strong>" . \AdminLang::trans("global.success") . "</strong><br/>" . \AdminLang::trans("system.emptiedLog", [":emptied" => \AdminLang::trans("global.emptied"), ":name" => "Module"]));
    }

    public function viewSingleEntry(\WHMCS\Http\Message\ServerRequest $request)
    {
        $id = $request->get("logId", 0);
        $record = \WHMCS\Database\Capsule::table("tblmodulelog")->where("id", "=", $id)->first();
        if (!$record) {
            return new \WHMCS\Http\RedirectResponse(routePath("admin-logs-module-log"));
        }
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("system.moduledebuglog"))->setSidebarName("logs")->setHelpLink("Troubleshooting_Module_Problems");
        $view->setBodyContent(view("admin.logs.modulelog-singleview", ["record" => $record]));
        return $view;
    }

    private function paginate($totalPages, int $page = [], $searchData)
    {
        $elements = $params = [];
        $prevPage = $nextPage = "";
        $previousDisable = $nextDisable = true;
        if (!empty($searchData)) {
            foreach ($searchData as $name => $value) {
                if (!empty($value)) {
                    $params[$name] = $value;
                }
            }
        }
        if ($page !== 1) {
            $previousDisable = false;
            $prevPage = $page - 1;
        }
        if ($page != $totalPages) {
            $nextDisable = false;
            $nextPage = $page + 1;
        }
        $routes = ["previousPageRoute" => $prevPage ? $this->getPaginationRoutePath($prevPage, $params) : "#", "nextPageRoute" => $nextPage ? $this->getPaginationRoutePath($nextPage, $params) : "#", "currentPageRoute" => $this->getPaginationRoutePath($page, $params)];
        $elements[] = ["disabled" => $page === 1, "active" => false, "link" => $this->getPaginationRoutePath(1, $params), "text" => "<i class=\"fas fa-chevron-double-left\"></i>"];
        $elements[] = ["disabled" => $previousDisable, "active" => false, "link" => $routes["previousPageRoute"], "text" => "<i class=\"fas fa-chevron-left\"></i>"];
        if ($totalPages === 1) {
            $elements[] = ["disabled" => false, "active" => true, "link" => $this->getPaginationRoutePath($page, $params), "text" => $page];
        } else {
            $index = $page - 2;
            while ($index <= $page + 2 && $index <= $totalPages) {
                if ($index < 1) {
                    $index++;
                } else {
                    $elements[] = ["disabled" => false, "active" => $index === $page, "link" => $this->getPaginationRoutePath($index, $params), "text" => $index];
                    $index++;
                }
            }
        }
        $elements[] = ["disabled" => $nextDisable, "active" => false, "link" => $routes["nextPageRoute"], "text" => "<i class=\"fas fa-chevron-right\"></i>"];
        $elements[] = ["disabled" => $page === $totalPages, "active" => false, "link" => $this->getPaginationRoutePath($totalPages, $params), "text" => "<i class=\"fas fa-chevron-double-right\"></i>"];
        return $elements;
    }

    private function getPaginationRoutePath($page = [], $params)
    {
        $routePath = routePath("admin-logs-module-log-paged", $page);
        if ($params) {
            $delimiter = strpos($routePath, "?") === false ? "?" : "&";
            $queryString = http_build_query($params);
            $routePath .= $delimiter . $queryString;
        }
        return $routePath;
    }

    private function pageSummaryHtml($params)
    {
        $params["flash"] = \WHMCS\FlashMessages::get();
        return view("admin.logs.modulelog", $params);
    }
}
