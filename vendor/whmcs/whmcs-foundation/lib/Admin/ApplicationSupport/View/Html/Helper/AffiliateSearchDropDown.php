<?php

namespace WHMCS\Admin\ApplicationSupport\View\Html\Helper;

class AffiliateSearchDropDown extends ClientSearchDropdown
{
    public function getFormattedHtmlHeadContent()
    {
        return "<script>function getClientSearchPostUrl() { return '" . routePath("admin-search-affiliate") . "'; }</script>" . PHP_EOL . "<script type=\"text/javascript\" " . "src=\"../assets/js/AdminClientDropdown.js\"></script>";
    }
}
