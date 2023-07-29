<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Configure Spam Control");
$aInt->title = $aInt->lang("stspamcontrol", "stspamcontroltitle");
$aInt->sidebar = "config";
$aInt->icon = "spamcontrol";
$aInt->helplink = "Email Piping Spam Control";
$action = $whmcs->get_req_var("action");
if ($action == "add") {
    check_token("WHMCS.admin.default");
    $type = $whmcs->get_req_var("type");
    $spamvalue = $whmcs->get_req_var("spamvalue");
    logAdminActivity("Spam Control Record Created: Type: '" . ucfirst($type) . "' - Content: '" . $spamvalue . "'");
    insert_query("tblticketspamfilters", ["type" => $type, "content" => $spamvalue]);
    redir("added=1");
}
if ($action == "delete") {
    check_token("WHMCS.admin.default");
    $id = (int) $whmcs->get_req_var("id");
    $spamFilter = Illuminate\Database\Capsule\Manager::table("tblticketspamfilters")->find($id);
    logAdminActivity("Spam Control Record Deleted: Type: '" . ucfirst($spamFilter->type) . "' - Content: '" . $spamFilter->content . "'");
    delete_query("tblticketspamfilters", ["id" => $id]);
    redir("deleted=1");
}
ob_start();
$jscode = "function doDelete(id,num) {\nif (confirm(\"" . $aInt->lang("stspamcontrol", "delsurespamcontrol", 1) . "\")) {\nwindow.location='" . $_SERVER["PHP_SELF"] . "?action=delete&id='+id+'&tabnum='+num+'" . generate_token("link") . "';\n}}";
if (App::getFromRequest("added")) {
    infoBox($aInt->lang("stspamcontrol", "spamcontrolupdatedtitle"), $aInt->lang("stspamcontrol", "spamcontrolupdatedadded"));
}
if (App::getFromRequest("deleted")) {
    infoBox($aInt->lang("stspamcontrol", "spamcontrolupdatedtitle"), $aInt->lang("stspamcontrol", "spamcontrolupdateddel"));
}
echo $infobox ?? "";
echo "\n<form method=\"post\" action=\"";
echo App::getPhpSelf();
echo "?action=add\">\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <tr>\n            <td width=\"20%\" class=\"fieldlabel\">\n                <b>";
echo AdminLang::trans("global.add");
echo ":</b>\n                ";
echo AdminLang::trans("stspamcontrol.typeval");
echo "            </td>\n            <td class=\"fieldarea\">\n                <div class=\"col-sm-3\">\n                    <select name=\"type\" class=\"form-control\" id=\"selectBlockType\">\n                        <option value=\"sender\">\n                            ";
echo AdminLang::trans("stspamcontrol.sender");
echo "                        </option>\n                        <option value=\"subject\">\n                            ";
echo AdminLang::trans("stspamcontrol.subject");
echo "                        </option>\n                        <option value=\"phrase\">\n                            ";
echo AdminLang::trans("stspamcontrol.phrase");
echo "                        </option>\n                    </select>\n                </div>\n                <div class=\"col-sm-5\">\n                    <input type=\"text\" name=\"spamvalue\" size=\"50\" class=\"form-control\" id=\"inputSpamValue\">\n                </div>\n                <div class=\"col-sm-2\">\n                    <button type=\"submit\" id=\"btnAddNew\" class=\"btn btn-primary\">\n                        ";
echo AdminLang::trans("stspamcontrol.addnewsc");
echo "                    </button>\n                </div>\n            </td>\n        </tr>\n    </table>\n</form>\n\n<br>\n\n";
echo $aInt->beginAdminTabs([$aInt->lang("stspamcontrol", "tab1"), $aInt->lang("stspamcontrol", "tab2"), $aInt->lang("stspamcontrol", "tab3")], true);
$nums = ["0", "1", "2"];
foreach ($nums as $num) {
    if ($num == 0) {
        $filtertype = "sender";
    } else {
        if ($num == 1) {
            $filtertype = "subject";
        } else {
            if ($num == 2) {
                $filtertype = "phrase";
            }
        }
    }
    $result = select_query("tblticketspamfilters", "COUNT(*)", ["type" => $filtertype]);
    $data = mysql_fetch_array($result);
    $numrows = $data[0];
    $aInt->sortableTableInit("id", "ASC");
    $tabledata = [];
    $result = select_query("tblticketspamfilters", "", ["type" => $filtertype], "content", "ASC", $page * $limit . "," . $limit);
    while ($data = mysql_fetch_array($result)) {
        $id = $data["id"];
        $content = $data["content"];
        $tabledata[] = [$content, "<a href=\"#\" onClick=\"doDelete('" . $id . "','" . $num . "');return false\"><img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . $aInt->lang("global", "delete") . "\"></a>"];
    }
    echo $aInt->sortableTable([$aInt->lang("fields", "content"), ""], $tabledata);
    echo $aInt->nextAdminTab();
}
echo $aInt->endAdminTabs();
$content = ob_get_contents();
ob_end_clean();
$aInt->content = $content;
$aInt->jquerycode = $jquerycode;
$aInt->jscode = $jscode;
$aInt->display();
