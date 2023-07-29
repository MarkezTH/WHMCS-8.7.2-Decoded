<?php

namespace App\Games\Kernel;

class _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F566965772F41646D696E2F4865616C7468436865636B2F4865616C7468436865636B5265706F7369746F72792E7068703078376630316332633531356232_
{
    protected $present = NULL;
    protected $absent = NULL;
    protected $writable = NULL;
    protected $notWritable = NULL;

    public function existence($present, $absent)
    {
        $this->present = $present;
        $this->absent = $absent;
    }

    public function write($writable, $notWritable)
    {
        $this->writable = $writable;
        $this->notWritable = $notWritable;
    }

    public function isGreen()
    {
        return empty($this->absent) && empty($this->notWritable);
    }

    public function render()
    {
        $helper = new WHMCS\View\Admin\HealthCheck\RenderHelper();
        if ($this->isGreen()) {
            return AdminLang::trans("healthCheck.customPathsChecksClean");
        }
        $out = "";
        if (!empty($this->absent)) {
            $out .= $helper->section(AdminLang::trans("healthCheck.customPathsTitleMismatch"));
            $out .= $helper->unordered($this->absent, function ($dir) {
                return $dir->invokeMissing();
            });
        }
        if (!empty($this->notWritable)) {
            $out .= $helper->section(AdminLang::trans("healthCheck.customPathsTitleNoWrite"));
            $out .= $helper->unordered($this->notWritable, function ($dir) {
                return $dir->invokeNotWritable();
            });
        }
        return $out;
    }
}
namespace WHMCS\View\Admin\HealthCheck;

class HealthCheckRepository
{
    protected $osChecker = NULL;
    protected $whmcsChecker = NULL;
    protected $curlChecker = NULL;
    protected $httpChecker = NULL;
    const RECOMMENDED_DB_COLLATIONS = "utf8mb4_unicode_ci";
    const MINIMUM_MEMORY_LIMIT = 67108864;
    const RECOMMENDED_MEMORY_LIMIT = 134217728;
    const DEFAULT_MEMORY_LIMIT_FOR_AUTO_UPDATE = self::MINIMUM_MEMORY_LIMIT;

    public function __construct(\WHMCS\Environment\OperatingSystem $osChecker = NULL, \WHMCS\Environment\WHMCS $whmcsChecker = NULL, \WHMCS\Environment\Http $httpChecker = NULL)
    {
        $this->osChecker = is_null($osChecker) ? new \WHMCS\Environment\OperatingSystem() : $osChecker;
        $this->whmcsChecker = is_null($whmcsChecker) ? new \WHMCS\Environment\WHMCS() : $whmcsChecker;
        $this->httpChecker = is_null($httpChecker) ? new \WHMCS\Environment\Http() : $httpChecker;
    }

    protected function buildCheckResults($results)
    {
        $healthChecks = new \Illuminate\Support\Collection();
        foreach ($results as $result) {
            if (!is_null($result)) {
                $healthChecks->put($result->getName(), $result);
            }
        }
        return $healthChecks->sort(function (HealthCheckResult $a, HealthCheckResult $b) {
            $logLevelOrders = [\Psr\Log\LogLevel::DEBUG => 0, \Psr\Log\LogLevel::INFO => 1, \Psr\Log\LogLevel::NOTICE => 2, \Psr\Log\LogLevel::WARNING => 3, \Psr\Log\LogLevel::ERROR => 4, \Psr\Log\LogLevel::CRITICAL => 5, \Psr\Log\LogLevel::ALERT => 6, \Psr\Log\LogLevel::EMERGENCY => 7];
            if ($a->getSeverityLevel() == $b->getSeverityLevel()) {
                return 0;
            }
            return $logLevelOrders[$a->getSeverityLevel()] < $logLevelOrders[$b->getSeverityLevel()] ? 1 : -1;
        });
    }

    public function keyChecks()
    {
        $healthChecks = [$this->checkForUpdateVersionAvailable(), $this->getQuickLinks()];
        return $this->buildCheckResults($healthChecks);
    }

    public function nonKeyChecks()
    {
        $healthChecks = [$this->checkForUnsupportedWebServer(), $this->whmcsChecker->cronPhpVersion() ? $this->checkBrowserPhpVsCronPhp() : NULL, $this->checkDirectoryCustomizations(), $this->checkForLaxFilePermissions(), $this->displayWhmcsPaths(), $this->checkForCustomPathUsage(), $this->checkForSensitiveDirectoryRemoteAccess(), $this->checkDefaultTemplateUsage(), $this->hasMatchCronTimeZone(), $this->hasCronRunToday(), $this->whmcsChecker->shouldPopCronRun() ? $this->hasPopCronRunInLastHour() : NULL, $this->checkPhpVersion(), $this->checkErrorDisplay(), $this->checkPhpErrorLevels(), $this->checkRequiredPhpExtensions(), $this->checkRecommendedPhpExtensions(), $this->checkRequiredPhpFunctions(), $this->checkPhpMemoryLimit(), $this->checkPhpSessionSupport(), $this->checkPhpTimezone(), $this->checkCurlVersion(), $this->checkForCurlSslSupport(), $this->checkForCurlSecureTlsSupport(), $this->checkForSiteSsl(), $this->checkDbVersion(), $this->checkUpdaterRequirements(), $this->whmcsChecker->isUsingSMTP() ? $this->checkSMTPMailEncryption() : NULL, $this->checkCronExecutionMemoryLimit(), $this->checkMysqlServerVariables(), $this->checkCloudLinuxMysqlnd(), $this->checkTicketMask(), $this->checkMissedAsyncJobs(), $this->checkModuleLogIsEnabled(), $this->checkLegacySmartyTags()];
        return $this->buildCheckResults($healthChecks);
    }

    protected function displayWhmcsPaths()
    {
        return new HealthCheckResult("customPaths", "WHMCS", \AdminLang::trans("healthCheck.currentPaths"), \Psr\Log\LogLevel::DEBUG, "<p>" . \AdminLang::trans("healthCheck.currentPathsSuccess") . "</p>" . "<ul>" . "<li>" . \AdminLang::trans("healthCheck.currentPathsAttachmentsDirectory", [":directory" => \App::getApplicationConfig()->attachments_dir]) . "</li>" . "<li>" . \AdminLang::trans("healthCheck.currentPathsDownloadsDirectory", [":directory" => \App::getApplicationConfig()->downloads_dir]) . "</li>" . "<li>" . \AdminLang::trans("healthCheck.currentPathsCompiledTemplatesDirectory", [":directory" => \App::getApplicationConfig()->templates_compiledir]) . "</li>" . "<li>" . \AdminLang::trans("healthCheck.currentPathsCronDirectory", [":directory" => \App::getApplicationConfig()->crons_dir]) . "</li>" . "<li>" . \AdminLang::trans("healthCheck.currentPathsAdminDirectory", [":directory" => ROOTDIR . DIRECTORY_SEPARATOR . \App::getApplicationConfig()->customadminpath]) . "</li>" . "</ul>");
    }

    protected function permissionDirectories()
    {
        $app = \DI::make("app");
        $directories = [$app->getTemplatesCacheDir(), $app->getCronDirectory()];
        foreach (\WHMCS\File\Provider\StorageProviderFactory::getLocalStoragePathsInUse() as $dir) {
            if (file_exists($dir)) {
                $directories[] = $dir;
            }
        }
        return $directories;
    }

