<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly.");
}

function autorelease_MetaData()
{
    return ["DisplayName" => "Auto Release", "APIVersion" => "1.0", "RequiresServer" => false, "AutoGenerateUsernameAndPassword" => false];
}

function autorelease_ConfigOptions()
{
    $depts = [];
    $depts[] = "0|None";
    $result = select_query("tblticketdepartments", "", "", "order", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $name = $data["name"];
        $depts[] = $id . "|" . strip_tags(WHMCS\Input\Sanitize::decode($name));
    }
    $adminUsers = [];
    $adminUsers[] = "0|Please Select";
    $admins = WHMCS\User\Admin::where("disabled", "=", false)->get();
    foreach ($admins as $admin) {
        $adminUsers[] = $admin->id . "|" . $admin->firstName . " " . $admin->lastName . " (" . $admin->username . ")";
    }
    $configarray = ["Create Action" => ["Type" => "dropdown", "Options" => "None,Add To-Do List Item,Create Support Ticket"], "Suspend Action" => ["Type" => "dropdown", "Options" => "None,Add To-Do List Item,Create Support Ticket"], "Unsuspend Action" => ["Type" => "dropdown", "Options" => "None,Add To-Do List Item,Create Support Ticket"], "Terminate Action" => ["Type" => "dropdown", "Options" => "None,Add To-Do List Item,Create Support Ticket"], "Renew Action" => ["Type" => "dropdown", "Options" => "None,Add To-Do List Item,Create Support Ticket"], "Support Dept ID" => ["Type" => "dropdown", "Options" => implode(",", $depts)], "Admin ID" => ["Type" => "dropdown", "Options" => implode(",", $adminUsers), "Description" => " Select the Admin User the API commands will be run as"]];
    return $configarray;
}

function autorelease_CreateAccount($params)
{
    $description = autorelease_buildDescriptionString($params, "was just auto provisioned");
    if ($params["configoption1"] == "Add To-Do List Item") {
        $todoarray["title"] = "Service Provisioned";
        $todoarray["description"] = $description;
        $todoarray["status"] = "Pending";
        $todoarray["duedate"] = date("Y-m-d");
        $todoarray["date"] = $todoarray["duedate"];
        insert_query("tbltodolist", $todoarray);
    } else {
        if ($params["configoption1"] == "Create Support Ticket") {
            $params["configoption6"] = explode("|", $params["configoption6"]);
            $params["configoption7"] = explode("|", $params["configoption7"]);
            if ($params["configoption6"][0] == "0") {
                return "Please select a Support Department ID in the product Module Settings";
            }
            if ($params["configoption7"][0] == "0") {
                return "Please select an Admin ID in the product Module Settings";
            }
            $postfields["action"] = "openticket";
            $postfields["clientid"] = $params["clientsdetails"]["userid"];
            $postfields["deptid"] = $params["configoption6"][0];
            $postfields["subject"] = "Service Provisioned";
            $postfields["message"] = $description;
            $postfields["priority"] = "Low";
            $response = localAPI($postfields["action"], $postfields, $params["configoption7"][0]);
            if ($response["result"] == "error") {
                return "An Error Occurred communicating with the API: " . $response["message"];
            }
        }
    }
    return "success";
}

function autorelease_SuspendAccount($params)
{
    $description = autorelease_buildDescriptionString($params, "requires suspension");
    if ($params["configoption2"] == "Add To-Do List Item") {
        $todoarray["title"] = "Service Suspension";
        $todoarray["description"] = $description;
        $todoarray["status"] = "Pending";
        $todoarray["duedate"] = date("Y-m-d");
        $todoarray["date"] = $todoarray["duedate"];
        insert_query("tbltodolist", $todoarray);
    } else {
        if ($params["configoption2"] == "Create Support Ticket") {
            $params["configoption6"] = explode("|", $params["configoption6"]);
            $params["configoption7"] = explode("|", $params["configoption7"]);
            if ($params["configoption6"][0] == "0") {
                return ["error" => "Please select a Support Department ID in the product Module Settings"];
            }
            if ($params["configoption7"][0] == "0") {
                return ["error" => "Please select an Admin ID in the product Module Settings"];
            }
            $postfields["action"] = "openticket";
            $postfields["clientid"] = $params["clientsdetails"]["userid"];
            $postfields["deptid"] = $params["configoption6"][0];
            $postfields["subject"] = "Service Suspension";
            $postfields["message"] = $description;
            $postfields["priority"] = "Low";
            $response = localAPI($postfields["action"], $postfields, $params["configoption7"][0]);
            if ($response["result"] == "error") {
                return "An Error Occurred communicating with the API: " . $response["message"];
            }
        }
    }
    return "success";
}

