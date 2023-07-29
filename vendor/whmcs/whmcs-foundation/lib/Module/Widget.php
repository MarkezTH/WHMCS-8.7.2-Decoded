<?php

namespace WHMCS\Module;

class Widget extends AbstractModule
{
    protected $type = self::TYPE_WIDGET;
    protected $usesDirectories = false;
    protected $widgets = NULL;
    protected $hookName = "AdminHomeWidgets";

    public function loadWidgets()
    {
        $this->widgets = [];
        foreach ($this->getList() as $widgetName) {
            if ($widgetName != "index") {
                try {
                    $widgetClass = "\\WHMCS\\Module\\Widget\\" . $widgetName;
                    if (class_exists($widgetClass)) {
                        $widget = new $widgetClass();
                        if (!$widget->getRequiredPermission() || checkPermission($widget->getRequiredPermission(), true)) {
                            $widget = new $widgetClass();
                            $this->widgets[] = $widget;
                        }
                    }
                } catch (\WHMCS\Exception\Module\NotServicable $e) {
                } catch (\Exception $e) {
                    logActivity("An Error Occurred loading widget " . $widgetName . ": " . $e->getMessage());
                }
            }
        }
        $this->loadWidgetsViaHooks();
        usort($this->widgets, function ($a, $b) {
            return $b->getWeight() < $a->getWeight();
        });
        return $this->widgets;
    }

    protected function initGlobalChartForLegacyWidgets()
    {
        global $chart;
        if (!$chart instanceof \WHMCS\Chart) {
            $chart = new \WHMCS\Chart();
        }
    }

    protected function loadWidgetsViaHooks()
    {
        $hookMgr = \DI::make("HookManager");
        $hooks = $hookMgr->getRegistered($this->hookName);
        if (count($hooks) == 0) {
            return NULL;
        }
        $allowedwidgets = get_query_val("tbladmins", "tbladminroles.widgets", ["tbladmins.id" => \WHMCS\Session::get("adminid")], "", "", "", "tbladminroles ON tbladminroles.id=tbladmins.roleid");
        $allowedwidgets = explode(",", $allowedwidgets);
        $hookjquerycode = "";
        $args = ["adminid" => \WHMCS\Session::get("adminid"), "loading" => "<img src=\"images/loading.gif\" align=\"absmiddle\" /> " . \AdminLang::trans("global.loading")];
        $results = [];
        foreach ($hooks as $hook) {
            $widgetname = is_string($hook["hookFunction"]) ? substr($hook["hookFunction"], 7) : NULL;
            if (is_callable($hook["hookFunction"]) && (!$widgetname || in_array($widgetname, $allowedwidgets))) {
                try {
                    $this->initGlobalChartForLegacyWidgets();
                    $response = call_user_func($hook["hookFunction"], $args);
                    $widget = NULL;
                    if ($response instanceof AbstractWidget) {
                        $widget = $response;
                    } else {
                        if (is_array($response)) {
                            $widget = LegacyWidget::factory($response["title"], $response["content"], $response["jscode"], $response["jquerycode"]);
                        }
                    }
                    if ($widget && (!$widget->getRequiredPermission() || checkPermission($widget->getRequiredPermission(), true))) {
                        $this->widgets[] = $widget;
                    }
                } catch (\Exception $e) {
                    logActivity("An Error Occurred loading widget " . $widgetname . ": " . $e->getMessage());
                } catch (\Error $e) {
                    logActivity("An Error Occurred loading widget " . $widgetname . ": " . $e->getMessage());
                }
            }
        }
    }

    public function getAllWidgets()
    {
        if (is_null($this->widgets)) {
            $this->loadWidgets();
        }
        return $this->widgets;
    }

    public function getWidgetByName($widgetId)
    {
        if (is_null($this->widgets)) {
            $this->loadWidgets();
        }
        foreach ($this->widgets as $widget) {
            if ($widget->getId() == $widgetId) {
                return $widget;
            }
        }
        throw new \WHMCS\Exception("Invalid widget name.");
    }
}
