<?php

namespace WHMCS\Admin\Logs\MailImport;

class Controller
{
    private $view = NULL;

    public function __construct()
    {
        $this->initialiseView();
    }

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $params = [];
        $params["limit"] = $request->get("limit", 25);
        $params["page"] = $request->get("page", 1);
        if ($params["page"] < 1) {
            $params["page"] = 1;
        }
        $query = \WHMCS\Log\TicketImport::query();
        $params["totalEntries"] = $query->count();
        $params["importLogData"] = $query->orderBy("date", "DESC")->skip($params["page"] * $params["limit"] - $params["limit"])->take($params["limit"])->get()->all();
        $params["pagination"] = [];
        $params["totalPages"] = (int) ceil($params["totalEntries"] / $params["limit"]);
        if ($params["limit"] < $params["totalEntries"]) {
            $params["pagination"] = $this->paginate($params["totalPages"], $params["page"]);
        }
        if ($params["totalPages"] === 0) {
            $params["totalPages"] = 1;
        }
        $this->getView()->setBodyContent($this->pageSummaryHtml($params));
        return $this->getView();
    }

    public function viewMessage(\WHMCS\Http\Message\ServerRequest $request)
    {
        $id = $request->get("id");
        $params = [];
        try {
            $params["record"] = \WHMCS\Log\TicketImport::findOrFail($id);
            $params["user"] = $params["record"]->user;
            $params["ticketIdentifier"] = \WHMCS\Support\Ticket::extractIdentifier($params["record"]->subject);
            $params["existingTicket"] = \WHMCS\Support\Ticket::whereTid($params["ticketIdentifier"])->first();
            $langKeyPrefix = "utilities.ticketMailLog.footer.";
            $footerTitle = \AdminLang::trans("utilities.ticketMailLog.importResult");
            $params["footer"] = "<strong>" . $footerTitle . ":</strong><br />";
            $params["footerClass"] = "info";
            if (!$params["record"]->imported) {
                if ($params["existingTicket"]) {
                    $ticketLink = \App::getSystemURL(true) . $params["existingTicket"]->getLink();
                    $params["footer"] .= \AdminLang::trans($langKeyPrefix . "importIntoExisting", [":ticket" => "<a href=\"" . $ticketLink . "\">" . "#" . $params["existingTicket"]->tid . "</a>"]);
                } else {
                    $params["footer"] .= \AdminLang::trans($langKeyPrefix . "importNew");
                }
            } else {
                $params["footerClass"] = "success";
                $params["footer"] .= \AdminLang::trans($langKeyPrefix . "imported");
            }
            $response = ["body" => view("admin.logs.mailimportlog-singleview", $params)];
            if (!$params["record"]->imported) {
                $response["submitlabel"] = \AdminLang::trans("system.ignoreimport");
                $response["submitId"] = "btnImportNow";
            }
        } catch (\Exception $e) {
            return $this->handleModalException($e);
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function importNow(\WHMCS\Http\Message\ServerRequest $request)
    {
        $id = $request->get("id");
        if (!function_exists("openNewTicket") || !function_exists("AddReply")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ticketfunctions.php";
        }
        try {
            $importData = \WHMCS\Log\TicketImport::findOrFail($id);
            $email = $importData->email;
            $clientId = $userId = $adminId = 0;
            $importActionResult = $user = NULL;
            $from = [];
            $client = \WHMCS\User\Client::whereEmail($importData->email)->first();
            if (!$client) {
                $from = ["name" => $importData->name, "email" => $email];
            } else {
                $clientId = $client->id;
            }
            $tid = \WHMCS\Support\Ticket::extractIdentifier($importData->subject);
            if (!$tid) {
            } else {
                $ticket = \WHMCS\Support\Ticket::whereTid($tid)->first();
                if ($ticket) {
                    $adminUser = \WHMCS\User\Admin::whereEmail($email)->first();
                    if ($adminUser) {
                        $adminId = $adminUser->id;
                        $clientId = 0;
                        $from = "";
                    } else {
                        $user = \WHMCS\User\User::whereEmail($email)->first();
                    }
                    $importActionResult = AddReply($ticket->id, $clientId, "", $importData->message, $adminId, $importData->attachment, $from, "", "", false, false, [], \WHMCS\Carbon::now(), $user);
                }
            }
            $department = \WHMCS\Support\Department::whereEmail($importData->to)->first();
            if (!$department) {
                $department = \WHMCS\Support\Department::query()->orderBy("id", "ASC")->first();
                $deptId = $department->id;
            } else {
                $deptId = $department->id;
            }
            try {
                $importActionResult = openNewTicket($clientId, "", $deptId, $importData->subject, $importData->message, "Medium", $importData->attachment, $from, "", "", "", "", false);
            } catch (\WHMCS\Exception\Support\TicketMaskIterationException $e) {
                throw new \WHMCS\Exception(\AdminLang::trans("support.ticketCreationFailed", [":error" => \AdminLang::trans("support.errorUnableToCreateTicketNumber")]));
            }
            $status = \WHMCS\Log\TicketImport::STATUS_SUCCESSFUL_TICKET_IMPORT;
            if (is_object($importActionResult) || is_array($importActionResult)) {
                $importData->attachment = "";
            } else {
                $status = \WHMCS\Log\TicketImport::STATUS_FAILED_TICKET_IMPORT;
            }
            $importData->status = $status;
            $importData->save();
            $response = ["body" => "Ticket Imported. Reloading page...", "reloadPage" => true, "hideSubmit" => true];
        } catch (\Exception $e) {
            return $this->handleModalException($e);
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    private function handleModalException($JsonResponse, $exception)
    {
        $response = ["title" => \AdminLang::trans("global.erroroccurred"), "body" => \WHMCS\View\Helper::alert($exception->getMessage(), "danger"), "disableSubmit" => true];
        return new \WHMCS\Http\Message\JsonResponse($response);
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
        $routePath = routePath("admin-logs-mail-import-paged", $page);
        if ($params) {
            $delimiter = strpos($routePath, "?") === false ? "?" : "&";
            $queryString = http_build_query($params);
            $routePath .= $delimiter . $queryString;
        }
        return $routePath;
    }

    private function initialiseView()
    {
        $this->view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("system.mailimportlog"))->setSidebarName("logs")->setHelpLink("System_Utilities#Activity_Logs");
        return $this;
    }

    private function getView($BodyContentWrapper)
    {
        return $this->view;
    }

    private function pageSummaryHtml($params)
    {
        return view("admin.logs.mailimportlog", $params);
    }
}
