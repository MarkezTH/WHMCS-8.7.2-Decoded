<?php

namespace WHMCS\Module\Addon\ProjectManagement;

class Tasks extends BaseProjectEntity
{
    protected function get($taskId = NULL)
    {
        $tasks = [];
        $where = ["projectid" => $this->project->id];
        if ($taskId) {
            $where["id"] = $taskId;
        }
        $result = select_query("mod_projecttasks", "", $where, "order", "ASC");
        $taskTimes = $this->project->timers()->getTaskTimes();
        while ($data = mysql_fetch_array($result)) {
            $taskid = $data["id"];
            $task = $data["task"];
            $taskadminid = $data["adminid"];
            $tasknotes = $data["notes"];
            $taskcompleted = $data["completed"];
            $taskadmin = $taskadminid ? "<span class=\"label label-assigned-user\" data-id=\"" . $data["adminid"] . "\">" . getAdminName($data["adminid"]) . "</span>" : "<span class=\"label label-assigned-user\" data-id=\"0\">Unassigned</span>";
            $taskduedate = "<span class=\"label label-assigned-user\"><i class=\"fas fa-calendar-alt\"></i> " . Helper::getFriendlyDaysToGo($data["duedate"], $this->project->getLanguage()) . ($data["duedate"] != "0000-00-00" ? " (" . fromMySQLDate($data["duedate"]) . ")" : "") . "</span>";
            $tasks[] = ["id" => $taskid, "task" => $task, "adminId" => $taskadminid, "assigned" => $taskadmin, "duedate" => $taskduedate, "rawDueDate" => $data["duedate"] != "0000-00-00" ? fromMySQLDate($data["duedate"]) : "", "notes" => $tasknotes, "completed" => $taskcompleted, "totalTime" => $taskTimes[$taskid] ? $this->project->timers()->formatTimerSecondsToReadableTime($taskTimes[$taskid]) : "00:00:00"];
        }
        return $tasks;
    }

    public function listall()
    {
        return $this->get();
    }

    public function getSingle($taskId = NULL)
    {
        if (is_null($taskId)) {
            $taskId = \App::getFromRequest("taskid");
        }
        return ["task" => $this->get($taskId)];
    }

    public function getTaskSummary()
    {
        return project_management_tasksstatus($this->project->id, $vars);
    }

    public function add()
    {
        if (!$this->project->permissions()->check("Create Tasks")) {
            throw new Exception("You don't have permission to create tasks.");
        }
        $task = trim(\App::getFromRequest("task"));
        $notes = trim(\App::getFromRequest("notes"));
        $assignedTo = \App::getFromRequest("assignid");
        $dueDate = \App::getFromRequest("duedate");
        if (!$task) {
            throw new Exception("Task is required");
        }
        $maxorder = get_query_val("mod_projecttasks", "MAX(`order`)", ["projectid" => $this->project->id]);
        $newTaskId = insert_query("mod_projecttasks", ["projectid" => $this->project->id, "task" => $task, "notes" => $notes, "adminid" => $assignedTo, "created" => "now()", "duedate" => toMySQLDate($dueDate), "completed" => "0", "billed" => "0", "order" => $maxorder + 1]);
        $this->project->log()->add("Task Added: " . $task);
        $this->project->notify()->staff([["field" => "Task Added", "oldValue" => "N/A", "newValue" => $task]]);
        $data = $this->get($newTaskId);
        return ["newTaskId" => $newTaskId, "newTask" => $data, "summary" => $this->getTaskSummary(), "editPermission" => $this->project->permissions()->check("Edit Tasks"), "deletePermission" => $this->project->permissions()->check("Delete Tasks")];
    }

    public function delete()
    {
        if (!$this->project->permissions()->check("Delete Tasks")) {
            throw new Exception("You don't have permission to delete tasks.");
        }
        $taskId = \App::getFromRequest("taskid");
        $task = get_query_val("mod_projecttasks", "task", ["projectid" => $this->project->id, "id" => $taskId]);
        delete_query("mod_projecttasks", ["projectid" => $this->project->id, "id" => $taskId]);
        $this->project->log()->add("Task Deleted: " . $task);
        $this->project->notify()->staff([["field" => "Task Deleted", "oldValue" => $task, "newValue" => ""]]);
        return ["deletedTaskId" => $taskId, "summary" => $this->getTaskSummary()];
    }

