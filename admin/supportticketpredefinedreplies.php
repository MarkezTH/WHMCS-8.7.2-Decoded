<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Manage Predefined Replies");
$aInt->title = $aInt->lang("support", "predefreplies");
$aInt->sidebar = "support";
$aInt->icon = "ticketspredefined";
$action = App::get_req_var("action");
if ($action == "parseMarkdown") {
    $markup = new WHMCS\View\Markup\Markup();
    $content = App::get_req_var("content");
    $aInt->setBodyContent(["body" => "<div class=\"markdown-content\">" . $markup->transform($content, "markdown") . "</div>"]);
    $aInt->output();
    WHMCS\Terminus::getInstance()->doExit();
}
if ($addreply == "true") {
    check_token("WHMCS.admin.default");
    checkPermission("Create Predefined Replies");
    $lastid = insert_query("tblticketpredefinedreplies", ["catid" => $catid, "name" => $name]);
    logActivity("Added New Predefined Reply - " . $title);
    redir("action=edit&id=" . $lastid);
}
if ($sub == "save") {
    check_token("WHMCS.admin.default");
    checkPermission("Manage Predefined Replies");
    $table = "tblticketpredefinedreplies";
    $array = ["catid" => $catid, "name" => $name, "reply" => $reply];
    $where = ["id" => $id];
    update_query($table, $array, $where);
    logActivity("Modified Predefined Reply (ID: " . $id . ")");
    redir("catid=" . $catid . "&save=true");
}
if ($sub == "savecat") {
    check_token("WHMCS.admin.default");
    checkPermission("Manage Predefined Replies");
    $table = "tblticketpredefinedcats";
    $array = ["parentid" => $parentid, "name" => $name];
    $where = ["id" => $id];
    update_query($table, $array, $where);
    logActivity("Modified Predefined Reply Category (ID: " . $id . ")");
    redir("catid=" . $parentid . "&savecat=true");
}
if ($addcategory == "true") {
    check_token("WHMCS.admin.default");
    checkPermission("Create Predefined Replies");
    insert_query("tblticketpredefinedcats", ["parentid" => $catid, "name" => $catname]);
    logActivity("Added New Predefined Reply Category - " . $catname);
    redir("catid=" . $catid . "&addedcat=true");
    exit;
}
if ($sub == "delete") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Predefined Replies");
    delete_query("tblticketpredefinedreplies", ["id" => $id]);
    logActivity("Deleted Predefined Reply (ID: " . $id . ")");
    redir("catid=" . $catid . "&delete=true");
}
if ($sub == "deletecategory") {
    check_token("WHMCS.admin.default");
    checkPermission("Delete Predefined Replies");
    delete_query("tblticketpredefinedreplies", ["catid" => $id]);
    delete_query("tblticketpredefinedcats", ["id" => $id]);
    deletepredefcat($id);
    logActivity("Deleted Predefined Reply Category (ID: " . $id . ")");
    redir("catid=" . $catid . "&deletecat=true");
}
ob_start();
if ($action == "") {
    if ($addedcat) {
        infoBox($aInt->lang("global", "success"), $aInt->lang("support", "predefaddedcat"));
    }
    if ($save) {
        infoBox($aInt->lang("global", "success"), $aInt->lang("support", "predefsave"));
    }
    if ($savecat) {
        infoBox($aInt->lang("global", "success"), $aInt->lang("support", "predefsavecat"));
    }
    if ($delete) {
        infoBox($aInt->lang("global", "success"), $aInt->lang("support", "predefdelete"));
    }
    if ($deletecat) {
        infoBox($aInt->lang("global", "success"), $aInt->lang("support", "predefdeletecat"));
    }
    echo $infobox;
    if ($catid) {
        $catid = get_query_val("tblticketpredefinedcats", "id", ["id" => $catid]);
    }
    $aInt->deleteJSConfirm("doDelete", "support", "predefdelsure", $_SERVER["PHP_SELF"] . "?catid=" . $catid . "&sub=delete&id=");
    $aInt->deleteJSConfirm("doDeleteCat", "support", "predefdelcatsure", $_SERVER["PHP_SELF"] . "?catid=" . $catid . "&sub=deletecategory&id=");
    echo $aInt->beginAdminTabs([$aInt->lang("support", "addcategory"), $aInt->lang("support", "addpredef"), $aInt->lang("global", "searchfilter")]);
    echo "\n<form method=\"post\" action=\"";
    echo $whmcs->getPhpSelf();
    echo "?catid=";
    echo $catid;
    echo "&addcategory=true\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
    echo AdminLang::trans("support.catname");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"catname\" class=\"form-control\"></td></tr>\n</table>\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
    echo $aInt->lang("support", "addcategory");
    echo "\" class=\"btn btn-primary\" />\n</div>\n</form>\n\n";
    echo $aInt->nextAdminTab();
    echo "\n";
    if ($catid != "") {
        echo "<form method=\"post\" action=\"";
        echo $whmcs->getPhpSelf();
        echo "?catid=";
        echo $catid;
        echo "&addreply=true\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
        echo AdminLang::trans("support.replyname");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"name\" class=\"form-control\"></td></tr>\n</table>\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
        echo $aInt->lang("support", "addarticle");
        echo "\" class=\"btn btn-primary\" />\n</div>\n</form>\n";
    } else {
        echo $aInt->lang("support", "pdnotoplevel");
    }
    echo "\n";
    echo $aInt->nextAdminTab();
    echo "\n<form action=\"";
    echo $whmcs->getPhpSelf();
    echo "\" method=\"post\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
    echo AdminLang::trans("support.replyname");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"title\" value=\"";
    echo $title;
    echo "\" class=\"form-control\" /></td></tr>\n<tr><td class=\"fieldlabel\">";
    echo AdminLang::trans("mergefields.message");
    echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"message\" value=\"";
    echo $message;
    echo "\" class=\"form-control\" /></td></tr>\n</table>\n<input type=\"hidden\" name=\"search\" value=\"search\" />\n\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
    echo $aInt->lang("global", "searchfilter");
    echo "\" class=\"btn btn-primary\" />\n</div>\n\n</form>\n\n";
    echo $aInt->endAdminTabs();
    if ($catid == "") {
        $catid = "0";
    }
    if ($catid != "0") {
        $result = select_query("tblticketpredefinedcats", "", ["id" => $catid]);
        $data = mysql_fetch_array($result);
        $catparentid = $data["parentid"];
        $catname = $data["name"];
        $catbreadcrumbnav = " > <a href=\"supportticketpredefinedreplies.php?catid=" . $catid . "\">" . $catname . "</a>";
        while ($catparentid != "0") {
            $result = select_query("tblticketpredefinedcats", "", ["id" => $catparentid]);
            $data = mysql_fetch_array($result);
            $cattempid = $data["id"];
            $catparentid = $data["parentid"];
            $catname = $data["name"];
            $catbreadcrumbnav = " > <a href=\"supportticketpredefinedreplies.php?catid=" . $cattempid . "\">" . $catname . "</a>" . $catbreadcrumbnav;
        }
        $breadcrumbnav .= $catbreadcrumbnav;
    }
    if (!$search) {
        echo "<br><p>" . AdminLang::trans("support.youarehere") . ": <a href=\"" . $whmcs->getPhpSelf() . "\">" . AdminLang::trans("support.toplevel") . "</a> " . $breadcrumbnav . "</p>";
    }
    $result = select_query("tblticketpredefinedcats", "", ["parentid" => $catid], "name", "ASC");
    $numcats = mysql_num_rows($result);
    echo "\n";
    if ($numcats != "0" && !$search) {
        echo "<div class=\"browse-section-title\">\n    ";
        echo AdminLang::trans("support.browsebycategory");
        echo "</div>\n\n<div class=\"row\">\n";
        if ($catid == "") {
            $catid = "0";
        }
        $result = select_query("tblticketpredefinedcats", "", ["parentid" => $catid], "name", "ASC");
        $i = 0;
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $name = $data["name"];
            $result3 = select_query("tblticketpredefinedreplies", "id", ["catid" => $id]);
            $numarticles = mysql_num_rows($result3);
            echo "<div class=\"col-md-4 col-sm-6\">" . DI::make("asset")->imgTag("folder.gif", "Folder", ["align" => "absmiddle"]) . "<a href=\"?catid=" . $id . "\"><b>" . $name . "</b></a> (" . $numarticles . ") <a href=\"?action=editcat&id=" . $id . "\">" . "<img src=\"images/edit.gif\" align=\"absmiddle\" border=\"0\" alt=\"" . AdminLang::trans("global.edit") . "\" /></a> " . "<a href=\"#\" onClick=\"doDeleteCat(" . $id . ");return false\">" . "<img src=\"images/delete.gif\" align=\"absmiddle\" border=\"0\"alt=\"" . AdminLang::trans("global.delete") . "\" /></a><br>" . $description . "</div>";
        }
        echo "</div>\n\n";
    } else {
        if ($catid == "0" && !$search) {
            echo "<p><b>" . $aInt->lang("support", "nocatsfound") . "</b></p>";
        }
    }
    $where = "";
    if (!$search) {
        $where .= " AND catid='" . db_escape_string($catid) . "'";
    }
    if ($title) {
        $where .= " AND name LIKE '%" . db_escape_string($title) . "%'";
    }
    if ($message) {
        $where .= " AND reply LIKE '%" . db_escape_string($message) . "%'";
    }
    if ($where) {
        $where = substr($where, 5);
    }
    $result = select_query("tblticketpredefinedreplies", "", $where, "name", "ASC");
    $numarticles = mysql_num_rows($result);
    if ($search) {
        echo "<br><p>" . AdminLang::trans("support.youarehere") . ": <a href=\"" . $whmcs->getPhpSelf() . "\">" . AdminLang::trans("support.toplevel") . "</a>  > <a href=\"" . $whmcs->getPhpSelf() . "\">" . AdminLang::trans("global.search") . "</a></p>";
    }
    if ($numarticles != "0") {
        echo "\n<p><b>";
        echo $aInt->lang("support", "replies");
        echo "</b></p>\n\n<table width=100%><tr>\n";
        $result = select_query("tblticketpredefinedreplies", "", $where, "name", "ASC");
        while ($data = mysql_fetch_array($result)) {
            $id = $data["id"];
            $name = $data["name"];
            $reply = strip_tags(stripslashes($data["reply"]));
            $reply = substr($reply, 0, 150) . "...";
            echo "<p>" . DI::make("asset")->imgTag("article.gif", "Article", ["align" => "absmiddle"]) . "<a href=\"?action=edit&id=" . $id . "\"><b>" . $name . "</b></a> <a href=\"#\" onClick=\"doDelete(" . $id . ");return false\"><img src=\"images/delete.gif\" align=\"absmiddle\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\" /></a><br>" . $reply . "</p>";
        }
        echo "</tr></table>\n\n";
    } else {
        if ($catid != "0" || $search) {
            echo "<p><b>" . $aInt->lang("support", "norepliesfound") . "</b></p>";
        }
    }
    echo "\n";
} else {
    if ($action == "edit") {
        $result = select_query("tblticketpredefinedreplies", "", ["id" => $id]);
        $data = mysql_fetch_array($result);
        $catid = $data["catid"];
        $name = $data["name"];
        $reply = $data["reply"];
        $aInt->addMarkdownEditor("predefinedReplyMDE", "predefined_reply_" . md5($id . WHMCS\Session::get("adminid")), "predefinedReply");
        echo "\n<form method=\"post\" action=\"";
        echo $whmcs->getPhpSelf();
        echo "?sub=save&id=";
        echo $id;
        echo "\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
        echo AdminLang::trans("support.replyname");
        echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"name\" value=\"";
        echo $name;
        echo "\" class=\"form-control\"></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo AdminLang::trans("support.category");
        echo "</td><td class=\"fieldarea\"><select name=\"catid\" class=\"form-control select-inline\">";
        buildcategorieslist(0, 0);
        echo "</select></td></tr>\n<tr><td class=\"fieldlabel\">";
        echo AdminLang::trans("mergefields.title");
        echo "</td><td class=\"fieldarea\">[NAME] - ";
        echo AdminLang::trans("mergefields.ticketname");
        echo "<br />[FIRSTNAME] - ";
        echo AdminLang::trans("fields.firstname");
        echo "<br />[EMAIL] - ";
        echo AdminLang::trans("mergefields.ticketemail");
        echo "</td></tr>\n</table>\n<br>\n<textarea name=\"reply\" id=\"predefinedReply\" rows=18 style=\"width:100%\">";
        echo $reply;
        echo "</textarea>\n<div class=\"btn-container\">\n    <input type=\"submit\" value=\"";
        echo AdminLang::trans("global.savechanges");
        echo "\" class=\"btn btn-primary\">\n    <input type=\"button\" value=\"";
        echo AdminLang::trans("global.cancelchanges");
        echo "\" class=\"btn btn-default\" onclick=\"history.go(-1)\" />\n</div>\n</form>\n\n";
    } else {
        if ($action == "editcat") {
            $result = select_query("tblticketpredefinedcats", "", ["id" => $id]);
            $data = mysql_fetch_array($result);
            $parentid = $catid = $data["parentid"];
            $name = stripslashes($data["name"]);
            echo "\n<form method=\"post\" action=\"";
            echo $whmcs->getPhpSelf();
            echo "?catid=";
            echo $parentid;
            echo "&sub=savecat&id=";
            echo $id;
            echo "\">\n<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n<tr><td width=\"15%\" class=\"fieldlabel\">";
            echo AdminLang::trans("support.parentcat");
            echo "</td><td class=\"fieldarea\"><select name=\"parentid\" class=\"form-control\"><option value=\"\">";
            echo AdminLang::trans("support.toplevel");
            buildcategorieslist(0, 0, $id);
            echo "</select></td></tr>\n<tr><td class=\"fieldlabel\">";
            echo AdminLang::trans("support.catname");
            echo "</td><td class=\"fieldarea\"><input type=\"text\" name=\"name\" value=\"";
            echo $name;
            echo "\" class=\"form-control\"></td></tr>\n</table>\n<div class=\"btn-container\">\n    <p align=\"center\"><input type=\"submit\" value=\"";
            echo AdminLang::trans("global.savechanges");
            echo "\" class=\"btn btn-primary\"> <input type=\"button\" value=\"";
            echo AdminLang::trans("global.cancelchanges");
            echo "\" class=\"btn btn-default\" onclick=\"history.go(-1)\" /></p>\n</div>\n</form>\n\n";
        }
    }
}
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
function buildCategoriesList($level, $parentlevel, $exclude = "")
{
    global $catid;
    $result = select_query("tblticketpredefinedcats", "", ["parentid" => $level], "name", "ASC");
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $parentid = $data["parentid"];
        $category = $data["name"];
        if ($id != $exclude) {
            echo "<option value=\"" . $id . "\"";
            if ($id == $catid) {
                echo " selected";
            }
            echo ">";
            for ($i = 1; $i <= $parentlevel; $i++) {
                echo "- ";
            }
            echo $category . "</option>";
            buildCategoriesList($id, $parentlevel + 1);
        }
    }
}

function deletePreDefCat($catid)
{
    $result = select_query("tblticketpredefinedcats", "", ["parentid" => $catid]);
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        delete_query("tblticketpredefinedreplies", ["catid" => $id]);
        delete_query("tblticketpredefinedcats", ["id" => $id]);
        deletePreDefCat($id);
    }
}
