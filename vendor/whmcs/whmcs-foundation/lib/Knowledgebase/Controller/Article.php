<?php

namespace WHMCS\Knowledgebase\Controller;

class Article
{
    public function view(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $id = $request->getAttribute("id");
        $knowledgebaseItem = \WHMCS\Knowledgebase\Article::with("tags")->find($id);
        if (!$knowledgebaseItem) {
            return new \Laminas\Diactoros\Response\RedirectResponse(routePath("knowledgebase-index"));
        }
        if ($knowledgebaseItem->private && !\Auth::user()) {
            \Auth::requireLogin(true);
        }
        $primaryCategory = $knowledgebaseItem->categories()->first();
        if ($knowledgebaseItem->isOrphaned() || $primaryCategory->isHidden()) {
            return new \Laminas\Diactoros\Response\RedirectResponse(routePath("knowledgebase-index"));
        }
        $query = $request->getQueryParams();
        $post = $request->getParsedBody();
        $userInput = new \Symfony\Component\HttpFoundation\ParameterBag(array_merge($query, $post));
        $useful = $userInput->get("useful", "");
        if ($useful == "vote") {
            return $this->vote($request);
        }
        $view = new \WHMCS\Knowledgebase\View\Article();
        $knowledgebaseItem->update(["views" => $knowledgebaseItem->views + 1]);
        $translatedKnowledgebaseItem = $knowledgebaseItem->bestTranslation();
        $oldestCategoryParentId = 0;
        $categoryAncestors = $primaryCategory->ancestors();
        foreach ($categoryAncestors as $ancestor) {
            $oldestCategoryParentId = $ancestor->id;
            $view->addToBreadCrumb(routePath("knowledgebase-category-view", $ancestor->id, $ancestor->bestTranslation()->name), $ancestor->bestTranslation()->name);
        }
        \Menu::addContext("kbCategoryParentId", (int) $oldestCategoryParentId);
        \Menu::addContext("kbCategoryId", $primaryCategory->id);
        $view->addToBreadCrumb(routePath("knowledgebase-category-view", $primaryCategory->id, $primaryCategory->bestTranslation()->name), $primaryCategory->bestTranslation()->name);
        $view->addToBreadCrumb(routePath("knowledgebase-article-view", $knowledgebaseItem->id, $translatedKnowledgebaseItem->title), $translatedKnowledgebaseItem->title);
        $editLink = "";
        if (0 < (int) \WHMCS\Session::get("adminid") && \WHMCS\User\Admin\Permission::currentAdminHasPermissionName("Manage Knowledgebase")) {
            $editLink = \App::getSystemURL() . \App::get_admin_folder_name() . "/" . "supportkb.php?action=edit&id=" . $id;
        }
        $tags = [];
        foreach ($knowledgebaseItem->tags->pluck("tag") as $tag) {
            $tags[] = \WHMCS\Input\Sanitize::makeSafeForOutput($tag);
        }
        $view->assign("kbarticle", ["id" => $knowledgebaseItem->id, "categoryid" => $primaryCategory->id, "categoryname" => $primaryCategory->bestTranslation()->name, "title" => $translatedKnowledgebaseItem->title, "urlfriendlytitle" => getModRewriteFriendlyString($translatedKnowledgebaseItem->title), "text" => $translatedKnowledgebaseItem->article, "views" => $knowledgebaseItem->views, "useful" => $knowledgebaseItem->useful, "votes" => $knowledgebaseItem->votes, "voted" => in_array($knowledgebaseItem->id, (array) \WHMCS\Session::get("knowledgebaseArticleVoted")), "tags" => implode(", ", $tags), "editLink" => $editLink]);
        $siblingArticles = [];
        $siblings = $knowledgebaseItem->siblings;
        foreach ($siblings as $item) {
            $siblingArticles[] = $view->getArticleTemplateData($item);
        }
        $view->assign("kbarticles", $siblingArticles);
        return $view;
    }

    public function vote(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $query = $request->getQueryParams();
        $post = $request->getParsedBody();
        $userInput = new \Symfony\Component\HttpFoundation\ParameterBag(array_merge($query, $post));
        $id = $request->getAttribute("id");
        $article = \WHMCS\Knowledgebase\Article::find($id);
        if ($article) {
            $vote = $userInput->get("vote", "");
            $updateValues["votes"] = $article->votes + 1;
            if ($vote == "yes") {
                $updateValues["useful"] = $article->useful + 1;
            }
            $article->update($updateValues);
            $votedArticles = (array) \WHMCS\Session::get("knowledgebaseArticleVoted");
            $votedArticles[] = (int) $article->id;
            \WHMCS\Session::set("knowledgebaseArticleVoted", $votedArticles);
        }
        return new \Laminas\Diactoros\Response\RedirectResponse(routePath("knowledgebase-article-view", $article->id, $article->bestTranslation()->title));
    }
}
