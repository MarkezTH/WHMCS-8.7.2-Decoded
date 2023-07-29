<?php

namespace WHMCS\View;

class Helper
{
    const ERROR_TITLE = "Critical Error";
    const ERROR_BODY = "Unknown Error";

    public static function applicationError($title = self::ERROR_TITLE, $body = self::ERROR_BODY, $exception = NULL)
    {
        if (is_null($title)) {
            $title = self::ERROR_TITLE;
        }
        if (is_null($body)) {
            $body = self::ERROR_BODY;
        }
        $body = nl2br("<h1>{{title}}</h1><p>" . $body . "</p>");
        if (\WHMCS\Utility\ErrorManagement::isDisplayErrorCurrentlyVisible() && ($exception instanceof \Exception || $exception instanceof \Error)) {
            $body .= "<p class=\"debug\">" . HtmlErrorPage::getHtmlStackTrace($exception) . "</p>";
        }
        $errorPage = new HtmlErrorPage($title, $body);
        return $errorPage->getHtmlErrorPage();
    }

    public static function generateCssFriendlyId($name, $title = "")
    {
        return preg_replace("/[^A-Za-z0-9_-]/", "_", $name . ($title != "" ? "-" . $title : ""));
    }

    public static function generateCssFriendlyClassName($value)
    {
        return preg_replace("/[^a-z0-9_-]/", "-", strtolower(trim(strip_tags($value))));
    }

    public static function buildTagCloud($tags = [])
    {
        $tagCloud = "";
        $tagCount = ceil(count($tags) / 4);
        $fontSize = "24";
        $minFontSize = "10";
        $fontSizes = [];
        $i = 0;
        $firstTag = true;
        foreach ($tags as $tag => $count) {
            $tagFontSize = $fontSize;
            if ($count <= 1) {
                $tagFontSize = "12";
            }
            if ($tagFontSize < $minFontSize) {
                $tagFontSize = $minFontSize;
            }
            if (isset($fontSizes[$count])) {
                $tagFontSize = $fontSizes[$count];
            } else {
                $fontSizes[$count] = $tagFontSize;
            }
            $cleanTag = strip_tags($tag);
            $htmlContent = htmlspecialchars($cleanTag);
            $tagCloud .= "<a href=\"" . routePath("knowledgebase-tag-view", $cleanTag) . "\" style=\"font-size:" . $tagFontSize . "px;\">" . $htmlContent . "</a>" . PHP_EOL;
            $i++;
            if ($i == $tagCount || $firstTag) {
                $fontSize -= 4;
                $i = 0;
            }
            $firstTag = false;
        }
        return $tagCloud;
    }

    public static function alert($text, $alertType = "info", $additionalClasses = "")
    {
        if (!in_array($alertType, ["success", "info", "warning", "danger"])) {
            $alertType = "info";
        }
        switch ($alertType) {
            case "success":
                $icon = "<i class=\"fas fa-check-circle fa-3x pull-left\"></i>";
                break;
            case "warning":
                $icon = "<i class=\"fas fa-exclamation-circle fa-3x pull-left\"></i>";
                break;
            case "danger":
                $icon = "<i class=\"fas fa-times-circle fa-3x pull-left\"></i>";
                break;
            default:
                $icon = "<i class=\"fas fa-info-circle fa-3x pull-left\"></i>";
                $alert = "<div class=\"alert alert-" . $alertType . " clearfix";
                if ($additionalClasses) {
                    $alert .= " " . $additionalClasses;
                }
                $alert .= "\" role=\"alert\">" . $icon . "<div class=\"alert-text\">" . $text . "</div></div>";
                return $alert;
        }
    }

    public static function jsGrowlNotification($type, $titleLangKey, $msgLangKey)
    {
        if ($type == "success") {
            $type = "notice";
        } else {
            if (!in_array($type, ["error", "notice", "warning"])) {
                $type = "";
            }
        }
        return "jQuery.growl" . ($type ? "." . $type : "") . "({ title: \"" . addslashes(\AdminLang::trans($titleLangKey)) . "\", message: \"" . addslashes(\AdminLang::trans($msgLangKey)) . "\" });";
    }