    public function toggleStatus()
    {
        $taskId = \App::getFromRequest("taskid");
        $where = ["projectid" => $this->project->id, "id" => $taskId];
        $data = get_query_vals("mod_projecttasks", "task, completed", $where);
        $task = $data["task"];
        $status = $data["completed"];
        $newStatus = $status ? "0" : "1";
        update_query("mod_projecttasks", ["completed" => $newStatus], $where);
        $newStatusText = $status ? "Incomplete" : "Completed";
        $this->project->log()->add("Task Marked " . $newStatusText . ": " . $task);
        $this->project->notify()->staff([["field" => "Task \"" . $task . "\" Status Changed", "oldValue" => $status ? "Completed" : "Incomplete", "newValue" => $newStatusText]]);
        return ["taskId" => $taskId, "isCompleted" => $newStatus, "summary" => $this->getTaskSummary()];
    }

    public function assign()
    {
        if (!$this->project->permissions()->check("Edit Tasks")) {
            throw new Exception("You don't have permission to edit tasks.");
        }
        $taskId = \App::getFromRequest("taskid");
        $adminId = (int) \App::getFromRequest("admin");
        $taskData = get_query_vals("mod_projecttasks", "task,adminid", ["projectid" => $this->project->id, "id" => $taskId]);
        $task = $taskData["task"];
        $currentAdminId = (int) $taskData["adminid"];
        $projectChanges = [];
        $admins = Helper::getAdmins();
        if ($adminId) {
            if ($currentAdminId != $adminId) {
                $admin = \WHMCS\User\Admin::findOrFail($adminId);
                $adminId = $admin->id;
                $this->project->log()->add("Task Assigned to " . $admin->fullName . ": " . $task);
                $currentAdmin = "Unassigned";
                if ($currentAdminId && array_key_exists($currentAdminId, $admins)) {
                    $currentAdmin = $admins[$currentAdminId];
                }
                $projectChanges[] = ["field" => "Task Assigned", "oldValue" => $currentAdmin, "newValue" => $admin->fullName];
            }
        } else {
            if ($currentAdminId !== $adminId) {
                $currentAdmin = "Unknown";
                if (array_key_exists($currentAdminId, $admins)) {
                    $currentAdmin = $admins[$currentAdminId];
                }
                $projectChanges[] = ["field" => "Task Unassigned", "oldValue" => $currentAdmin, "newValue" => ""];
                $this->project->log()->add("Task Unassigned: " . $task);
            }
        }
        if ($projectChanges) {
            \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", "=", $this->project->id)->where("id", "=", $taskId)->update(["adminid" => $adminId]);
            $this->project->notify()->staff($projectChanges);
        }
        return ["taskId" => $taskId, "isCompleted" => 1];
    }

    public function setDueDate()
    {
        if (!$this->project->permissions()->check("Edit Tasks")) {
            throw new Exception("You don't have permission to edit tasks.");
        }
        $taskId = \App::getFromRequest("taskid");
        $dueDate = \App::getFromRequest("duedate");
        $taskData = get_query_vals("mod_projecttasks", "task,duedate", ["projectid" => $this->project->id, "id" => $taskId]);
        $dueDateMySql = toMySQLDate($dueDate);
        $currentDueDate = $taskData["duedate"];
        $task = $taskData["task"];
        if ($dueDateMySql != $currentDueDate) {
            \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", "=", $this->project->id)->where("id", "=", $taskId)->update(["duedate" => $dueDateMySql]);
            $this->project->log()->add("Task Due Date set to " . $dueDate . ": " . $task);
            $this->project->notify()->staff([["field" => "Task \"" . $task . "\" Due Date Changed", "oldValue" => fromMySQLDate($currentDueDate), "newValue" => $dueDate]]);
        }
        $daysLeft = Helper::getFriendlyDaysToGo($dueDateMySql, $this->project->getLanguage());
        return ["taskId" => $taskId, "isCompleted" => 1, "dueDate" => "<span class=\"label label-assigned-user\"><i class=\"fas fa-calendar-alt\"></i> " . $daysLeft . " (" . $dueDate . ")</span>"];
    }

