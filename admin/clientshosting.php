<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View Clients Products/Services");
if ($userid && $hostingid) {
    redir("userid=" . $userid . "&id=" . $hostingid, "clientsservices.php");
}
if ($userid && $id) {
    redir("userid=" . $userid . "&id=" . $id, "clientsservices.php");
}
if ($id) {
    redir("id=" . $id, "clientsservices.php");
}
if ($userid) {
    redir("userid=" . $userid, "clientsservices.php");
}
redir("", "clientsservices.php");
