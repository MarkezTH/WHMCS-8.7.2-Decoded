<?php

namespace WHMCS\Admin\Apps;

class AppsController
{
    public function index(\WHMCS\Http\Message\ServerRequest $request, $postLoadAction = NULL, $postLoadParams = [])
    {
        $aInt = new \WHMCS\Admin("Apps and Integrations");
        $aInt->setResponseType(\WHMCS\Admin::RESPONSE_HTML_MESSAGE);
        $aInt->title = \AdminLang::trans("apps.title");
        $aInt->sidebar = "";
        $aInt->icon = "apps";
        $aInt->isSetupPage = true;
        try {
            $aInt->content = view("admin.apps.index", ["assetHelper" => \DI::make("asset"), "heros" => (new \WHMCS\Apps\Hero\Collection())->get(), "postLoadAction" => $postLoadAction, "postLoadParams" => $postLoadParams]);
        } catch (\WHMCS\Exception\Http\ConnectionError $e) {
            $aInt->content = view("admin.apps.index", ["assetHelper" => \DI::make("asset"), "connectionError" => true]);
        } catch (\WHMCS\Exception $e) {
            $aInt->content = view("admin.apps.index", ["assetHelper" => \DI::make("asset"), "renderError" => $e->getMessage()]);
        }
        return $aInt->display();
    }

    public function jumpBrowse(\WHMCS\Http\Message\ServerRequest $request)
    {
        return $this->index($request, "browse", ["category" => $request->get("category")]);
    }

    public function jumpActive(\WHMCS\Http\Message\ServerRequest $request)
    {
        return $this->index($request, "active");
    }

    public function jumpSearch(\WHMCS\Http\Message\ServerRequest $request)
    {
        return $this->index($request, "search");
    }

    public function featured(\WHMCS\Http\Message\ServerRequest $request)
    {
        return new \WHMCS\Http\Message\JsonResponse(["content" => view("admin.apps.featured", ["apps" => new \WHMCS\Apps\App\Collection(), "categories" => new \WHMCS\Apps\Category\Collection()])]);
    }

    public function active(\WHMCS\Http\Message\ServerRequest $request)
    {
        return new \WHMCS\Http\Message\JsonResponse(["content" => view("admin.apps.active", ["apps" => new \WHMCS\Apps\App\Collection()])]);
    }

    public function search(\WHMCS\Http\Message\ServerRequest $request)
    {
        return new \WHMCS\Http\Message\JsonResponse(["content" => view("admin.apps.response.search", ["apps" => new \WHMCS\Apps\App\Collection()])]);
    }

    public function category(\WHMCS\Http\Message\ServerRequest $request)
    {
        $slug = $request->get("category");
        $apps = new \WHMCS\Apps\App\Collection();
        $categories = new \WHMCS\Apps\Category\Collection();
        $category = $categories->getCategoryBySlug($slug);
        if (is_null($category)) {
            $category = $categories->first();
        }
        return new \WHMCS\Http\Message\JsonResponse(["displayname" => $category->getDisplayName(), "content" => view("admin.apps.category", ["apps" => $apps, "category" => $category, "hero" => $category->getHero($apps), "categories" => $categories])]);
    }

    public function infoModal(\WHMCS\Http\Message\ServerRequest $request)
    {
        $moduleSlug = $request->get("moduleSlug");
        $apps = new \WHMCS\Apps\App\Collection();
        if (!$apps->exists($moduleSlug)) {
            return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.apps.modal.error", ["errorMsg" => "Module not found. Please try again."])]);
        }
        return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.apps.modal.info", ["app" => $apps->get($moduleSlug)])]);
    }

    public function logo(\WHMCS\Http\Message\ServerRequest $request)
    {
        $moduleSlug = $request->get("moduleSlug");
        $app = (new \WHMCS\Apps\App\Collection())->get($moduleSlug);
        header("Content-type:image/png");
        return $app->getLogoContent();
    }
}