    public function edit()
    {
        if (!$this->project->permissions()->check("Edit Tasks")) {
            throw new Exception("You don't have permission to edit tasks.");
        }
        $taskId = \App::getFromRequest("taskid");
        $adminId = (int) \App::getFromRequest("admin");
        $task = \App::getFromRequest("task");
        $notes = \App::getFromRequest("notes");
        $taskData = get_query_vals("mod_projecttasks", "", ["projectid" => $this->project->id, "id" => $taskId]);
        if ($adminId) {
            $adminId = \WHMCS\User\Admin::findOrFail($adminId, ["id"])->id;
        }
        $dueDate = \App::getFromRequest("duedate");
        if (!$dueDate) {
            $dueDate = fromMySQLDate("0000-00-00");
        }
        $projectChanges = [];
        $permission = new Permission();
        if (!$permission->check("Edit Tasks")) {
            throw new Exception("You don't have permission to edit tasks.");
        }
        if ($taskData["task"] != $task) {
            $projectChanges[] = ["field" => "Task Name Changed", "oldValue" => $task, "newValue" => $taskData["task"]];
        }
        if ($taskData["adminid"] != $adminId) {
            $admins = Helper::getAdmins();
            $newAdmin = "Unassigned";
            if ($adminId) {
                $newAdmin = "Unknown";
                if (array_key_exists($adminId, $admins)) {
                    $newAdmin = $admins[$adminId];
                }
            }
            $currentAdmin = "Unassigned";
            if ($taskData["adminid"]) {
                $currentAdmin = "Unknown";
                if (array_key_exists($taskData["adminid"], $admins)) {
                    $currentAdmin = $admins[$taskData["adminid"]];
                }
            }
            $projectChanges[] = ["field" => "Task Association Changed", "oldValue" => $currentAdmin, "newValue" => $newAdmin];
        }
        if ($taskData["notes"] != $notes) {
            $projectChanges[] = ["field" => "Task Notes Changed", "oldValue" => $taskData["notes"], "newValue" => $notes];
        }
        if ($taskData["duedate"] != toMySQLDate($dueDate)) {
            $projectChanges[] = ["field" => "Task Due Date Changed", "oldValue" => $dueDate, "newValue" => fromMySQLDate($taskData["duedate"])];
        }
        if ($projectChanges) {
            \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", "=", $this->project->id)->where("id", "=", $taskId)->update(["task" => $task, "notes" => $notes, "adminid" => $adminId, "duedate" => toMySQLDate($dueDate)]);
            $this->project->notify()->staff($projectChanges);
            $this->project->log()->add("Task Modified: " . $task);
        }
        return ["taskId" => $taskId, "isCompleted" => 1, "task" => $this->get($taskId)];
    }

    public function search()
    {
        $projectId = \App::getFromRequest("project");
        $search = \App::getFromRequest("search");
        $searchResults = [];
        $projects = \WHMCS\Database\Capsule::table("mod_project")->where(function (\Illuminate\Database\Query\Builder $query) use($search) {
            $query->where("id", "like", "%" . $search . "%");
            $query->orWhere("title", "like", "%" . $search . "%");
        })->where("id", "!=", $projectId)->get(["id", "title"])->all();
        foreach ($projects as $project) {
            $searchResults[] = ["id" => "p" . $project->id, "name" => $project->title];
        }
        $results = Models\Task\Template::where("name", "like", "%" . $search . "%")->get(["id", "name"]);
        foreach ($results as $result) {
            $searchResults[] = ["id" => $result->id, "name" => $result->name];
        }
        return ["options" => $searchResults];
    }

    public function select()
    {
        $selectedItem = \App::getFromRequest("selected");
        $return = [];
        if (substr($selectedItem, 0, 1) == "p") {
            $tasks = \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", "=", substr($selectedItem, 1))->get()->all();
            foreach ($tasks as $task) {
                $return["tasks"][] = ["id" => $task->id, "name" => $task->task];
            }
            $return["reference"] = "tasks";
        } else {
            $taskTemplate = Models\Task\Template::find($selectedItem);
            $return["tasks"] = [];
            if ($taskTemplate) {
                foreach ($taskTemplate->tasks as $key => $task) {
                    $return["tasks"][] = ["id" => $key, "name" => $task["task"]];
                }
            }
            $return["reference"] = $selectedItem;
        }
        return $return;
    }

