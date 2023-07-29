<?php

require "init.php";
$id = (int) $whmcs->get_req_var("id");
$url = get_query_val("tbllinks", "link", ["id" => $id]);
if ($url) {
    update_query("tbllinks", ["clicks" => "+1"], ["id" => $id]);
    WHMCS\Cookie::set("LinkID", $id, "3m");
    run_hook("LinkTracker", ["linkid" => $id]);
    header("Location: " . WHMCS\Input\Sanitize::decode($url));
    exit;
}
redir("", "index.php");
