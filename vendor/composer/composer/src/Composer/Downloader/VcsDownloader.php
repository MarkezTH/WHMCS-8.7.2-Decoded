<?php
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Downloader;

use Composer\Config;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionGuesser;
use Composer\Package\Version\VersionParser;
use Composer\Util\ProcessExecutor;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
abstract class VcsDownloader implements DownloaderInterface, ChangeReportInterface, VcsCapableDownloaderInterface
{
    /** @var IOInterface */
    protected $io;
    /** @var Config */
    protected $config;
    /** @var ProcessExecutor */
    protected $process;
    /** @var Filesystem */
    protected $filesystem;

    public function __construct(IOInterface $io, Config $config, ProcessExecutor $process = null, Filesystem $fs = null)
    {
        $this->io = $io;
        $this->config = $config;
        $this->process = $process ?: new ProcessExecutor($io);
        $this->filesystem = $fs ?: new Filesystem($this->process);
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallationSource()
    {
        return 'source';
    }

    /**
     * {@inheritDoc}
     */
    public function download(PackageInterface $package, $path)
    {
        if (!$package->getSourceReference()) {
            throw new \InvalidArgumentException('Package '.$package->getPrettyName().' is missing reference information');
        }

        $this->io->writeError("  - Installing <info>" . $package->getName() . "</info> (<comment>" . $package->getFullPrettyVersion() . "</comment>): ", false);
        $this->filesystem->emptyDirectory($path);

        $urls = $package->getSourceUrls();
        while ($url = array_shift($urls)) {
            try {
                if (Filesystem::isLocalPath($url)) {
                    // realpath() below will not understand
                    // url that starts with "file://"
                    $needle = 'file://';
                    $isFileProtocol = false;
                    if (0 === strpos($url, $needle)) {
                        $url = substr($url, strlen($needle));
                        $isFileProtocol = true;
                    }

                    // realpath() below will not understand %20 spaces etc.
                    if (false !== strpos($url, '%')) {
                        $url = rawurldecode($url);
                    }

                    $url = realpath($url);

                    if ($isFileProtocol) {
                        $url = $needle . $url;
                    }
                }
                $this->doDownload($package, $path, $url);
                break;
            } catch (\Exception $e) {
                // rethrow phpunit exceptions to avoid hard to debug bug failures
                if ($e instanceof \PHPUnit_Framework_Exception) {
                    throw $e;
                }
                if ($this->io->isDebug()) {
                    $this->io->writeError('Failed: ['.get_class($e).'] '.$e->getMessage());
                } elseif (count($urls)) {
                    $this->io->writeError('    Failed, trying the next URL');
                }
                if (!count($urls)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(PackageInterface $initial, PackageInterface $target, $path)
    {
        if (!$target->getSourceReference()) {
            throw new \InvalidArgumentException('Package '.$target->getPrettyName().' is missing reference information');
        }

        $name = $target->getName();
        if ($initial->getPrettyVersion() == $target->getPrettyVersion()) {
            if ($target->getSourceType() === 'svn') {
                $from = $initial->getSourceReference();
                $to = $target->getSourceReference();
            } else {
                $from = substr($initial->getSourceReference(), 0, 7);
                $to = substr($target->getSourceReference(), 0, 7);
            }
            $name .= ' '.$initial->getPrettyVersion();
        } else {
            $from = $initial->getFullPrettyVersion();
            $to = $target->getFullPrettyVersion();
        }

        $actionName = VersionParser::isUpgrade($initial->getVersion(), $target->getVersion()) ? 'Updating' : 'Downgrading';
        $this->io->writeError("  - " . $actionName . " <info>" . $name . "</info> (<comment>" . $from . "</comment> => <comment>" . $to . "</comment>): ", false);

        $this->cleanChanges($initial, $path, true);
        $urls = $target->getSourceUrls();

        $exception = null;
        while ($url = array_shift($urls)) {
            try {
                if (Filesystem::isLocalPath($url)) {
                    $url = realpath($url);
                }
                $this->doUpdate($initial, $target, $path, $url);

                $exception = null;
                break;
            } catch (\Exception $exception) {
                // rethrow phpunit exceptions to avoid hard to debug bug failures
                if ($exception instanceof \PHPUnit_Framework_Exception) {
                    throw $exception;
                }
                if ($this->io->isDebug()) {
                    $this->io->writeError('Failed: ['.get_class($exception).'] '.$exception->getMessage());
                } elseif (count($urls)) {
                    $this->io->writeError('    Failed, trying the next URL');
                }
            }
        }

        $this->reapplyChanges($path);

        // print the commit logs if in verbose mode and VCS metadata is present
        // because in case of missing metadata code would trigger another exception
        if (!$exception && $this->io->isVerbose() && $this->hasMetadataRepository($path)) {
            $message = 'Pulling in changes:';
            $logs = $this->getCommitLogs($initial->getSourceReference(), $target->getSourceReference(), $path);

            if (!trim($logs)) {
                $message = 'Rolling back changes:';
                $logs = $this->getCommitLogs($target->getSourceReference(), $initial->getSourceReference(), $path);
            }

            if (trim($logs)) {
                $logs = implode("\n", array_map(function ($line) {
                    return '      ' . $line;
                }, explode("\n", $logs)));

                // escape angle brackets for proper output in the console
                $logs = str_replace('<', '\<', $logs);

                $this->io->writeError('    '.$message);
                $this->io->writeError($logs);
            }
        }

        if (!$urls && $exception) {
            throw $exception;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function remove(PackageInterface $package, $path)
    {
        $this->io->writeError("  - Removing <info>" . $package->getName() . "</info> (<comment>" . $package->getPrettyVersion() . "</comment>)");
        $this->cleanChanges($package, $path, false);
        if (!$this->filesystem->removeDirectory($path)) {
            throw new \RuntimeException('Could not completely delete '.$path.', aborting.');
        }
    }

    /**
     * Download progress information is not available for all VCS downloaders.
     * {@inheritDoc}
     */
    public function setOutputProgress($outputProgress)
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getVcsReference(PackageInterface $package, $path)
    {
        $parser = new VersionParser;
        $guesser = new VersionGuesser($this->config, $this->process, $parser);
        $dumper = new ArrayDumper;

        $packageConfig = $dumper->dump($package);
        if ($packageVersion = $guesser->guessVersion($packageConfig, $path)) {
            return $packageVersion['commit'];
        }
    }

    /**
     * Prompt the user to check if changes should be stashed/removed or the operation aborted
     *
     * @param  PackageInterface  $package
     * @param  string            $path
     * @param  bool              $update  if true (update) the changes can be stashed and reapplied after an update,
     *                                    if false (remove) the changes should be assumed to be lost if the operation is not aborted
     * @throws \RuntimeException in case the operation must be aborted
     */
    protected function cleanChanges(PackageInterface $package, $path, $update)
    {
        // the default implementation just fails if there are any changes, override in child classes to provide stash-ability
        if (null !== $this->getLocalChanges($package, $path)) {
            throw new \RuntimeException('Source directory ' . $path . ' has uncommitted changes.');
        }
    }

    /**
     * Guarantee that no changes have been made to the local copy
     *
     * @param  string            $path
     * @throws \RuntimeException in case the operation must be aborted or the patch does not apply cleanly
     */
    protected function reapplyChanges($path)
    {
    }

    /**
     * Downloads specific package into specific folder.
     *
     * @param PackageInterface $package package instance
     * @param string           $path    download path
     * @param string           $url     package url
     */
    abstract protected function doDownload(PackageInterface $package, $path, $url);

    /**
     * Updates specific package in specific folder from initial to target version.
     *
     * @param PackageInterface $initial initial package
     * @param PackageInterface $target  updated package
     * @param string           $path    download path
     * @param string           $url     package url
     */
    abstract protected function doUpdate(PackageInterface $initial, PackageInterface $target, $path, $url);

    /**
     * Fetches the commit logs between two commits
     *
     * @param  string $fromReference the source reference
     * @param  string $toReference   the target reference
     * @param  string $path          the package path
     * @return string
     */
    abstract protected function getCommitLogs($fromReference, $toReference, $path);

    /**
     * Checks if VCS metadata repository has been initialized
     * repository example: .git|.svn|.hg
     *
     * @param  string $path
     * @return bool
     */
    abstract protected function hasMetadataRepository($path);
}