    public function import()
    {
        $reference = \App::getFromRequest("reference");
        $ids = \App::getFromRequest("taskId");
        $return = [];
        $newTasks = [];
        if ($reference == "tasks") {
            $tasks = \WHMCS\Database\Capsule::table("mod_projecttasks")->whereIn("id", $ids)->get()->all();
            $currentMaxOrder = (int) \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", "=", $this->project->id)->max("order");
            foreach ($tasks as $task) {
                $newTasks[] = $task->task;
                $currentMaxOrder++;
                $newTaskId = \WHMCS\Database\Capsule::table("mod_projecttasks")->insertGetId(["projectid" => $this->project->id, "task" => $task->task, "notes" => $task->notes, "adminid" => 0, "created" => \WHMCS\Carbon::now()->format("YYYY-mm-dd hh:ii:ss"), "duedate" => \WHMCS\Carbon::now()->format("YYYY-mm-dd"), "completed" => 0, "billed" => 0, "order" => $currentMaxOrder]);
                list($return["tasks"][]) = $this->get($newTaskId);
            }
        } else {
            $taskTemplate = Models\Task\Template::find($reference);
            $tasks = [];
            if ($taskTemplate) {
                $tasks = $taskTemplate->tasks;
            }
            $currentMaxOrder = (int) \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", "=", $this->project->id)->max("order");
            foreach ($tasks as $key => $task) {
                if (in_array($key, $ids)) {
                    $newTasks[] = $task->task;
                    $currentMaxOrder++;
                    $newTaskId = \WHMCS\Database\Capsule::table("mod_projecttasks")->insertGetId(["projectid" => $this->project->id, "task" => $task["task"], "notes" => $task["notes"], "adminid" => 0, "created" => \WHMCS\Carbon::now()->format("YYYY-mm-dd hh:ii:ss"), "duedate" => \WHMCS\Carbon::now()->format("YYYY-mm-dd"), "completed" => 0, "billed" => 0, "order" => $currentMaxOrder]);
                    list($return["tasks"][]) = $this->get($newTaskId);
                }
            }
        }
        if ($newTasks) {
            $this->project->notify()->staff([["field" => "Tasks Added", "oldValue" => "N/A", "newValue" => implode(", ", $newTasks)]]);
        }
        $return["summary"] = $this->getTaskSummary();
        $return["editPermission"] = $this->project->permissions()->check("Edit Tasks");
        $return["deletePermission"] = $this->project->permissions()->check("Delete Tasks");
        return $return;
    }

    public function saveList()
    {
        $taskListName = \App::getFromRequest("name");
        $existing = Models\Task\Template::where("name", $taskListName)->count();
        if ($existing && 0 < $existing) {
            throw new Exception("Unique Name Required");
        }
        $tasks = \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", $this->project->id)->get()->all();
        $tasksForList = [];
        foreach ($tasks as $task) {
            $tasksForList[] = ["task" => $task->task, "notes" => $task->notes, "adminid" => $task->adminid, "duedate" => $task->duedate];
        }
        if (!$tasksForList) {
            throw new Exception("No Tasks to Save");
        }
        $newTemplate = new Models\Task\Template();
        $newTemplate->name = $taskListName;
        $newTemplate->tasks = $tasksForList;
        $newTemplate->save();
        return ["success" => true, "taskListName" => $taskListName];
    }

    public function deleteTaskTemplate()
    {
        if (!(new Permission())->check("Master Admin Check")) {
            throw new Exception("Invalid Access Attempt");
        }
        $taskTemplateId = (int) \App::getFromRequest("template");
        $taskTemplate = Models\Task\Template::findOrFail($taskTemplateId);
        $templateName = $taskTemplate->name;
        $taskTemplate->delete();
        logAdminActivity("Project Management Task Template Deleted: " . $templateName);
        return ["success" => true, "successMsgTitle" => "Deleted", "successMsg" => "Template '" . $templateName . "' Deleted"];
    }

    public function saveOrder()
    {
        $taskOrder = \App::getFromRequest("task");
        foreach ($taskOrder as $order => $taskId) {
            \WHMCS\Database\Capsule::table("mod_projecttasks")->where("projectid", $this->project->id)->where("id", $taskId)->update(["order" => $order]);
        }
        return ["success" => true, "successMsgTitle" => "Updated", "successMsg" => "Task Sort Order Updated"];
    }
}