    protected function customizableDirectories()
    {
        $config = \DI::make("config");
        $app = \DI::make("app");
        $checkConfigFileVariable = function ($variable) {
            return \AdminLang::trans("healthCheck.configFileVariableValue", [":var" => $variable]);
        };
        $directories = [];
        $isProjectAddonEnabled = \WHMCS\Module\Addon::isEnabled(\WHMCS\Module\Addon::MODULE_NAME_PROJECT_MANAGEMENT);
        $defaultAssetPaths = \WHMCS\File\Configuration\StorageConfiguration::getDefaultAssetStoragePaths();
        foreach (\WHMCS\File\Configuration\FileAssetSetting::query()->hasLocalConfiguration()->get() as $assetSetting) {
            if ($isProjectAddonEnabled || $assetSetting->asset_type != \WHMCS\File\FileAsset::TYPE_PM_FILES) {
                $defaultPath = $defaultAssetPaths[$assetSetting->asset_type] ?? "";
                $provider = $assetSetting->configuration->createStorageProvider();
                $directories[] = $this->newApplicationDirectory($provider->getLocalPath())->name(\WHMCS\File\FileAsset::getTypeName($assetSetting->asset_type))->default($defaultPath);
            }
        }
        unset($defaultAssetPaths);
        unset($assetSetting);
        unset($defaultPath);
        unset($provider);
        $directories[] = $this->newApplicationDirectory($config->makeAbsoluteToRootIfNot($app->getTemplatesCacheDir()))->name("templates_c")->default($config->makeAbsoluteToRootIfNot(\WHMCS\Config\Application::DEFAULT_COMPILED_TEMPLATES_FOLDER))->whenMissing(function ($dirObj) use($checkConfigFileVariable) {
            return sprintf("<p>%s</p><p>%s</p>", \AdminLang::trans("healthCheck.configFileMissingDirectory", [":desc" => "template compile cache", ":path" => $dirObj->currentPath]), $checkConfigFileVariable("templates_compiledir"));
        });
        $directories[] = $this->newApplicationDirectory($config->makeAbsoluteToRootIfNot($app->getCronDirectory()))->name("crons")->default($config->makeAbsoluteToRootIfNot(\WHMCS\Config\Application::DEFAULT_CRON_FOLDER))->whenMissing(function ($dirObj) use($checkConfigFileVariable) {
            return sprintf("<p>%s</p><p>%s</p>", \AdminLang::trans("healthCheck.configFileMissingDirectory", [":desc" => "cron", ":path" => $dirObj->currentPath]), $checkConfigFileVariable("crons_dir"));
        })->ignoreConcerns(["not-writable"]);
        return $directories;
    }

    protected function newApplicationDirectory($ApplicationDirectory, $currentPath)
    {
        return new ApplicationDirectory($currentPath);
    }

    protected function checkForUpdateVersionAvailable()
    {
        $updater = new \WHMCS\Installer\Update\Updater();
        if (\WHMCS\Version\SemanticVersion::compare($updater->getLatestVersion(), \App::getVersion(), ">")) {
            $level = \Psr\Log\LogLevel::ERROR;
            $message = \AdminLang::trans("healthCheck.updateAvailable", [":version" => $updater->getLatestVersion()->getCasual()]) . "<br>" . \AdminLang::trans("healthCheck.updateAvailableHelp", [":href" => "href=\"https://docs.whmcs.com/Upgrading\""]);
        } else {
            $level = \Psr\Log\LogLevel::NOTICE;
            $message = \AdminLang::trans("healthCheck.updateNotAvailable");
        }
        return new HealthCheckResult("version", "WHMCS", \App::getVersion()->getCasual(), $level, $message);
    }

    protected function checkForCustomPathUsage()
    {
        $config = \DI::make("config");
        $nonCustomPaths = [];
        foreach ($this->customizableDirectories() as $dirObj) {
            if ($dirObj->isDefault()) {
                $nonCustomPaths[$dirObj->currentPath] = $config->stripRootPath($dirObj->currentPath);
            }
        }
        $logLevel = empty($nonCustomPaths) ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::WARNING;
        $body = empty($nonCustomPaths) ? \AdminLang::trans("healthCheck.usingDefaultPathsSuccess") : \AdminLang::trans("healthCheck.usingDefaultPathsFailure", [":nonCustomPaths" => "<li><strong>" . implode("</strong></li><li><strong>", $nonCustomPaths) . "</strong></li>"]);
        return new HealthCheckResult("checkCustomFields", "WHMCS", \AdminLang::trans("healthCheck.usingDefaultPaths"), $logLevel, $body);
    }

    protected function checkDirectoryCustomizations()
    {
        _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F766965772F61646D696E2F6865616C7468636865636B2F6865616C7468636865636B7265706F7369746F72792E7068703078376630316332633531356232_();
        $output = $easytoyou_error_decompile;
        $present = [];
        $absent = [];
        $writable = [];
        $notWritable = [];
        foreach ($this->customizableDirectories() as $selfAwareDirectory) {
            if (!$selfAwareDirectory->isConcern("exists")) {
            } else {
                if ($selfAwareDirectory->exists()) {
                    $present[] = $selfAwareDirectory;
                    if ($selfAwareDirectory->isConcern("not-writable")) {
                        if ($selfAwareDirectory->writable()) {
                            $writable[] = $selfAwareDirectory;
                        } else {
                            $notWritable[] = $selfAwareDirectory;
                        }
                    }
                } else {
                    $absent[] = $selfAwareDirectory;
                }
            }
        }
        $output->existence($present, $absent);
        $output->write($writable, $notWritable);
        return new HealthCheckResult("directoryConfigurationsCheck", "WHMCS", \AdminLang::trans("healthCheck.customPathsHeadingConfiguration"), $output->isGreen() ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::ERROR, $output->render());
    }

    protected function checkForLaxFilePermissions()
    {
        $directoriesNotOwnedByUs = [];
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $bodyHtml = "";
        foreach ($this->permissionDirectories() as $directory) {
            if (!$this->osChecker->isOwnedByMe($directory)) {
                $logLevel = \Psr\Log\LogLevel::WARNING;
                $directoriesNotOwnedByUs[] = $directory;
            }
        }
        if ($this->whmcsChecker->isConfigurationWritable()) {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $bodyHtml .= "<p>" . \AdminLang::trans("healthCheck.permissionCheckConfigFileWritable") . "</p>";
        }
        if ($logLevel == \Psr\Log\LogLevel::NOTICE) {
            return new HealthCheckResult("permissionCheck", "WHMCS", \AdminLang::trans("healthCheck.permissionCheck"), $logLevel, \AdminLang::trans("healthCheck.permissionCheckSuccess"));
        }
        if (!empty($directoriesNotOwnedByUs)) {
            $bodyHtml .= "<style> .trimmed-dir-list li { text-decoration: underline dashed; } </style>";
            $bodyHtml .= "<p>" . \AdminLang::trans("healthCheck.permissionCheckUnownedDirectories") . "</p>" . "<ul class=\"trimmed-dir-list\">" . implode(array_map(function ($dir) {
                $displayDir = $dir;
                if (strpos($displayDir, ROOTDIR) === 0) {
                    $displayDir = ltrim(substr($displayDir, strlen(ROOTDIR)), DIRECTORY_SEPARATOR);
                }
                return "<li title=\"" . $dir . "\">" . $displayDir . "</li>";
            }, $directoriesNotOwnedByUs)) . "</ul>";
        }
        $bodyHtml .= \AdminLang::trans("healthCheck.permissionCheckUnownedDirectories2", [":href" => "href=\"https://docs.whmcs.com/Further_Security_Steps#Secure_the_Writeable_Directories\""]);
        return new HealthCheckResult("permissionCheck", "WHMCS", \AdminLang::trans("healthCheck.permissionCheck"), $logLevel, $bodyHtml);
    }

