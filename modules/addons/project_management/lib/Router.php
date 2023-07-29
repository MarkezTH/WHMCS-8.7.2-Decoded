<?php

namespace WHMCS\Module\Addon\ProjectManagement;

class Router
{
    protected $routes = ["watch" => "project@watch", "unwatch" => "project@unwatch", "saveProject" => "project@saveProject", "clientSearch" => "project@clientSearch", "duplicateProject" => "project@duplicateProject", "deletefile" => "files@delete", "uploadfile" => "files@upload", "addInvoice" => "invoices@associate", "createInvoice" => "invoices@create", "searchInvoices" => "invoices@search", "unlinkInvoice" => "invoices@unlink", "addmessage" => "messages@add", "deletemsg" => "messages@delete", "uploadFileForMessage" => "messages@uploadFile", "addtask" => "tasks@add", "assigntask" => "tasks@assign", "deletetask" => "tasks@delete", "deleteTaskTemplate" => "tasks@deleteTaskTemplate", "gettaskinfo" => "tasks@getSingle", "importTasks" => "tasks@import", "saveTaskList" => "tasks@saveList", "selectTaskList" => "tasks@select", "taskedit" => "tasks@edit", "taskduedate" => "tasks@setDueDate", "taskSearch" => "tasks@search", "tasksort" => "tasks@saveOrder", "taskstatustoggle" => "tasks@toggleStatus", "addticket" => "tickets@associate", "openticket" => "tickets@open", "parseMarkdown" => "tickets@parseMarkdown", "searchTickets" => "tickets@search", "unlinkTicket" => "tickets@unlink", "taskTimeAdd" => "timers@add", "deleteTimer" => "timers@delete", "endtimer" => "timers@end", "gettimerinfo" => "timers@getSingle", "invoiceItems" => "timers@invoiceItems", "prepareInvoiceTimers" => "timers@prepareInvoiceTimers", "starttimer" => "timers@start", "updateTimer" => "timers@update", "notify" => "notify@staff", "sendEmail" => "notify@sendEmail", "associateTicket" => "tickets@associateTicket", "doAssociateTicket" => "tickets@doAssociateTicket"];

    public function dispatch($action, $project = NULL, $ajaxModal = false)
    {
        $action = $this->routes[$action];
        if (!$action) {
            throw new Exception("Invalid action requested");
        }
        $action = explode("@", $action);
        list($class, $method) = $action;
        switch ($project) {
            case NULL:
                $class = "WHMCS\\Module\\Addon\\ProjectManagement\\" . ucfirst($class);
                $response = $class::$method();
                break;
            default:
                if ($class == "project") {
                    $response = $project->{$method}();
                } else {
                    $response = $project->{$class}()->{$method}();
                }
                if (is_array($response)) {
                    $response = array_merge(["status" => $ajaxModal ? "success" : "1"], $response);
                } else {
                    $response = ["status" => $ajaxModal ? "error" : "0", "error" => "Unexpected response"];
                }
                return $response;
        }
    }
}
