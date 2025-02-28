<?php

namespace WHMCS\Download\Controller;

class DownloadController
{
    private function getTopDownloads()
    {
        if (!$topDownloads) {
            $topDownloads = \WHMCS\Download\Download::topDownloads()->get();
        }
        return $topDownloads;
    }

    private function toIndex()
    {
        return new \Laminas\Diactoros\Response\RedirectResponse(routePath("download-index"));
    }

    private function setPageContexts(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $catid = $request->getAttribute("catid", NULL);
        if (!is_null($catid) && !is_numeric($catid)) {
            return false;
        }
        \Menu::addContext("downloadCategory", \WHMCS\Download\Category::find($catid));
        \Menu::addContext("topFiveDownloads", $this->getTopDownloads());
        return true;
    }

    private function getSubcategoryIdsDeep($catId)
    {
        $nestingLevel++;
        if (100 < $nestingLevel) {
            return [];
        }
        $subCatIds = \WHMCS\Download\Category::ofParent($catId)->visible()->pluck("id")->toArray();
        $result = $subCatIds;
        foreach ($subCatIds as $subCatId) {
            $result = array_merge($result, $this->getSubcategoryIdsDeep($subCatId));
        }
        $nestingLevel--;
        return $result;
    }

    private function getCategoriesByParent($parentCatId = 0)
    {
        $result = [];
        $i = 1;
        $cats = \WHMCS\Download\Category::ofParent($parentCatId)->visible()->orderBy("name", "asc")->get();
        foreach ($cats as $cat) {
            $downloadStatCatIds = $this->getSubcategoryIdsDeep($cat->id);
            $downloadStatCatIds[] = $cat->id;
            $containedDownloadCount = \WHMCS\Download\Download::considerProductDownloads()->visible()->whereIn("category", $downloadStatCatIds)->count();
            $result[$i++] = ["id" => $cat->id, "name" => $cat->name, "urlfriendlyname" => getModRewriteFriendlyString($cat->name), "description" => $cat->description, "numarticles" => $containedDownloadCount];
        }
        return $result;
    }

    public function index(\Psr\Http\Message\ServerRequestInterface $request)
    {
        if (!$this->setPageContexts($request)) {
            return $this->toIndex();
        }
        $view = new \WHMCS\Download\View\Index();
        $view->assign("mostdownloads", $view->formatDownloadsForTemplate($this->getTopDownloads()));
        $view->assign("dlcats", $this->getCategoriesByParent());
        return $view;
    }

    public function viewCategory(\Psr\Http\Message\ServerRequestInterface $request)
    {
        if (!$this->setPageContexts($request)) {
            return $this->toIndex();
        }
        $view = new \WHMCS\Download\View\Index();
        $view->assign("search", NULL);
        $view->assign("dlcats", NULL);
        $catId = $request->getAttribute("catid", 0);
        $view->setTemplate("downloadscat");
        $bcCatId = $catId;
        $maxLevels = 100;
        $breadcrumbs = [];
        while ($bcCatId && $maxLevels--) {
            $bcCat = \WHMCS\Download\Category::find($bcCatId);
            if ($bcCat) {
                array_unshift($breadcrumbs, [$bcCat->id, $bcCat->name]);
                $bcCatId = $bcCat->parentId;
            } else {
                return $this->toIndex();
            }
        }
        foreach ($breadcrumbs as $breadcrumb) {
            list($catId, $catTitle) = $breadcrumb;
            $view->addToBreadCrumb(routePath("download-by-cat", $catId, $catTitle), $catTitle);
        }
        $downloads = \WHMCS\Download\Download::considerProductDownloads()->visible()->inCategory($catId)->orderBy("title", "asc")->get();
        $view->assign("downloads", $view->formatDownloadsForTemplate($downloads));
        $view->assign("dlcats", $this->getCategoriesByParent($catId));
        return $view;
    }

    public function search(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $query = $request->getQueryParams();
        $attributes = $request->getAttributes();
        $post = $request->getParsedBody();
        $userInput = new \Symfony\Component\HttpFoundation\ParameterBag(array_merge($query, $attributes, $post));
        $search = $userInput->get("search");
        if (!trim($search)) {
            return $this->toIndex();
        }
        $view = new \WHMCS\Download\View\Index();
        $view->setTemplate("downloadscat");
        $view->assign("search", $search);
        $view->assign("dlcats", NULL);
        $downloads = \WHMCS\Download\Download::considerProductDownloads()->visible()->categoryVisible()->search($search)->orderBy("title", "asc")->get();
        $view->assign("downloads", $view->formatDownloadsForTemplate($downloads));
        return $view;
    }
}