    protected function checkForSensitiveDirectoryRemoteAccess()
    {
        $pathsToCheck = [["check_url" => \App::getSystemURL(false) . "/vendor/composer/LICENSE", "directory" => "/vendor"]];
        $accessiblePaths = [];
        $downloadClient = new \WHMCS\Http\Client\HttpClient(["defaults" => ["timeout" => 5, "allow_redirects" => false]]);
        if (function_exists("curl_multi_exec")) {
            $promises = [];
            foreach ($pathsToCheck as $path) {
                $promises[] = $downloadClient->requestAsync("GET", $path["check_url"]);
            }
            $results = settle($promises)->wait();
            foreach ($results as $index => $promise) {
                $path = $pathsToCheck[$index];
                if ($promise["state"] === \GuzzleHttp\Promise\PromiseInterface::FULFILLED) {
                    $accessiblePaths[] = $path["directory"];
                }
            }
        } else {
            foreach ($pathsToCheck as $path) {
                try {
                    $responseCode = $downloadClient->get($path["check_url"])->getStatusCode();
                    if (200 <= $responseCode && $responseCode < 400) {
                        $accessiblePaths[] = $path["directory"];
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        if (empty($accessiblePaths)) {
            $logLevel = \Psr\Log\LogLevel::NOTICE;
            $body = \AdminLang::trans("healthCheck.sensitiveDirsNotAccessible");
        } else {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $body = \AdminLang::trans("healthCheck.sensitiveDirsAccessible", [":accessiblePaths" => implode("", array_map(function ($path) {
                return "<li>" . $path . "</li>";
            }, $accessiblePaths)), ":href" => "https://docs.whmcs.com/Further_Security_Steps#Vendor_Directory"]);
        }
        return new HealthCheckResult("sensitiveDirsCheck", "WHMCS", \AdminLang::trans("healthCheck.sensitiveDirsCheck"), $logLevel, $body);
    }

    protected function checkForUnsupportedWebServer()
    {
        if (\WHMCS\Environment\WebServer::supportsHtaccess()) {
            $logLevel = \Psr\Log\LogLevel::NOTICE;
            $bodyHtml = \AdminLang::trans("healthCheck.supportedWebserver", [":server" => \WHMCS\Environment\WebServer::getServerFamily()]);
        } else {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $bodyHtml = \AdminLang::trans("healthCheck.unsupportedWebserver", [":server" => \WHMCS\Environment\WebServer::getServerFamily(), ":envGuideline" => "https://docs.whmcs.com/System_Environment_Guide", ":learnMore" => "https://docs.whmcs.com/Further_Security_Steps#Enforce_htaccess_directives"]);
        }
        return new HealthCheckResult("webserverSupportCheck", "WHMCS", \AdminLang::trans("healthCheck.webserverSupportCheck"), $logLevel, $bodyHtml);
    }

    protected function getQuickLinks()
    {
        $updater = new \WHMCS\Installer\Update\Updater();
        $installedVersion = \App::getVersion();
        $latestVersion = $updater->getLatestVersion();
        $changeLogUrl = "https://docs.whmcs.com/Changelog:WHMCS_V%s.%s#Version_%s.%s.%s";
        $recentChangesUrl = "https://docs.whmcs.com/Version_%s.%s.%s_Release_Notes";
        $currentVersionChangeLogUrl = sprintf($changeLogUrl, $installedVersion->getMajor(), $installedVersion->getMinor(), $installedVersion->getMajor(), $installedVersion->getMinor(), $installedVersion->getPatch());
        $currentVersionChangeLogTitle = \AdminLang::trans("healthCheck.currentChangeLogLink", [":version" => $installedVersion->getCasual()]);
        $latestVersionChangeLogUrl = sprintf($changeLogUrl, $latestVersion->getMajor(), $latestVersion->getMinor(), $latestVersion->getMajor(), $latestVersion->getMinor(), $latestVersion->getPatch());
        $latestVersionChangeLogTitle = \AdminLang::trans("healthCheck.latestChangeLogLink", [":version" => $latestVersion->getCasual()]);
        $currentVersionReleaseNotesUrl = sprintf($recentChangesUrl, $installedVersion->getMajor(), $installedVersion->getMinor(), $installedVersion->getPatch());
        $currentVersionReleaseNotesTitle = \AdminLang::trans("healthCheck.currentReleaseNotesLink", [":version" => $installedVersion->getCasual()]);
        $latestVersionReleaseNotesUrl = sprintf($recentChangesUrl, $latestVersion->getMajor(), $latestVersion->getMinor(), $latestVersion->getPatch());
        $latestVersionReleaseNotesTitle = \AdminLang::trans("healthCheck.latestReleaseNotesLink", [":version" => $latestVersion->getCasual()]);
        $output = "<ul>" . "<li><a class='autoLinked' href='" . $currentVersionReleaseNotesUrl . "'>" . $currentVersionReleaseNotesTitle . "</a></li>" . "<li><a class='autoLinked' href='" . $currentVersionChangeLogUrl . "'>" . $currentVersionChangeLogTitle . "</a></li>";
        if (\WHMCS\Version\SemanticVersion::compare($latestVersion, $installedVersion, "!=")) {
            $output .= "</ul><h2>" . \AdminLang::trans("healthCheck.updatesAreAvailable") . "</h2>" . "<ul>" . "<li><a class='autoLinked' href='" . $latestVersionReleaseNotesUrl . "'>" . $latestVersionReleaseNotesTitle . "</a></li>" . "<li><a class='autoLinked' href='" . $latestVersionChangeLogUrl . "'>" . $latestVersionChangeLogTitle . "</a></li>";
        }
        $output .= "</ul>";
        return new HealthCheckResult("quickLinks", "WHMCS", \AdminLang::trans("healthCheck.quickLinks"), \Psr\Log\LogLevel::DEBUG, $output);
    }

    protected function hasCronRunToday()
    {
        if (\App::isNewInstallation()) {
            return NULL;
        }
        $cronCompletion = $this->whmcsChecker->hasCronCompletedInLastDay();
        return new HealthCheckResult("cron", "WHMCS", \AdminLang::trans("healthCheck.cronJobCompletion"), $cronCompletion ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::ERROR, "<p>" . ($cronCompletion ? \AdminLang::trans("healthCheck.cronJobCompletionSuccess") : \AdminLang::trans("healthCheck.cronJobCompletionFailure", [":href" => "href=\"https://docs.whmcs.com/Cron_Tasks\""])) . "</p>");
    }

    protected function hasMatchCronTimeZone()
    {
        $cronTimeZone = (new \WHMCS\Cron\Status())->getCronTimeZone();
        if (\App::isNewInstallation() || !$cronTimeZone) {
            return NULL;
        }
        $now = \WHMCS\Carbon::now();
        $uiUtcOffsetSeconds = $now->offset;
        $cronUtcOffsetSeconds = $now->setTimezone($cronTimeZone)->offset;
        $totalOffsetUtcHours = ($uiUtcOffsetSeconds - $cronUtcOffsetSeconds) / (\WHMCS\Carbon::SECONDS_PER_MINUTE * \WHMCS\Carbon::MINUTES_PER_HOUR);
        $diff = abs($totalOffsetUtcHours);
        return new HealthCheckResult("crontimezone", "PHP", \AdminLang::trans("healthCheck.cronTimeZone"), $diff ? \Psr\Log\LogLevel::WARNING : \Psr\Log\LogLevel::NOTICE, "<p>" . ($diff ? \AdminLang::trans("healthCheck.cronTimeZoneMisAligned", [":href" => "href=\"https://docs.whmcs.com/Cron_Tasks\"", ":crontimezone" => $cronTimeZone->getName(), ":diff" => $diff]) : \AdminLang::trans("healthCheck.cronTimeZoneAligned")) . "</p>");
    }

    protected function hasPopCronRunInLastHour()
    {
        $popCronCompletion = $this->whmcsChecker->hasPopCronRunInLastHour();
        return new HealthCheckResult("popCron", "WHMCS", \AdminLang::trans("healthCheck.popCronTicketImport"), $popCronCompletion ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::ERROR, "<p>" . ($popCronCompletion ? \AdminLang::trans("healthCheck.popCronTicketImportSuccess") : \AdminLang::trans("healthCheck.popCronTicketImportFailure", [":href" => "href=\"https://docs.whmcs.com/Email_Piping#Cron_Piping_Method_2\""])) . "</p>");
    }

    protected function checkDefaultTemplateUsage()
    {
        $nonCustomTemplates = [];
        if ($this->whmcsChecker->isUsingADefaultOrderFormTemplate(\WHMCS\Config\Setting::getValue("OrderFormTemplate"))) {
            $nonCustomTemplates[] = \AdminLang::trans("global.cart");
        }
        if ($this->whmcsChecker->isUsingADefaultSystemTemplate(\WHMCS\Config\Setting::getValue("Template"))) {
            $nonCustomTemplates[] = \AdminLang::trans("global.clientarea");
        }
        $logLevel = empty($nonCustomTemplates) ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::WARNING;
        $message = empty($nonCustomTemplates) ? "<p>" . \AdminLang::trans("healthCheck.customTemplatesSuccess") . "</p>" : "<p>" . \AdminLang::trans("healthCheck.customTemplatesFailure") . "</p>" . "<ul>" . "<li><strong>" . implode("</strong></li><li><strong>", $nonCustomTemplates) . "</strong></li>" . "</ul>" . "<p>" . \AdminLang::trans("healthCheck.customTemplatesFailure2", [":href" => "href=\"https://docs.whmcs.com/Client_Area_Template_Files#Creating_a_Custom_Template\""]) . "</p>";
        return new HealthCheckResult("usingCustomTemplates", "WHMCS", \AdminLang::trans("healthCheck.customTemplates"), $logLevel, $message);
    }

    protected function checkDbVersion()
    {
        $minRequiredVersion = "5.1";
        $minRecommendedMySqlVersion = "5.5.3";
        $minRecommendedMariaDbVersion = "5.5";
        $minRequiredMySqlV8Version = "8.0.12";
        $dbEngineName = strtolower(\DI::make("db")->getSqlVersionComment());
        $sqlVersion = strtolower(\DI::make("db")->getSqlVersion());
        $dbEngineVersion = preg_replace("/[^\\d\\.]*/", "", $sqlVersion);
        $isMariaDb = strpos($dbEngineName . $sqlVersion, "mariadb") !== false;
        $isMySql = !$isMariaDb;
        $dbEngineName = $isMariaDb ? "MariaDB" : "MySQL";
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $message = \AdminLang::trans("healthCheck.dbVersionIsUpToDate", [":dbname" => $dbEngineName, ":currentversion" => $dbEngineVersion]);
        $recommendedVersion = "";
        if (0 <= version_compare($dbEngineVersion, $minRequiredVersion)) {
            if ($isMariaDb) {
                if (version_compare($dbEngineVersion, $minRecommendedMariaDbVersion) < 0) {
                    $recommendedVersion = $minRecommendedMariaDbVersion;
                }
            } else {
                if (version_compare($dbEngineVersion, $minRecommendedMySqlVersion) < 0) {
                    $recommendedVersion = $minRecommendedMySqlVersion;
                }
            }
        } else {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $message = \AdminLang::trans("healthCheck.dbVersionUpgradeRequired", [":dbname" => $dbEngineName, ":currentversion" => $dbEngineVersion]);
        }
        if ($logLevel == \Psr\Log\LogLevel::NOTICE && $isMySql && 0 <= version_compare($dbEngineVersion, "8.0") && version_compare($dbEngineVersion, $minRequiredMySqlV8Version) < 0) {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $message = \AdminLang::trans("healthCheck.dbMinorVersionUpgradeRequired", [":dbname" => "MySQL", ":currentversion" => $dbEngineVersion, ":requiredVersion" => $minRequiredMySqlV8Version]);
        }
        if ($logLevel == \Psr\Log\LogLevel::NOTICE && !empty($recommendedVersion)) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $message = \AdminLang::trans("healthCheck.dbVersionUpgradeRecommended", [":dbname" => $dbEngineName, ":currentversion" => $dbEngineVersion, ":recommendedversion" => $recommendedVersion]);
        }
        return new HealthCheckResult("dbVersion", "DB", \AdminLang::trans("healthCheck.dbVersionTitle"), $logLevel, $message);
    }

    protected function checkDbCollations()
    {
        $dbCollations = $this->whmcsChecker->getDbCollations();
        $allowedCollations = explode(",", strtolower(self::RECOMMENDED_DB_COLLATIONS));
        $collationsText = str_replace(",", " / ", strtolower(self::RECOMMENDED_DB_COLLATIONS));
        $issues = ["tables" => [], "columns" => []];
        foreach ($dbCollations["tables"] as $tableCollations) {
            if (!in_array($tableCollations->collation, $allowedCollations) || 1 < count($dbCollations["tables"])) {
                $issues["tables"][] = $tableCollations->collation;
            }
        }
        foreach ($dbCollations["columns"] as $columnCollations) {
            if (!in_array($columnCollations->collation, $allowedCollations) || 1 < count($dbCollations["columns"])) {
                $issues["columns"][] = $columnCollations->collation;
            }
        }
        $messageParams = [":collationsText" => $collationsText, ":href" => "href=\"https://docs.whmcs.com/Database_Collations\""];
        if (empty($issues["tables"]) && empty($issues["columns"])) {
            $logLevel = \Psr\Log\LogLevel::NOTICE;
            $message = \AdminLang::trans("healthCheck.dbCollationsOk", $messageParams);
        } else {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $message = \AdminLang::trans("healthCheck.dbCollationsNotOk", $messageParams);
        }
        return new HealthCheckResult("dbCollations", "DB", \AdminLang::trans("healthCheck.dbCollationsTitle"), $logLevel, $message);
    }

    protected function checkPhpVersion()
    {
        $majorMinor = PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;
        $body = "<p>";
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        if (\WHMCS\Environment\Php::isSupportedByWhmcs(PHP_VERSION)) {
            $body .= \AdminLang::trans("healthCheck.phpVersionWhmcsSupported", [":version" => PHP_VERSION]);
        } else {
            $body .= \AdminLang::trans("healthCheck.phpVersionWhmcsUnsupported", [":version" => PHP_VERSION]);
            $logLevel = \Psr\Log\LogLevel::ERROR;
        }
        $body .= "</p><p>";
        if (\WHMCS\Environment\Php::hasSecurityPhpSupport($majorMinor)) {
            if (\WHMCS\Environment\Php::hasActivePhpSupport($majorMinor)) {
                $body .= \AdminLang::trans("healthCheck.phpVersionPhpSupported");
            } else {
                $body .= \AdminLang::trans("healthCheck.phpVersionPhpSecurityUpdatesOnly", [":version" => $majorMinor]);
                $logLevel = \Psr\Log\LogLevel::WARNING;
            }
        } else {
            $body .= \AdminLang::trans("healthCheck.phpVersionPhpUnsupported", [":version" => $majorMinor]);
            $logLevel = \Psr\Log\LogLevel::ERROR;
        }
        $body .= "</p>";
        return new HealthCheckResult("phpVersion", "PHP", \AdminLang::trans("healthCheck.phpVersion"), $logLevel, $body);
    }

    protected function checkBrowserPhpVsCronPhp()
    {
        $cronPhpVersion = \WHMCS\Config\Setting::getValue("CronPHPVersion");
        $currentPhpVersion = \WHMCS\Environment\Php::getVersion();
        if (version_compare($currentPhpVersion, $cronPhpVersion, "!=")) {
            return new HealthCheckResult("phpMismatch", "PHP", \AdminLang::trans("healthCheck.phpCronMismatch"), $logLevel = \Psr\Log\LogLevel::WARNING, \AdminLang::trans("healthCheck.phpCronMismatchDescription", [":cronPhp" => $cronPhpVersion, ":currentPhp" => $currentPhpVersion, ":learnMore" => "https://go.whmcs.com/1539/php-cron-mismatch"]));
        }
        return NULL;
    }

    protected function checkRequiredPhpExtensions()
    {
        $extensions = ["curl", "gd", "ioncube loader", "json", "pdo", "pdo_mysql", "xml"];
        $missingExtensions = [];
        foreach ($extensions as $extension) {
            if (!\WHMCS\Environment\Php::hasExtension($extension)) {
                $missingExtensions[] = $extension;
            }
        }
        $logLevel = 0 < count($missingExtensions) ? \Psr\Log\LogLevel::ERROR : \Psr\Log\LogLevel::NOTICE;
        $message = 0 < count($missingExtensions) ? "<p>" . \AdminLang::trans("healthCheck.requiredPhpExtensionsFailure") . "</p>" . "<ul>" . "<li><strong>" . implode("</strong></li><li><strong>", $missingExtensions) . "</strong></li>" . "</ul>" . "<p>" . \AdminLang::trans("healthCheck.requiredPhpExtensionsFailure2", [":href" => "href=\"https://docs.whmcs.com/System_Requirements\""]) . "</p>" : "<p>" . \AdminLang::trans("healthCheck.requiredPhpExtensionsSuccess") . "</p>";
        return new HealthCheckResult("requiredPhpExtensions", "PHP", \AdminLang::trans("healthCheck.requiredPhpExtensions"), $logLevel, $message);
    }

    protected function checkRecommendedPhpExtensions($HealthCheckResult)
    {
        $extensions = ["fileinfo", "iconv", "mbstring", "soap", "zip"];
        $missingExtensions = [];
        foreach ($extensions as $extension) {
            if (!\WHMCS\Environment\Php::hasExtension($extension)) {
                $missingExtensions[] = $extension;
            }
        }
        $logLevel = 0 < count($missingExtensions) ? \Psr\Log\LogLevel::WARNING : \Psr\Log\LogLevel::NOTICE;
        $message = 0 < count($missingExtensions) ? "<p>" . \AdminLang::trans("healthCheck.recommendedPhpExtensionsFailure") . "</p>" . "<ul>" . "<li><strong>" . implode("</strong></li><li><strong>", $missingExtensions) . "</strong></li>" . "</ul>" . "<p>" . \AdminLang::trans("healthCheck.recommendedPhpExtensionsFailure2", [":href" => "href=\"https://docs.whmcs.com/System_Requirements\""]) . "</p>" : "<p>" . \AdminLang::trans("healthCheck.recommendedPhpExtensionsSuccess") . "</p>";
        return new HealthCheckResult("recommendedPhpExtensions", "PHP", \AdminLang::trans("healthCheck.recommendedPhpExtensions"), $logLevel, $message);
    }

    protected function checkRequiredPhpFunctions()
    {
        $functions = ["base64_decode", "copy", "curl_exec", "curl_multi_exec", "escapeshellcmd", "file_get_contents", "file_put_contents", "fclose", "fopen", "fsockopen", "fwrite", "ini_get", "ini_set", "is_readable", "is_writable", "readfile", "preg_match_all", "print_r", "set_time_limit", "sscanf", "tempnam", "touch", "unlink"];
        $missingFunctions = [];
        foreach ($functions as $function) {
            if (!\WHMCS\Environment\Php::functionEnabled($function)) {
                $missingFunctions[] = $function;
            }
        }
        $logLevel = 0 < count($missingFunctions) ? \Psr\Log\LogLevel::ERROR : \Psr\Log\LogLevel::NOTICE;
        $message = 0 < count($missingFunctions) ? "<p>" . \AdminLang::trans("healthCheck.requiredPhpFunctionsFailure") . "</p>" . "<ul>" . "<li><strong>" . implode("</strong></li><li><strong>", $missingFunctions) . "</strong></li>" . "</ul>" . "<p>" . \AdminLang::trans("healthCheck.requiredPhpFunctionsFailure2") . "</p>" : "<p>" . \AdminLang::trans("healthCheck.requiredPhpFunctionsSuccess") . "</p>";
        return new HealthCheckResult("requiredPhpFunctions", "PHP", \AdminLang::trans("healthCheck.requiredPhpFunctions"), $logLevel, $message);
    }

    protected function checkErrorDisplay()
    {
        $displayErrors = $this->whmcsChecker->isDisplayingErrors(\WHMCS\Config\Setting::getValue("DisplayErrors"), \App::getApplicationConfig()->display_errors);
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $bodyHtml = \AdminLang::trans("healthCheck.errorDisplaySuccess");
        if ($displayErrors) {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $bodyHtml = \AdminLang::trans("healthCheck.errorDisplayFailure", [":href" => "href=\"https://docs.whmcs.com/Enabling_Error_Reporting\""]);
        }
        return new HealthCheckResult("errorDisplay", "PHP", \AdminLang::trans("healthCheck.errorDisplay"), $logLevel, $bodyHtml);
    }

    protected function checkPhpErrorLevels()
    {
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $bodyHtml = \AdminLang::trans("healthCheck.errorLevelsSuccess");
        $displayWarning = \WHMCS\Environment\Php::hasErrorLevelEnabled(error_reporting(), E_WARNING);
        $displayNotice = \WHMCS\Environment\Php::hasErrorLevelEnabled(error_reporting(), E_NOTICE);
        if ($displayWarning || $displayNotice) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $bodyHtml = \AdminLang::trans("healthCheck.errorLevelsFailure", [":href" => "href=\"https://docs.whmcs.com/Enabling_Error_Reporting#If_you_are_unable_to_access_the_Admin_Area\""]);
        }
        return new HealthCheckResult("errorLevels", "PHP", \AdminLang::trans("healthCheck.errorLevels"), $logLevel, $bodyHtml);
    }

    protected function checkPhpMemoryLimit()
    {
        $memoryLimit = \WHMCS\Environment\Php::getPhpMemoryLimitInBytes();
        if (0 <= $memoryLimit && $memoryLimit < self::MINIMUM_MEMORY_LIMIT) {
            if (self::MINIMUM_MEMORY_LIMIT <= $memoryLimit && $memoryLimit < self::RECOMMENDED_MEMORY_LIMIT) {
                $logLevel = \Psr\Log\LogLevel::NOTICE;
                $message = \AdminLang::trans("healthCheck.phpMemorySuccess", [":memory_limit" => ini_get("memory_limit")]);
            } else {
                $logLevel = \Psr\Log\LogLevel::WARNING;
                $message = \AdminLang::trans("healthCheck.phpMemoryLow", [":memory_limit" => ini_get("memory_limit"), ":href" => "href=\"http://php.net/manual/en/ini.core.php#ini.memory-limit\""]);
            }
        } else {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $message = \AdminLang::trans("healthCheck.phpMemoryTooLow", [":memory_limit" => ini_get("memory_limit"), ":href" => "href=\"http://php.net/manual/en/ini.core.php#ini.memory-limit\""]);
        }
        return new HealthCheckResult("phpMemoryLimit", "PHP", \AdminLang::trans("healthCheck.phpMemory"), $logLevel, $message);
    }

    protected function checkCurlVersion()
    {
        $curlVersion = curl_version();
        $curlVersionIsGood = \WHMCS\Environment\Curl::hasKnownGoodVersion($curlVersion);
        $message = \AdminLang::trans("healthCheck.curlCurrentMessage", [":version" => $curlVersion["version"]]);
        if ($curlVersionIsGood) {
            $logLevel = \Psr\Log\LogLevel::NOTICE;
            $message .= " " . \AdminLang::trans("healthCheck.curlCurrentMessageSuccess");
        } else {
            $link = "http://curl.haxx.se/changes.html";
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $message .= " " . \AdminLang::trans("healthCheck.curlNotSecure", [":link" => $link]) . " " . \AdminLang::trans("healthCheck.curlNotSecureAdvice", [":last_bad_version" => \WHMCS\Environment\Curl::LAST_BAD_VERSION]);
        }
        return new HealthCheckResult("installedCurlVersion", "PHP", \AdminLang::trans("healthCheck.installedCurlVersion"), $logLevel, $message);
    }

    protected function checkForCurlSslSupport()
    {
        $curlHasSsl = \WHMCS\Environment\Curl::hasSslSupport(curl_version());
        return new HealthCheckResult("curlSSL", "WHMCS", \AdminLang::trans("healthCheck.curlSslSupport"), $curlHasSsl ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::ERROR, "<p>" . ($curlHasSsl ? \AdminLang::trans("healthCheck.curlSslSupportSuccess") : \AdminLang::trans("healthCheck.curlSslSupportFailure", [":href" => "https://docs.whmcs.com/System_Requirements"])) . "</p>");
    }

    protected function checkForCurlSecureTlsSupport()
    {
        $curlHasSecureTls = \WHMCS\Environment\Curl::hasSecureTlsSupport(curl_version());
        return new HealthCheckResult("curlSecureTLS", "WHMCS", \AdminLang::trans("healthCheck.curlSecureTlsSupport"), $curlHasSecureTls ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::WARNING, "<p>" . ($curlHasSecureTls ? \AdminLang::trans("healthCheck.curlSecureTlsSupportSuccess") : \AdminLang::trans("healthCheck.curlSecureTlsSupportFailure", [":href" => "https://docs.whmcs.com/System_Requirements"])) . "</p>");
    }

    protected function checkPhpSessionSupport()
    {
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $body = "<p>";
        $customSessionSavePathIsWritable = false;
        $hasSessionSupport = \WHMCS\Environment\Php::hasExtension("session");
        $sessionAutoStartEnabled = \WHMCS\Environment\Php::isSessionAutoStartEnabled();
        $sessionPath = (string) ini_get("session.save_path");
        $hasCustomSessionSavePath = $customSessionSavePathIsWritable = false;
        if (0 < strlen($sessionPath)) {
            $hasCustomSessionSavePath = true;
            $customSessionSavePathIsWritable = \WHMCS\Environment\Php::isSessionSavePathWritable();
        }
        if ($hasSessionSupport) {
            $body .= \AdminLang::trans("healthCheck.phpSessionSupportEnabled");
        } else {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $body .= \AdminLang::trans("healthCheck.phpSessionSupportDisabled");
        }
        $body .= "</p><p>";
        if ($sessionAutoStartEnabled) {
            $logLevel = \Psr\Log\LogLevel::ERROR;
            $body .= \AdminLang::trans("healthCheck.phpSessionSupportAutoStartEnabled");
        } else {
            $body .= \AdminLang::trans("healthCheck.phpSessionSupportAutoStartDisabled");
        }
        $body .= "</p>";
        if ($hasCustomSessionSavePath) {
            $body .= "<p>";
            if ($customSessionSavePathIsWritable) {
                $body .= \AdminLang::trans("healthCheck.phpSessionSupportSavePathIsWritable", [":path" => $sessionPath]);
            } else {
                $logLevel = \Psr\Log\LogLevel::ERROR;
                $body .= \AdminLang::trans("healthCheck.phpSessionSupportSavePathIsNotWritable", [":path" => $sessionPath]);
            }
            $body .= "</p>";
        }
        return new HealthCheckResult("sessionSupport", "PHP", \AdminLang::trans("healthCheck.phpSessionSupport"), $logLevel, $body);
    }

    protected function checkPhpTimezone()
    {
        $tzValid = \WHMCS\Environment\Php::hasValidTimezone();
        return new HealthCheckResult("phpSettings", "PHP", \AdminLang::trans("healthCheck.phpTimezone"), $tzValid ? \Psr\Log\LogLevel::NOTICE : \Psr\Log\LogLevel::ERROR, $tzValid ? \AdminLang::trans("healthCheck.phpTimezoneOk") : \AdminLang::trans("healthCheck.phpTimezoneNotSet", [":href" => "https://docs.whmcs.com/Changing_Timezone"]));
    }

    protected function checkForSiteSsl()
    {
        $title = "Website SSL";
        $sslIsRecommended = \AdminLang::trans("healthCheck.sslIsRecommended");
        $purchaseSsl = "<a class=\"autoLinked\" href=\"https://go.whmcs.com/1341/get-ssl-certificate\">" . \AdminLang::trans("healthCheck.purchaseSsl") . "</a>";
        if (!$this->httpChecker->siteIsConfiguredForSsl()) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $noSSLWarning = \AdminLang::trans("healthCheck.sslNotConfigured", [":url" => \WHMCS\Config\Setting::getValue("SystemURL")]);
            $body = $noSSLWarning . "  " . $sslIsRecommended . " " . $purchaseSsl;
        } else {
            if (!$this->httpChecker->siteHasVerifiedSslCert()) {
                $site = \App::getSystemURL();
                $logLevel = \Psr\Log\LogLevel::WARNING;
                $caNotDetectedWarning = \AdminLang::trans("healthCheck.caSslNotDetected", [":site" => $site]);
                $body = $caNotDetectedWarning . "  " . $sslIsRecommended . " " . $purchaseSsl;
            } else {
                $logLevel = \Psr\Log\LogLevel::NOTICE;
                $body = \AdminLang::trans("healthCheck.caSslDetectedOk");
            }
        }
        return new HealthCheckResult("siteSslSupport", "HTTP", $title, $logLevel, $body);
    }

    protected function checkSMTPMailEncryption()
    {
        $title = \AdminLang::trans("healthCheck.emailEncryption");
        $mailConfig = \WHMCS\Module\Mail::getStoredConfiguration();
        if ($this->whmcsChecker->isUsingEncryptedEmailDelivery($mailConfig["configuration"]["secure"])) {
            $logLevel = \Psr\Log\LogLevel::NOTICE;
            $body = \AdminLang::trans("healthCheck.emailEncryptionSuccess");
        } else {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body = \AdminLang::trans("healthCheck.emailEncryptionWarning");
        }
        return new HealthCheckResult("SMTPMailEncryption", "WHMCS", $title, $logLevel, $body);
    }

    public function checkUpdaterRequirements(\WHMCS\Installer\Update\Updater $updater = NULL)
    {
        $memoryLimitRequired = self::DEFAULT_MEMORY_LIMIT_FOR_AUTO_UPDATE;
        $minIoncubeLoaderVersionRequired = NULL;
        $minExecutionTimeLimitSec = 60;
        if (!is_null($updater) && $updater->isUpdateAvailable()) {
            $updateVersion = $updater->getLatestVersion();
            $memoryLimitRequired = $updater->getMemoryLimitRequiredToUpdateTo($updateVersion);
            $minIoncubeLoaderVersionRequired = $updater->getMinimumRequiredIoncubeLoaderVersion($updateVersion);
        }
        $title = \AdminLang::trans("healthCheck.updaterTitle");
        $body = [];
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $requiredFunctions = ["mkdir"];
        $disabledFunctions = [];
        foreach ($requiredFunctions as $functionName) {
            if (!\WHMCS\Environment\Php::isFunctionAvailable($functionName)) {
                $disabledFunctions[] = $functionName;
            }
        }
        if (!empty($disabledFunctions)) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.updaterDisabledFunctions", [":functions" => implode(", ", $disabledFunctions)]);
        }
        if (!\WHMCS\Environment\Php::isIniSettingEnabled("allow_url_fopen")) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.updaterFopen");
        }
        $maxExecutionTime = \WHMCS\Environment\Php::getIniSetting("max_execution_time");
        if ($maxExecutionTime && $maxExecutionTime < $minExecutionTimeLimitSec) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.maxExecutionTime", [":required_value" => $minExecutionTimeLimitSec]);
        }
        if (!\WHMCS\Environment\Php::isModuleActive("zip") && !\WHMCS\Environment\Php::isFunctionAvailable("proc_open")) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.updaterZip");
        }
        if (!\WHMCS\Environment\Php::isFunctionAvailable("chmod")) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.updaterChmod");
        }
        if (!\WHMCS\Environment\Php::isFunctionAvailable("escapeshellarg")) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.updaterEscapeShellArg");
        }
        if (!$this->whmcsChecker->isVendorWhmcsWhmcsWritable()) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.updaterVendorWriteable");
        }
        if (!$this->whmcsChecker->hasEnoughMemoryForUpgrade($memoryLimitRequired)) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $memoryLimitString = sprintf("%dMB", $memoryLimitRequired / 1048576);
            if (!is_null($updateVersion)) {
                $body[] = \AdminLang::trans("healthCheck.updaterVersionMemoryLimit", [":updateVersion" => $updateVersion->getVersion(), ":memoryLimitRequired" => $memoryLimitString]);
            } else {
                $body[] = \AdminLang::trans("healthCheck.updaterGeneralMemoryLimit", [":memoryLimitRequired" => $memoryLimitString]);
            }
        }
        if (!is_null($minIoncubeLoaderVersionRequired) && !is_null($updateVersion)) {
            $installedIoncubeLoaderVersion = \WHMCS\Environment\Ioncube\Loader\LocalLoader::getVersion();
            if (!is_null($installedIoncubeLoaderVersion) && \WHMCS\Version\SemanticVersion::compare($installedIoncubeLoaderVersion, $minIoncubeLoaderVersionRequired, "<")) {
                $logLevel = \Psr\Log\LogLevel::WARNING;
                $body[] = \AdminLang::trans("healthCheck.updaterIoncubeLoaderMismatch", [":loaderVersionInstalled" => $installedIoncubeLoaderVersion->getVersion(), ":loaderVersionRequired" => $minIoncubeLoaderVersionRequired->getVersion(), ":updateVersion" => $updateVersion->getRelease()]);
            }
        }
        if (!$this->whmcsChecker->isUpdateTmpPathSet()) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
            $body[] = \AdminLang::trans("healthCheck.updaterTempSet");
        } else {
            if (!$this->whmcsChecker->isUpdateTmpPathWriteable()) {
                $logLevel = \Psr\Log\LogLevel::WARNING;
                $body[] = \AdminLang::trans("healthCheck.updaterTempWriteable");
            }
        }
        if ($logLevel == \Psr\Log\LogLevel::NOTICE) {
            $body[] = \AdminLang::trans("healthCheck.updaterSuccess");
        }
        return new HealthCheckResult("CheckUpdaterRequirements", "WHMCS", $title, $logLevel, "<ul><li>" . implode("</li><li>", $body) . "</li></ul>");
    }

    public function checkCronExecutionMemoryLimit()
    {
        $cronMemoryLimit = (int) (new \WHMCS\Cron\Status())->getLastCronMemoryLimit();
        $minimumMemoryLimit = self::MINIMUM_MEMORY_LIMIT / 1024 / 1024;
        $recommendedMemoryLimit = self::RECOMMENDED_MEMORY_LIMIT / 1024 / 1024;
        $title = \AdminLang::trans("healthCheck.cronMemoryLimit");
        $logLevel = $body = "";
        if ($cronMemoryLimit) {
            if ($cronMemoryLimit < $minimumMemoryLimit) {
                $logLevel = \Psr\Log\LogLevel::ERROR;
                $body = \AdminLang::trans("healthCheck.cronMemoryLimitBelowMinimum", [":memorylimit" => $cronMemoryLimit . "M", ":recommendedlimit" => $recommendedMemoryLimit . "M", ":minimumlimit" => $minimumMemoryLimit . "M", ":learnmorelink" => "<a href=\"https://docs.whmcs.com/System_Requirements\" target=\"_blank\">" . \AdminLang::trans("global.learnMore") . "</a>"]);
            } else {
                if ($cronMemoryLimit < $recommendedMemoryLimit) {
                    $logLevel = \Psr\Log\LogLevel::WARNING;
                    $body = \AdminLang::trans("healthCheck.cronMemoryLimitBelowRecommended", [":memorylimit" => $cronMemoryLimit . "M", ":recommendedlimit" => $recommendedMemoryLimit . "M", ":learnmorelink" => "<a href=\"https://docs.whmcs.com/System_Requirements\" target=\"_blank\">" . \AdminLang::trans("global.learnMore") . "</a>"]);
                }
            }
        }
        if ($logLevel && $body) {
            return new HealthCheckResult("CronExecutionMemoryLimit", "WHMCS", $title, $logLevel, $body);
        }
        return NULL;
    }

    public function checkMysqlServerVariables()
    {
        $title = \AdminLang::trans("healthCheck.mysqlVariableCheck");
        $variablesToCheck = [["variable" => "connect_timeout", "operator" => "<", "value" => "10"], ["variable" => "wait_timeout", "operator" => "<", "value" => "300"], ["variable" => "interactive_timeout", "operator" => "<", "value" => "300"], ["variable" => "max_allowed_packet", "operator" => "<", "value" => "4194304"]];
        $result = $this->grabDatabaseVariables($variablesToCheck);
        if (!is_array($result)) {
            return NULL;
        }
        $errors = "";
        foreach ($variablesToCheck as $variable) {
            $currentVariable = $variable["variable"];
            $operator = $variable["operator"];
            $value = $variable["value"];
            $checkResult = true;
            $errorVars = [];
            switch ($operator) {
                case "<":
                    if ($result[$currentVariable] < (int) $value) {
                        $errorVars = [":variable" => $currentVariable, ":value" => $result[$currentVariable], ":recommendedvalue" => $value];
                        $checkResult = false;
                    }
                    break;
                case ">":
                    if ((int) $value < $result[$currentVariable]) {
                        $errorVars = [":variable" => $currentVariable, ":value" => $result[$currentVariable], ":recommendedvalue" => $value];
                        $checkResult = false;
                    }
                    break;
                case "==":
                    if ($result[$currentVariable] == $value) {
                        $errorVars = [":variable" => $currentVariable, ":value" => $result[$currentVariable], ":recommendedvalue" => $value];
                        $checkResult = false;
                    }
                    break;
                default:
                    $checkResult = false;
                    if ($errorVars) {
                        $errors .= "<li>" . \AdminLang::trans("healthCheck.mysqlVariableCheckError", $errorVars) . "</li>";
                    }
            }
        }
        if ($errors) {
            $body = "<p style=\"padding-bottom: 10px;\">" . \AdminLang::trans("healthCheck.mysqlVariableCheckErrorBody") . "</p>";
            $body .= "<ul>";
            $body .= $errors;
            $body .= "</ul>";
            return new HealthCheckResult("SQLVariableCheck", "WHMCS", $title, \Psr\Log\LogLevel::WARNING, $body);
        }
    }

    private function grabDatabaseVariables($variablesToCheck)
    {
        $pdo = \WHMCS\Database\Capsule::connection()->getPdo();
        $queryString = "";
        $firstVar = true;
        foreach ($variablesToCheck as $singleVariable) {
            if ($firstVar) {
                $queryString .= "SELECT @@" . $singleVariable["variable"] . " AS " . $singleVariable["variable"];
                $firstVar = false;
            } else {
                $queryString .= ", @@" . $singleVariable["variable"] . " AS " . $singleVariable["variable"];
            }
        }
        $queryString .= ";";
        try {
            $query = $pdo->query($queryString);
            $result = $query->fetch();
        } catch (\Throwable $e) {
            if ((bool) \WHMCS\Config\Setting::getValue("SQLErrorReporting")) {
                logActivity("SQL Error: " . $e->getMessage());
            }
            $result = NULL;
        }
        return $result;
    }

    public function checkCloudLinuxMysqlnd()
    {
        if ($this->osChecker->isServerCloudLinux()) {
            $title = \AdminLang::trans("healthCheck.cloudLinuxMysqlCheck");
            $pdo = \WHMCS\Database\Capsule::connection()->getPdo();
            if (strpos($pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION), "mysqlnd") !== false) {
                $logLevel = \Psr\Log\LogLevel::NOTICE;
                $body = \AdminLang::trans("healthCheck.cloudLinuxMysqlSuccessDescription");
            } else {
                $logLevel = \Psr\Log\LogLevel::WARNING;
                $body = \AdminLang::trans("healthCheck.cloudLinuxMysqlFailDescription", [":learnmorelink" => "<a href=\"https://go.whmcs.com/1621/mysql-extension-check\" target=\"_blank\">" . \AdminLang::trans("global.learnMore") . "</a>"]);
            }
            return new HealthCheckResult("checkLitespeedMysqlnd", "WHMCS", $title, $logLevel, $body);
        }
    }

    public function checkTicketMask($HealthCheckResult)
    {
        $ticketCount = \WHMCS\Support\Ticket::count();
        $possibilities = (new \WHMCS\Support\TicketMask())->gatherMaskPossibilities();
        $percentageUtilised = round($ticketCount / $possibilities * 100, 2);
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $text = \AdminLang::trans("apps.info.documentation");
        $documentationLink = "<a class=\"autoLinked\" href=\"https://docs.whmcs.com/Support_Tab#Support_Ticket_Mask_Format\">" . $text . "</a>";
        $body = \AdminLang::trans("healthCheck.checkTicketMaskDescription", [":possibilities" => $possibilities, ":utilisation" => $percentageUtilised, ":alerts" => \AdminLang::trans("healthCheck.checkTicketMaskAlert", [":documentationLink" => $documentationLink])]);
        if ($percentageUtilised <= 50) {
            $body = \AdminLang::trans("healthCheck.checkTicketMaskDescription", [":possibilities" => $possibilities, ":utilisation" => $percentageUtilised, ":alerts" => ""]);
        }
        if (50 < $percentageUtilised && $percentageUtilised <= 85) {
            $logLevel = \Psr\Log\LogLevel::WARNING;
        } else {
            if (85 < $percentageUtilised) {
                $logLevel = \Psr\Log\LogLevel::ERROR;
            }
        }
        return new HealthCheckResult("checkTicketMask", "WHMCS", \AdminLang::trans("healthCheck.checkTicketMask"), $logLevel, $body);
    }

    public function checkMissedAsyncJobs($HealthCheckResult)
    {
        $logLevel = \Psr\Log\LogLevel::NOTICE;
        $body = \AdminLang::trans("healthCheck.asyncJobs.success");
        if (\WHMCS\Product\EventAction\EventActionProcessorHandler::getEventHandlingMode() === \WHMCS\Product\EventAction\EventActionProcessorHandler::EVENT_HANDLING_MODE_ASYNC) {
            $staleAsyncJobCount = \WHMCS\Scheduling\Jobs\Queue::isAsync()->where("created_at", "<", \WHMCS\Carbon::now()->subHours(1))->whereNull("started_at")->count();
            if (0 < $staleAsyncJobCount) {
                $logLevel = \Psr\Log\LogLevel::WARNING;
                $body = \AdminLang::trans("healthCheck.asyncJobs.staleJobs");
            }
        }
        return new HealthCheckResult("checkMissedAsyncJobs", "WHMCS", \AdminLang::trans("healthCheck.asyncJobs.title"), $logLevel, $body);
    }

    public function checkModuleLogIsEnabled($HealthCheckResult)
    {
        $isModuleLogEnabled = (bool) \WHMCS\Config\Setting::getValue("ModuleDebugMode");
        if (!$isModuleLogEnabled) {
            return NULL;
        }
        $healthCheckLangString = "healthCheck.moduleLogEnabled.";
        return new HealthCheckResult("checkModuleLogIsEnabled", "WHMCS", \AdminLang::trans($healthCheckLangString . "title"), \Psr\Log\LogLevel::WARNING, \AdminLang::trans($healthCheckLangString . "isEnabled", [":href" => "https://go.whmcs.com/1657/Module-Troubleshooting"]));
    }

    protected function checkLegacySmartyTags($HealthCheckResult)
    {
        $tagScanner = new \WHMCS\Utility\Smarty\TagScanner();
        $isAllowSmartyPhpTagsEnabled = (bool) \WHMCS\Config\Setting::getValue("AllowSmartyPhpTags");
        $scanResultCount = $tagScanner->getScanResultCount(\WHMCS\Utility\Smarty\TagScanner::DEPRECATED_SMARTY_BC_TAGS_CACHE_KEY);
        if (!($isAllowSmartyPhpTagsEnabled || 0 < $scanResultCount)) {
            return NULL;
        }
        $adminBaseUrl = \WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl();
        $reportUrl = $adminBaseUrl . "/reports.php?report=smarty_compatibility";
        $settingsUrl = $adminBaseUrl . "/configgeneral.php?tab=10";
        $transParams = [":anchorReport" => "<a href=\"" . $reportUrl . "\">", ":anchorSettings" => "<a href=\"" . $settingsUrl . "\">", ":anchorDocs" => "<a href=\"https://go.whmcs.com/1733/legacy-smarty-tags\">", ":anchorClose" => "</a>"];
        if ($isAllowSmartyPhpTagsEnabled && 0 < $scanResultCount) {
            $transKey = "healthCheck.legacySmartyTags.body.tagsAndSetting";
        } else {
            if (0 < $scanResultCount) {
                $transKey = "healthCheck.legacySmartyTags.body.tagsOnly";
            } else {
                $transKey = "healthCheck.legacySmartyTags.body.settingOnly";
            }
        }
        return new HealthCheckResult("legacySmartyTagsCheck", "WHMCS", \AdminLang::trans("healthCheck.legacySmartyTags.heading"), \Psr\Log\LogLevel::WARNING, \AdminLang::trans($transKey, $transParams));
    }
}
