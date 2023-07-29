<?php

namespace WHMCS;

class LegacyPage
{
    private static $emitterClass = "Laminas\\HttpHandlerRunner\\Emitter\\SapiEmitter";
    const TYPE_DOWNLOAD = "download";
    const TYPE_ANNOUNCEMENTS = "announcements";
    const TYPE_KNOWLEDGEBASE = "knowledgebase";
    const TYPE_TICKET_IMPORT_LOG = "ticketimportlog";

    private function send($response)
    {
        $emitter = new self::$emitterClass();
        $emitter->emit($response);
    }

    private static function generateRedirectResponseAndEmit($redirectPath, int $statusCode = 301)
    {
        $legacyPage = new self();
        $redirectPath = $legacyPage->appendLanguageParameterIfNecessary($redirectPath);
        $legacyPage->send(new Http\RedirectResponse($redirectPath, $statusCode));
    }

    private function appendLanguageParameterIfNecessary($url)
    {
        if (\App::isInRequest("language")) {
            $language = \App::getFromRequest("language");
            $url .= strpos($url, "?") !== false ? "&" : "?";
            $url .= "language=" . $language;
        }
        return $url;
    }

    public static function redirectKnowledgebase()
    {
        $redirectPath = self::generateRoutePath(self::TYPE_KNOWLEDGEBASE, ["action" => \App::getFromRequest("action"), "catId" => \App::getFromRequest("catid"), "articleId" => \App::getFromRequest("id")]);
        self::generateRedirectResponseAndEmit($redirectPath);
    }

    public static function redirectAnnouncements()
    {
        $redirectPath = self::generateRoutePath(self::TYPE_ANNOUNCEMENTS, ["id" => \App::getFromRequest("id")]);
        self::generateRedirectResponseAndEmit($redirectPath);
    }

    public static function redirectDownloads()
    {
        $redirectPath = self::generateRoutePath(self::TYPE_DOWNLOAD, ["action" => \App::getFromRequest("action"), "catId" => \App::getFromRequest("catid")]);
        self::generateRedirectResponseAndEmit($redirectPath);
    }

    public static function redirectTicketImportLog()
    {
        $redirectPath = self::generateRoutePath(self::TYPE_TICKET_IMPORT_LOG, ["display" => \App::getFromRequest("display"), "id" => \App::getFromRequest("id")]);
        self::generateRedirectResponseAndEmit($redirectPath);
    }

    private static function generateRoutePath($type, $params = [], $fqdn = false)
    {
        $function = $fqdn ? "fqdnRoutePath" : "routePath";
        switch ($type) {
            case self::TYPE_KNOWLEDGEBASE:
                if (array_key_exists("action", $params) && $params["action"]) {
                    try {
                        if ($params["action"] === "displaycat" && array_key_exists("catId", $params)) {
                            $category = Knowledgebase\Category::findOrFail($params["catId"]);
                            return $function("knowledgebase-category-view", $category->id, getModRewriteFriendlyString($category->name));
                        }
                        if ($params["action"] === "displayarticle" && array_key_exists("articleId", $params)) {
                            $article = Knowledgebase\Article::findOrFail($params["articleId"]);
                            return $function("knowledgebase-article-view", $article->id, getModRewriteFriendlyString($article->title));
                        }
                    } catch (\Throwable $e) {
                    }
                }
                return $function("knowledgebase-index");
                break;
            case self::TYPE_ANNOUNCEMENTS:
                if (array_key_exists("id", $params) && $params["id"]) {
                    try {
                        $announcement = Announcement\Announcement::findOrFail($params["id"]);
                        return $function("announcement-view", $announcement->id, getModRewriteFriendlyString($announcement->title));
                    } catch (\Throwable $e) {
                    }
                }
                return $function("announcement-index");
                break;
            case self::TYPE_DOWNLOAD:
                if (array_key_exists("action", $params) && array_key_exists("catId", $params) && $params["action"] === "displaycat" && !empty($params["catId"])) {
                    try {
                        $category = Download\Category::findOrFail($params["catId"]);
                        return $function("download-by-cat", $category->id, getModRewriteFriendlyString($category->name));
                    } catch (\Throwable $e) {
                    }
                }
                return $function("download-index");
                break;
            case self::TYPE_TICKET_IMPORT_LOG:
                return $function("admin-logs-mail-import-log");
                break;
            default:
                throw new Exception("Invalid Type Provided");
        }
    }
}
