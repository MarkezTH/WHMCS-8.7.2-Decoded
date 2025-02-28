<?php

namespace WHMCS\Installer\Composer;

class UpdateNotificationRepository
{
    private $currentVersion = NULL;
    private $updateVersion = NULL;
    private $latestBetaVersion = NULL;
    private $latestStableVersion = NULL;
    private $latestSupportAndUpdatesVersion = NULL;
    private $currentTier = NULL;
    private $tierIsVersion = false;
    private $channel = NULL;
    private $composerJson = NULL;
    private $license = NULL;

    public function __construct(Channels $channel)
    {
        $this->currentVersion = \App::getVersion();
        $this->channel = $channel;
        $this->channel->setLtsJson(\WHMCS\Config\Setting::getValue("UpdaterLTS"));
        $this->composerJson = new ComposerJson();
        $this->setCurrentTier(\WHMCS\Config\Setting::getValue("WHMCSUpdatePinVersion"));
        $this->setLicense(\App::getLicense());
    }

    public function setCurrentVersion($version)
    {
        $this->currentVersion = new \WHMCS\Version\SemanticVersion($version);
        return $this;
    }

    public function setLicense(\WHMCS\License $license)
    {
        $this->license = $license;
        return $this;
    }

    public function getCurrentVersion()
    {
        return $this->currentVersion;
    }

    public function setUpdateVersion($version)
    {
        $this->updateVersion = new \WHMCS\Version\SemanticVersion($version);
        return $this;
    }

    public function getUpdateVersion()
    {
        return $this->updateVersion;
    }

    public function setLatestBetaVersion($version)
    {
        $this->latestBetaVersion = new \WHMCS\Version\SemanticVersion($version);
        return $this;
    }

    public function getLatestBetaVersion()
    {
        return $this->latestBetaVersion;
    }

    public function setLatestStableVersion($version)
    {
        $this->latestStableVersion = new \WHMCS\Version\SemanticVersion($version);
        return $this;
    }

    public function getLatestStableVersion()
    {
        return $this->latestStableVersion;
    }

    public function setLatestSupportAndUpdatesVersion($version)
    {
        $this->latestSupportAndUpdatesVersion = new \WHMCS\Version\SemanticVersion($version);
        return $this;
    }

    public function getLatestSupportAndUpdatesVersion()
    {
        return $this->latestSupportAndUpdatesVersion;
    }

    public function setCurrentTier($tier)
    {
        $this->currentTier = $tier;
        if (in_array($tier, $this->channel->getMinStabilityLevels())) {
            $this->tierIsVersion = false;
        } else {
            $this->tierIsVersion = true;
        }
        return $this;
    }

    public function getCurrentTier()
    {
        return $this->currentTier;
    }

    public function getNotifications()
    {
        return ["rcAvailable" => $this->checkIfPrereleaseRCUpdateAvailable(), "betaAvailable" => $this->checkIfPrereleaseBetaUpdateAvailable(), "pinnedBlock" => $this->checkIfPinnedVersionPreventsUpdate(), "pinnedEol" => $this->checkIfPinnedVersionIsUnsupported(), "updatesBlock" => $this->checkIfSupportAndUpdatesBlocksUpdate()];
    }

    public function checkIfPrereleaseRCUpdateAvailable()
    {
        if ($this->updateVersion->getCanonical() == $this->latestBetaVersion->getCanonical()) {
            return NULL;
        }
        if ($this->currentTier == ComposerJson::STABILITY_RC) {
            return NULL;
        }
        if (strtoupper($this->latestBetaVersion->getPreReleaseIdentifier()) != ComposerJson::STABILITY_RC) {
            return NULL;
        }
        if (\WHMCS\Version\SemanticVersion::compare($this->updateVersion, $this->latestBetaVersion, ">")) {
            return NULL;
        }
        if (\WHMCS\Version\SemanticVersion::compare($this->currentVersion, $this->latestBetaVersion, ">") || \WHMCS\Version\SemanticVersion::compare($this->currentVersion, $this->latestBetaVersion, "=")) {
            return NULL;
        }
        $title = "New Release Candidate Build Available";
        $body = "There is a new release candidate build available for testing: " . $this->latestBetaVersion->getCanonical() . ". If you want to update to it, change your channel to RC.";
        return new UpdateNotification("RCUpdateAvailable", "WHMCS", $title, \Psr\Log\LogLevel::NOTICE, $body);
    }