function autorelease_UnsuspendAccount($params)
{
    $description = autorelease_buildDescriptionString($params, "requires unsuspending");
    if ($params["configoption3"] == "Add To-Do List Item") {
        $todoarray["title"] = "Service Reactivation";
        $todoarray["description"] = $description;
        $todoarray["status"] = "Pending";
        $todoarray["duedate"] = date("Y-m-d");
        $todoarray["date"] = $todoarray["duedate"];
        insert_query("tbltodolist", $todoarray);
    } else {
        if ($params["configoption3"] == "Create Support Ticket") {
            $params["configoption6"] = explode("|", $params["configoption6"]);
            $params["configoption7"] = explode("|", $params["configoption7"]);
            if ($params["configoption6"][0] == "0") {
                return ["error" => "Please select a Support Department ID in the product Module Settings"];
            }
            if ($params["configoption7"][0] == "0") {
                return ["error" => "Please select an Admin ID in the product Module Settings"];
            }
            $postfields["action"] = "openticket";
            $postfields["clientid"] = $params["clientsdetails"]["userid"];
            $postfields["deptid"] = $params["configoption6"][0];
            $postfields["subject"] = "Service Reactivation";
            $postfields["message"] = $description;
            $postfields["priority"] = "Low";
            $response = localAPI($postfields["action"], $postfields, $params["configoption7"][0]);
            if ($response["result"] == "error") {
                return "An Error Occurred communicating with the API: " . $response["message"];
            }
        }
    }
    return "success";
}

function autorelease_TerminateAccount($params)
{
    $description = autorelease_buildDescriptionString($params, "requires termination");
    if ($params["configoption4"] == "Add To-Do List Item") {
        $todoarray["title"] = "Service Termination";
        $todoarray["description"] = $description;
        $todoarray["status"] = "Pending";
        $todoarray["duedate"] = date("Y-m-d");
        $todoarray["date"] = $todoarray["duedate"];
        insert_query("tbltodolist", $todoarray);
    } else {
        if ($params["configoption4"] == "Create Support Ticket") {
            $params["configoption6"] = explode("|", $params["configoption6"]);
            $params["configoption7"] = explode("|", $params["configoption7"]);
            if ($params["configoption6"][0] == "0") {
                return ["error" => "Please select a Support Department ID in the product Module Settings"];
            }
            if ($params["configoption7"][0] == "0") {
                return ["error" => "Please select an Admin ID in the product Module Settings"];
            }
            $postfields["action"] = "openticket";
            $postfields["clientid"] = $params["clientsdetails"]["userid"];
            $postfields["deptid"] = $params["configoption6"][0];
            $postfields["subject"] = "Service Termination";
            $postfields["message"] = $description;
            $postfields["priority"] = "Low";
            $response = localAPI($postfields["action"], $postfields, $params["configoption7"][0]);
            if ($response["result"] == "error") {
                return "An Error Occurred communicating with the API: " . $response["message"];
            }
        }
    }
    return "success";
}

function autorelease_Renew($params)
{
    $description = autorelease_buildDescriptionString($params, "was just renewed");
    if ($params["configoption5"] == "Add To-Do List Item") {
        $todoarray["title"] = "Service Renewal";
        $todoarray["description"] = $description;
        $todoarray["status"] = "Pending";
        $todoarray["duedate"] = date("Y-m-d");
        $todoarray["date"] = $todoarray["duedate"];
        insert_query("tbltodolist", $todoarray);
    } else {
        if ($params["configoption5"] == "Create Support Ticket") {
            $params["configoption6"] = explode("|", $params["configoption6"]);
            $params["configoption7"] = explode("|", $params["configoption7"]);
            if ($params["configoption6"][0] == "0") {
                return ["error" => "Please select a Support Department ID in the product Module Settings"];
            }
            if ($params["configoption7"][0] == "0") {
                return ["error" => "Please select an Admin ID in the product Module Settings"];
            }
            $postfields["action"] = "openticket";
            $postfields["clientid"] = $params["clientsdetails"]["userid"];
            $postfields["deptid"] = $params["configoption6"][0];
            $postfields["subject"] = "Service Renewal";
            $postfields["message"] = $description;
            $postfields["priority"] = "Low";
            $response = localAPI($postfields["action"], $postfields, $params["configoption7"][0]);
            if ($response["result"] == "error") {
                return "An Error Occurred communicating with the API: " . $response["message"];
            }
        }
    }
    return "success";
}

function autorelease_buildDescriptionString($params, $action)
{
    $description = empty($params["addonId"]) ? "Service ID # " . $params["serviceid"] . " " : "Addon ID # " . $params["addonId"] . " ";
    $description .= $action;
    return $description;
}
