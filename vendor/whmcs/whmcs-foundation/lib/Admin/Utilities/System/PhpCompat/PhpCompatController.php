<?php

namespace WHMCS\Admin\Utilities\System\PhpCompat;

class PhpCompatController
{
    public function index()
    {
        $view = new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper();
        $view->setTitle(\AdminLang::trans("phpCompatUtil.title"))->setSidebarName("utilities")->setHelpLink("PHP Version Compatibility Assessment")->setFavicon("phpinfo");
        $iterator = \WHMCS\Environment\Ioncube\Inspector\Iterator\Loggable::fromDatabase();
        $scan = $iterator->getMetaData();
        $needsScan = false;
        if (!$scan->hasScanned()) {
            $needsScan = true;
        } else {
            if ($scan->needsScan($this->getApplicationVersion(), $this->getPhpVersion(), $this->getLoaderVersion())) {
                $needsScan = true;
                $iterator->purgeAll();
                $scan = $iterator->resetMetadata();
            }
        }
        $templateData = ["assessments" => $this->getVersionDataWithAccordionCompat($iterator), "needsInitialScan" => $needsScan, "lastScanned" => $scan->getDateTime(), "localLoaderVersion" => \WHMCS\Environment\Ioncube\Loader\LocalLoader::getVersion()];
        $body = view("admin.utilities.system.php-compat.index", $templateData);
        $view->setBodyContent($body);
        return $view;
    }

    public function scan()
    {
        $config = \DI::make("config");
        if (isset($config["overidephptimelimit"]) && is_numeric($config["overidephptimelimit"])) {
            $timeout = (int) $config["overidephptimelimit"];
        } else {
            $timeout = 120;
        }
        @ini_set("max_execution_time", $timeout);
        $files = \WHMCS\Environment\Ioncube\Inspector\Iterator\Directory::fromDirectory(ROOTDIR);
        $iterator = \WHMCS\Environment\Ioncube\Inspector\Iterator\Loggable::fromDatabase();
        $iterator->merge($files);
        $iterator->save();
        $scan = $iterator->getMetaData()->update($this->getApplicationVersion(), $this->getPhpVersion(), $this->getLoaderVersion());
        $iterator->saveMetaData($scan);
        $templateData = ["assessments" => $this->getVersionDataWithAccordionCompat($iterator)];
        $body = view("admin.utilities.system.php-compat.assessment.all-versions-details", $templateData);
        return new \WHMCS\Http\Message\JsonResponse(["allVersionsHtml" => $body, "lastScanned" => $scan->getDateTime()->toAdminDateTimeFormat()]);
    }

    protected function getVersionDataWithAccordionCompat(\WHMCS\Environment\Ioncube\Contracts\InspectorIteratorInterface $inspector)
    {
        $data = [];
        $loader = new \WHMCS\Environment\Ioncube\Loader\Loader100100();
        $thisProductSupportedPhpVersions = ["0702" => "7.2", "0703" => "7.3", "0704" => "7.4", "0801" => "8.1"];
        $allWhmcsSupportedPhpVersions = array_merge(["0506" => "5.6", "0700" => "7.0", "0701" => "7.1"], $thisProductSupportedPhpVersions);
        foreach ($allWhmcsSupportedPhpVersions as $versionId => $version) {
            $filesThatShouldBeLookedAt = new \WHMCS\Environment\Ioncube\Inspector\Filter\AnyEncodingIterator($version, $inspector);
            $data[$version] = new View\AccordionByCompat\VersionDetails($version, $versionId, $filesThatShouldBeLookedAt, $loader, isset($thisProductSupportedPhpVersions[$versionId]));
            if (version_compare($version, "8.1", ">=")) {
                $data[$version]->setRequiredMinimumLoaderVersion(new \WHMCS\Version\SemanticVersion("12.0.1"));
            }
        }
        return $data;
    }

    protected function getApplicationVersion()
    {
        return new \WHMCS\Version\SemanticVersion(\WHMCS\Application::FILES_VERSION);
    }

    protected function getLoaderVersion()
    {
        return \WHMCS\Environment\Ioncube\Loader\LocalLoader::getVersion();
    }

    protected function getPhpVersion()
    {
        return PHP_VERSION_ID;
    }
}