    public function checkIfPrereleaseBetaUpdateAvailable()
    {
        if ($this->updateVersion->getCanonical() == $this->latestBetaVersion->getCanonical()) {
            return NULL;
        }
        if ($this->currentTier == ComposerJson::STABILITY_BETA) {
            return NULL;
        }
        if ($this->latestBetaVersion->getPreReleaseIdentifier() != ComposerJson::STABILITY_BETA) {
            return NULL;
        }
        if (\WHMCS\Version\SemanticVersion::compare($this->updateVersion, $this->latestBetaVersion, ">")) {
            return NULL;
        }
        if (\WHMCS\Version\SemanticVersion::compare($this->currentVersion, $this->latestBetaVersion, ">") || \WHMCS\Version\SemanticVersion::compare($this->currentVersion, $this->latestBetaVersion, "=")) {
            return NULL;
        }
        $title = "New Beta Build Available";
        $body = "There is a new beta build available for testing: " . $this->latestBetaVersion->getCanonical() . ". If you want to update to it, change your channel to beta. Note we don't recommend running beta releases in production.";
        return new UpdateNotification("BetaUpdateAvailable", "WHMCS", $title, \Psr\Log\LogLevel::NOTICE, $body);
    }

    public function checkIfPinnedVersionPreventsUpdate()
    {
        if (!$this->tierIsVersion) {
            return NULL;
        }
        if ($this->updateVersion->getCanonical() == $this->latestStableVersion->getCanonical()) {
            return NULL;
        }
        $title = "Newer Stable Version Available";
        $body = "There is a newer stable release available (" . $this->latestStableVersion->getCanonical() . ") however you are pinned to " . $this->currentVersion->getMajor() . "." . $this->currentVersion->getMinor() . " and this is preventing you from updating. If you want to install the latest version, change your channel to 'stable'.";
        return new UpdateNotification("NewStableBlockedByPinnedChannel", "WHMCS", $title, \Psr\Log\LogLevel::NOTICE, $body);
    }

    public function checkIfPinnedVersionIsUnsupported()
    {
        if (!$this->tierIsVersion) {
            return NULL;
        }
        if (!$this->channel->isPinOutOfLTS($this->currentTier)) {
            return NULL;
        }
        $title = "Pinned to Unsupported Minor Version";
        $body = "The minor version you have pinned to (" . $this->currentVersion->getMajor() . "." . $this->currentVersion->getMinor() . ") has reached end of life and is no longer receiving security updates. To ensure you remain secure we recommend upgrading as soon as possible.";
        return new UpdateNotification("OutOfLTS", "WHMCS", $title, \Psr\Log\LogLevel::ERROR, $body);
    }

    public function checkIfSupportAndUpdatesBlocksUpdate()
    {
        if (!$this->license->getRequiresUpdates()) {
            return NULL;
        }
        if ($this->updateVersion->getCanonical() == $this->latestSupportAndUpdatesVersion->getCanonical()) {
            return NULL;
        }
        if ($this->tierIsVersion && $this->latestStableVersion->getMajor() . "." . $this->latestStableVersion->getMinor() != $this->latestSupportAndUpdatesVersion->getMajor() . "." . $this->latestSupportAndUpdatesVersion->getMinor()) {
            return NULL;
        }
        $title = "Newer Stable Version Available";
        $body = "There is a newer release of WHMCS available. However, access to upgrades is no longer available for this license. To update, you must purchase a new license key.";
        return new UpdateNotification("RenewSupportAndUpdates", "WHMCS", $title, \Psr\Log\LogLevel::WARNING, $body);
    }
}
