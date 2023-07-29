<?php

namespace WHMCS\Announcement\Controller;

class AnnouncementController
{
    public function __construct()
    {
        if (!function_exists("ticketsummary")) {
            require ROOTDIR . "/includes/ticketfunctions.php";
        }
    }

    private function setPageContexts(\Psr\Http\Message\ServerRequestInterface $request)
    {
        \Menu::addContext("announcementView", $request->getAttribute("view"));
    }

    public function index(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $this->setPageContexts($request);
        $view = new \WHMCS\Announcement\View\Index();
        $pageLimit = 4;
        $page = $request->getAttribute("page", 1);
        $query = \WHMCS\Announcement\Announcement::published();
        $userView = $request->getAttribute("view");
        if ($userView) {
            if ($userView === "older") {
                $monthsDisplayedInSidebar = \Menu::context("monthsWithAnnouncements");
                foreach ($monthsDisplayedInSidebar as $month) {
                    $query = $query->where("date", "NOT LIKE", $month->format("Y-m") . "%");
                }
            } else {
                $query = $query->where("date", "like", "%" . $userView . "%");
            }
        }
        $view->assign("view", $userView);
        $numannouncements = $query->count();
        $query = $query->orderBy("date", "DESC")->skip((int) (($page - 1) * $pageLimit))->limit((int) $pageLimit);
        $results = $query->get();
        $announcements = [];
        foreach ($results as $announcement) {
            $announcements[] = $view->getAnnouncementTemplateData($announcement);
        }
        $view->assign("announcements", $announcements);
        $totalpages = ceil($numannouncements / $pageLimit);
        $prevpage = $nextpage = "";
        if ($page != 1) {
            $prevpage = $page - 1;
        }
        if ($page != $totalpages && $numannouncements) {
            $nextpage = $page + 1;
        }
        if (!$totalpages) {
            $totalpages = 1;
        }
        $view->assign("numannouncements", $numannouncements);
        $view->assign("pagenumber", $page);
        $view->assign("totalpages", $totalpages);
        $view->assign("prevpage", $prevpage);
        $view->assign("nextpage", $nextpage);
        $view->assign("pagination", $this->paginate($totalpages, $page, $userView));
        switch ($userView) {
            case "older":
                $breadcrumb = \Lang::trans("announcementsolder");
                break;
            default:
                try {
                    $monthYear = \WHMCS\Carbon::parse($userView);
                    if (!$monthYear->isValid()) {
                        throw new \WHMCS\Exception("Invalid Date");
                    }
                    $breadcrumb = $monthYear->format("M Y");
                } catch (\Throwable $e) {
                    $breadcrumb = "";
                }
                if ($breadcrumb) {
                    $view->addToBreadCrumb(routePath("announcement-index", $userView), $breadcrumb);
                }
                return $view;
        }
    }

    public function twitterFeed(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $view = new \WHMCS\Announcement\View\TwitterFeed();
        $tweets = [];
        $view->assign("tweets", $tweets);
        $view->assign("numtweets", 0);
        return $view;
    }

    public function view(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $this->setPageContexts($request);
        $announcement = \WHMCS\Announcement\Announcement::published()->find($request->getAttribute("id"));
        if (!$announcement) {
            return new \Laminas\Diactoros\Response\RedirectResponse(routePath("announcement-index"));
        }
        $translatedAnnouncement = $announcement->bestTranslation();
        $view = new \WHMCS\Announcement\View\Item();
        $view->setDisplayTitle($translatedAnnouncement->title);
        $view->setTagLine("");
        $view->setTemplateVariables($view->getAnnouncementTemplateData($translatedAnnouncement));
        $view->addToBreadCrumb(routePath("announcement-view", $translatedAnnouncement->id), $translatedAnnouncement->title);
        return $view;
    }

    private function paginate(int $totalPages, int $page, $view)
    {
        $elements = [];
        $prevPage = $nextPage = "";
        $previousDisable = $nextDisable = true;
        if ($page != 1) {
            $previousDisable = false;
            $prevPage = $page - 1;
        }
        if ($page != $totalPages) {
            $nextDisable = false;
            $nextPage = $page + 1;
        }
        $routes = ["previousPageRoute" => $prevPage ? routePath("announcement-index-paged", $prevPage, $view) : "#", "nextPageRoute" => $nextPage ? routePath("announcement-index-paged", $nextPage, $view) : "#", "currentPageRoute" => routePath("announcement-index-paged", $page, $view)];
        $elements[] = ["disabled" => $page === 1, "active" => false, "link" => routePath("announcement-index-paged", 1, $view), "text" => "<i class=\"fas fa-chevron-double-left\"></i>"];
        $elements[] = ["disabled" => $previousDisable, "active" => false, "link" => $routes["previousPageRoute"], "text" => "<i class=\"fas fa-chevron-left\"></i>"];
        if ($totalPages === 1) {
            $elements[] = ["disabled" => false, "active" => true, "link" => routePath("announcement-index-paged", $page, $view), "text" => $page];
        } else {
            $index = $page - 2;
            while ($index <= $page + 2 && $index <= $totalPages) {
                if ($index < 1) {
                    $index++;
                } else {
                    $elements[] = ["disabled" => false, "active" => $index === $page, "link" => routePath("announcement-index-paged", $index, $view), "text" => $index];
                    $index++;
                }
            }
        }
        $elements[] = ["disabled" => $nextDisable, "active" => false, "link" => $routes["nextPageRoute"], "text" => "<i class=\"fas fa-chevron-right\"></i>"];
        $elements[] = ["disabled" => $page === $totalPages, "active" => false, "link" => routePath("announcement-index-paged", $totalPages, $view), "text" => "<i class=\"fas fa-chevron-double-right\"></i>"];
        return $elements;
    }
}
