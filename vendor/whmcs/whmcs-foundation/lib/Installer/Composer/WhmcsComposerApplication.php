<?php

namespace WHMCS\Installer\Composer;

class WhmcsComposerApplication extends \Composer\Console\Application
{
    private $overrideConfig = NULL;
    private $packageMetadata = [];

    public function getOverrideConfig()
    {
        return $this->overrideConfig;
    }

    public function setOverrideConfig($value)
    {
        $this->overrideConfig = $value;
        return $this;
    }

    public function getComposer($required = true, $disablePlugins = false)
    {
        if (NULL === $this->composer) {
            try {
                $this->composer = WhmcsComposerFactory::create($this->io, $this->overrideConfig, $disablePlugins);
            } catch (\InvalidArgumentException $e) {
                if ($required) {
                    $this->io->writeError($e->getMessage());
                    exit(1);
                }
            } catch (\Composer\Json\JsonValidationException $e) {
                $errors = " - " . implode(PHP_EOL . " - ", $e->getErrors());
                $message = $e->getMessage() . ":" . PHP_EOL . $errors;
                throw new \Composer\Json\JsonValidationException($message);
            }
        }
        $locker = new \Composer\Package\Locker($this->io, new \Composer\Json\JsonFile(ROOTDIR . DIRECTORY_SEPARATOR . "composer.lock", NULL, $this->io), $this->composer->getRepositoryManager(), $this->composer->getInstallationManager(), json_encode($this->overrideConfig));
        $this->composer->setLocker($locker);
        return $this->composer;
    }

    protected function doRunCommand(\Symfony\Component\Console\Command\Command $command, \Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        $validatedZipRfs = new WhmcsRemoteFilesystem($this->io, $this->getComposer()->getConfig());
        $downloader = new ValidatedZipDownloader($this->io, $this->getComposer()->getConfig(), $this->getComposer()->getEventDispatcher(), NULL, NULL, $validatedZipRfs);
        $downloader->setPackageMetadataCallback(function ($packageName, $metadata) {
            $this->packageMetadata[$packageName] = $metadata;
        });
        $this->getComposer()->getDownloadManager()->setDownloader("zip", $downloader);
        return parent::doRunCommand($command, $input, $output);
    }

    public function getPackageMetadata()
    {
        return $this->packageMetadata;
    }
}
