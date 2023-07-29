<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Configure Security Questions");
$aInt->title = $aInt->lang("setup", "securityqs");
$aInt->sidebar = "config";
$aInt->icon = "securityquestions";
$aInt->helplink = "Security Questions";
$id = (int) $whmcs->get_req_var("id");
$action = $whmcs->get_req_var("action");
if ($action == "savequestion") {
    check_token("WHMCS.admin.default");
    $addquestion = $whmcs->get_req_var("addquestion");
    if ($id) {
        $question = WHMCS\User\User\SecurityQuestion::find($id);
        $redirect = "update";
    } else {
        $question = new WHMCS\User\User\SecurityQuestion();
        $redirect = "added";
    }
    $question->question = $addquestion;
    $question->save();
    redir($redirect . "=true");
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    try {
        $question = WHMCS\User\User\SecurityQuestion::withCount("users")->findOrFail($id);
        if (0 < $question->usersCount) {
            redir("deleteerror=true");
        } else {
            $question->delete();
            redir("deletesuccess=true");
        }
    } catch (Exception $e) {
        redir("");
    }
}
ob_start();
$infobox = "";
if (App::getFromRequest("deletesuccess")) {
    infoBox($aInt->lang("securityquestionconfig", "delsuccess"), $aInt->lang("securityquestionconfig", "delsuccessinfo"));
}
if (App::getFromRequest("deleteerror")) {
    infoBox($aInt->lang("securityquestionconfig", "error"), $aInt->lang("securityquestionconfig", "errorinfo"));
}
if (App::getFromRequest("added")) {
    infoBox($aInt->lang("securityquestionconfig", "addsuccess"), $aInt->lang("securityquestionconfig", "changesuccessinfo"));
}
if (App::getFromRequest("update")) {
    infoBox($aInt->lang("securityquestionconfig", "changesuccess"), $aInt->lang("securityquestionconfig", "changesuccessinfo"));
}
echo $infobox;
$aInt->deleteJSConfirm("doDelete", "securityquestionconfig", "delsuresecurityquestion", "?action=delete&id=");
echo "\n<h2>";
echo $aInt->lang("securityquestionconfig", "questions");
echo "</h2>\n\n";
$aInt->sortableTableInit("nopagination");
$result = select_query("tbladminsecurityquestions", "", "");
while ($data = mysql_fetch_assoc($result)) {
    $cnt = WHMCS\User\User::where("security_question_id", $data["id"])->count();
    $tabledata[] = [decrypt($data["question"]), $cnt, "<a href=\"" . $_SERVER["PHP_SELF"] . "?action=edit&id=" . $data["id"] . "\"><img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "edit") . "\"></a>", "<a href=\"#\" onClick=\"doDelete('" . $data["id"] . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
}
echo $aInt->sortableTable([$aInt->lang("securityquestionconfig", "question"), $aInt->lang("securityquestionconfig", "uses"), "", ""], $tabledata);
echo "\n<h2>";
$question = "";
if ($action == "edit") {
    $result = select_query("tbladminsecurityquestions", "", ["id" => $id]);
    $data = mysql_fetch_array($result);
    $question = decrypt($data["question"]);
    echo $aInt->lang("securityquestionconfig", "edit");
} else {
    echo $aInt->lang("securityquestionconfig", "add");
}
echo "</h2>\n\n<form method=\"post\" action=\"";
echo $whmcs->getPhpSelf();
echo "?action=savequestion&id=";
echo $id;
echo "\">\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"25%\" class=\"fieldlabel\">";
echo $aInt->lang("fields", "securityquestion");
echo "</td>\n            <td class=\"fieldarea\"><input type=\"text\" name=\"addquestion\" value=\"";
echo $question;
echo "\" class=\"form-control\" /></td>\n        </tr>\n    </table>\n    <div class=\"btn-container\">\n        <input type=\"submit\" value=\"";
echo $aInt->lang("global", "savechanges");
echo "\" class=\"btn btn-primary\" />\n    </div>\n</form>\n\n";
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
