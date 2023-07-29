<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$statuses = ["New" => ["count" => 0, "overdue" => 0], "Pending" => ["count" => 0, "overdue" => 0], "In Progress" => ["count" => 0, "overdue" => 0], "Completed" => ["count" => 0, "overdue" => 0], "Postponed" => ["count" => 0, "overdue" => 0]];
$todo_result = full_query("SELECT status, COUNT(*) AS count FROM tbltodolist GROUP BY status");
while ($todo = mysql_fetch_assoc($todo_result)) {
    $statuses[$todo["status"]]["count"] = $todo["count"];
}
$todo_over_due_result = full_query("SELECT status, COUNT(*) AS count FROM tbltodolist WHERE DATE(duedate) <= CURDATE() GROUP BY status");
while ($todo_over_due = mysql_fetch_assoc($todo_over_due_result)) {
    $statuses[$todo_over_due["status"]]["overdue"] = $todo_over_due["count"];
}
$apiresults = ["result" => "success", "totalresults" => 5];
foreach ($statuses as $key => $status) {
    $apiresults["todoitemstatuses"]["status"][] = ["type" => $key, "count" => $status["count"], "overdue" => $status["overdue"]];
}
$responsetype = "xml";
