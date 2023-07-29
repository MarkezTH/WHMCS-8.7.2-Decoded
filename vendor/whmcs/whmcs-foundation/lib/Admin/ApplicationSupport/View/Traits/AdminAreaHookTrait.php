<?php

namespace WHMCS\Admin\ApplicationSupport\View\Traits;

trait AdminAreaHookTrait
{
    public function runHookAdminFooterOutput($hookVariables)
    {
        $hookResult = run_hook("AdminAreaFooterOutput", $hookVariables);
        $hookResult[] = view("admin.utilities.date.footer");
        return count($hookResult) ? implode("\n", $hookResult) : "";
    }

    public function runHookAdminHeaderOutput($hookVariables)
    {
        $hookResult = run_hook("AdminAreaHeaderOutput", $hookVariables);
        return count($hookResult) ? implode("\n", $hookResult) : "";
    }

    public function runHookAdminHeadOutput($hookVariables)
    {
        $hookResult = run_hook("AdminAreaHeadOutput", $hookVariables);
        return count($hookResult) ? implode("\n", $hookResult) : "";
    }

    public function runHookAdminAreaPage($hookVariables)
    {
        $hookResult = run_hook("AdminAreaPage", $hookVariables);
        foreach ($hookResult as $arr) {
            foreach ($arr as $k => $v) {
                $hookVariables[$k] = $v;
            }
        }
        return $hookVariables;
    }
}