    public static function getAssetVersionHash()
    {
        return substr(sha1(\App::getWHMCSInstanceID() . \App::getVersion()->getCanonical()), 0, 6);
    }

    public static function getDomainGroupLabel($group)
    {
        strtolower($group);
        switch (strtolower($group)) {
            case "hot":
                $groupInfo = "<span class=\"label label-danger\" data-group=\"hot\">" . \AdminLang::trans("domains.hot") . "</span>";
                break;
            case "new":
                $groupInfo = "<span class=\"label label-success\" data-group=\"new\">" . \AdminLang::trans("domains.new") . "</span>";
                break;
            case "sale":
                $groupInfo = "<span class=\"label label-warning\" data-group=\"sale\">" . \AdminLang::trans("domains.sale") . "</span>";
                break;
            default:
                $groupInfo = "";
                return $groupInfo;
        }
    }

    public static function getServerDropdownOptions($selectedServerId = 0)
    {
        $servers = $disabledServers = "";
        $serverData = \WHMCS\Database\Capsule::table("tblservers")->orderBy("name")->get(["id", "name", "disabled"])->all();
        foreach ($serverData as $server) {
            $id = $server->id;
            $serverName = $server->name;
            $serverDisabled = $server->disabled;
            if ($serverDisabled) {
                $serverName .= " (" . \AdminLang::trans("emailtpls.disabled") . ")";
            }
            $selected = "";
            if ($selectedServerId == $id) {
                $selected .= "selected=\"selected\"";
            }
            $serverTemp = "<option value=\"" . $id . "\" " . $selected . ">" . $serverName . "</option>";
            if ($serverDisabled) {
                $disabledServers .= $serverTemp;
            } else {
                $servers .= $serverTemp;
            }
        }
        return ["servers" => $servers, "disabledServers" => $disabledServers];
    }

    public static function confirmationModal($name, $question, $url = "")
    {
        $parsedUrl = parse_url($url);
        $output = [];
        if (is_array($parsedUrl) && isset($parsedUrl["query"])) {
            parse_str($parsedUrl["query"], $output);
        }
        $output = array_keys($output);
        $lastParamKey = end($output);
        $name = self::generateCssFriendlyId($name);
        $ok = \AdminLang::trans("global.ok");
        $cancel = \AdminLang::trans("global.cancel");
        $close = \AdminLang::trans("global.close");
        $langSure = \AdminLang::trans("global.areYouSure");
        return "<div class=\"modal fade\"\n     id=\"" . $name . "\"\n     tabindex=\"-1\"\n     role=\"dialog\"\n     aria-labelledby=\"" . $name . "Label\"\n     aria-hidden=\"true\"\n>\n    <form method=\"post\" action=\"" . $url . "\">\n        <input type=\"hidden\" name=\"" . $lastParamKey . "\" value=\"\" class=\"id-target\">\n        <div class=\"modal-dialog\">\n            <div class=\"modal-content panel panel-primary\">\n                <div class=\"modal-header panel-heading\">\n                    <button type=\"button\" class=\"close\" data-dismiss=\"modal\">\n                        <span aria-hidden=\"true\">&times;</span>\n                        <span class=\"sr-only\">" . $close . "</span>\n                    </button>\n                    <h4 class=\"modal-title\">" . $langSure . "</h4>\n                </div>\n                <div class=\"modal-body panel-body\">\n                    " . $question . "\n                </div>\n                <div class=\"modal-footer panel-footer\">\n                    <button type=\"button\"\n                            id=\"" . $name . "-cancel\"\n                            class=\"btn btn-default\"\n                            data-dismiss=\"modal\"\n                    >\n                        " . $cancel . "\n                    </button>\n                    <button type=\"submit\" id=\"" . $name . "-ok\" class=\"btn btn-primary\">\n                        " . $ok . "\n                    </button>\n                </div>\n            </div>\n        </div>\n    </form>\n</div>";
    }
}
