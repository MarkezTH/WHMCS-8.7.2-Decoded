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

namespace Composer\Command;

use Composer\Composer;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Pool;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\VersionSelector;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Repository\ArrayRepository;
use Composer\Repository\ComposerRepository;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryInterface;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Semver;
use Composer\Spdx\SpdxLicenses;
use Composer\Util\Platform;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Jérémy Romey <jeremyFreeAgent>
 * @author Mihai Plasoianu <mihai@plasoianu.de>
 */
class ShowCommand extends BaseCommand
{
    /** @var VersionParser */
    protected $versionParser;
    protected $colors;

    /** @var Pool */
    private $pool;

    protected function configure()
    {
        $this
            ->setName('show')
            ->setAliases(array('info'))
            ->setDescription('Shows information about packages.')
            ->setDefinition(array(
                new InputArgument('package', InputArgument::OPTIONAL, 'Package to inspect. Or a name including a wildcard (*) to filter lists of packages instead.'),
                new InputArgument('version', InputArgument::OPTIONAL, 'Version or version constraint to inspect'),
                new InputOption('all', null, InputOption::VALUE_NONE, 'List all packages'),
                new InputOption('installed', 'i', InputOption::VALUE_NONE, 'List installed packages only (enabled by default, only present for BC).'),
                new InputOption('platform', 'p', InputOption::VALUE_NONE, 'List platform packages only'),
                new InputOption('available', 'a', InputOption::VALUE_NONE, 'List available packages only'),
                new InputOption('self', 's', InputOption::VALUE_NONE, 'Show the root package information'),
                new InputOption('name-only', 'N', InputOption::VALUE_NONE, 'List package names only'),
                new InputOption('path', 'P', InputOption::VALUE_NONE, 'Show package paths'),
                new InputOption('tree', 't', InputOption::VALUE_NONE, 'List the dependencies as a tree'),
                new InputOption('latest', 'l', InputOption::VALUE_NONE, 'Show the latest version'),
                new InputOption('outdated', 'o', InputOption::VALUE_NONE, 'Show the latest version but only for packages that are outdated'),
                new InputOption('ignore', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Ignore specified package(s). Use it with the --outdated option if you don\'t want to be informed about new versions of some packages.'),
                new InputOption('minor-only', 'm', InputOption::VALUE_NONE, 'Show only packages that have minor SemVer-compatible updates. Use with the --outdated option.'),
                new InputOption('direct', 'D', InputOption::VALUE_NONE, 'Shows only packages that are directly required by the root package'),
                new InputOption('strict', null, InputOption::VALUE_NONE, 'Return a non-zero exit code when there are outdated packages'),
                new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Format of the output: text or json', 'text'),
            ))
            ->setHelp(
                <<<EOT
The show command displays detailed information about a package, or
lists all packages available.

Read more at https://getcomposer.org/doc/03-cli.md#show
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->versionParser = new VersionParser;
        if ($input->getOption('tree')) {
            $this->initStyles($output);
        }

        $composer = $this->getComposer(false);
        $io = $this->getIO();

        if ($input->getOption('installed')) {
            $io->writeError('<warning>You are using the deprecated option "installed". Only installed packages are shown by default now. The --all option can be used to show all packages.</warning>');
        }

        if ($input->getOption('outdated')) {
            $input->setOption('latest', true);
        } elseif ($input->getOption('ignore')) {
            $io->writeError('<warning>You are using the option "ignore" for action other than "outdated", it will be ignored.</warning>');
        }

        if ($input->getOption('direct') && ($input->getOption('all') || $input->getOption('available') || $input->getOption('platform'))) {
            $io->writeError('The --direct (-D) option is not usable in combination with --all, --platform (-p) or --available (-a)');

            return 1;
        }

        if ($input->getOption('tree') && ($input->getOption('all') || $input->getOption('available'))) {
            $io->writeError('The --tree (-t) option is not usable in combination with --all or --available (-a)');

            return 1;
        }

        if ($input->getOption('tree') && $input->getOption('latest')) {
            $io->writeError('The --tree (-t) option is not usable in combination with --latest (-l)');

            return 1;
        }

        if ($input->getOption('tree') && $input->getOption('path')) {
            $io->writeError('The --tree (-t) option is not usable in combination with --path (-P)');

            return 1;
        }

        $format = $input->getOption('format');
        if (!in_array($format, array('text', 'json'))) {
            $io->writeError(sprintf('Unsupported format "%s". See help for supported formats.', $format));

            return 1;
        }

        // init repos
        $platformOverrides = array();
        if ($composer) {
            $platformOverrides = $composer->getConfig()->get('platform') ?: array();
        }
        $platformRepo = new PlatformRepository(array(), $platformOverrides);
        $phpVersion = $platformRepo->findPackage('php', '*')->getVersion();

        if ($input->getOption('self')) {
            $package = $this->getComposer()->getPackage();
            if ($input->getOption('name-only')) {
                $io->write($package->getName());
                return 0;
            }
            $repos = $installedRepo = new ArrayRepository(array($package));
        } elseif ($input->getOption('platform')) {
            $repos = $installedRepo = $platformRepo;
        } elseif ($input->getOption('available')) {
            $installedRepo = $platformRepo;
            if ($composer) {
                $repos = new CompositeRepository($composer->getRepositoryManager()->getRepositories());
            } else {
                $defaultRepos = RepositoryFactory::defaultRepos($io);
                $repos = new CompositeRepository($defaultRepos);
                $io->writeError('No composer.json found in the current directory, showing available packages from ' . implode(', ', array_keys($defaultRepos)));
            }
        } elseif ($input->getOption('all') && $composer) {
            $localRepo = $composer->getRepositoryManager()->getLocalRepository();
            $installedRepo = new CompositeRepository(array($localRepo, $platformRepo));
            $repos = new CompositeRepository(array_merge(array($installedRepo), $composer->getRepositoryManager()->getRepositories()));
        } elseif ($input->getOption('all')) {
            $defaultRepos = RepositoryFactory::defaultRepos($io);
            $io->writeError('No composer.json found in the current directory, showing available packages from ' . implode(', ', array_keys($defaultRepos)));
            $installedRepo = $platformRepo;
            $repos = new CompositeRepository(array_merge(array($installedRepo), $defaultRepos));
        } else {
            $repos = $installedRepo = $this->getComposer()->getRepositoryManager()->getLocalRepository();
            $rootPkg = $this->getComposer()->getPackage();
            if (!$installedRepo->getPackages() && ($rootPkg->getRequires() || $rootPkg->getDevRequires())) {
                $io->writeError('<warning>No dependencies installed. Try running composer install or update.</warning>');
            }
        }

        if ($composer) {
            $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'show', $input, $output);
            $composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);
        }

        if ($input->getOption('latest') && null === $composer) {
            $io->writeError('No composer.json found in the current directory, disabling "latest" option');
            $input->setOption('latest', false);
        }

        $packageFilter = $input->getArgument('package');

        // show single package or single version
        if (($packageFilter && false === strpos($packageFilter, '*')) || !empty($package)) {
            if (empty($package)) {
                list($package, $versions) = $this->getPackage($installedRepo, $repos, $input->getArgument('package'), $input->getArgument('version'));

                if (empty($package)) {
                    $options = $input->getOptions();
                    if (!isset($options['working-dir']) || !file_exists('composer.json')) {
                        if (preg_match(PlatformRepository::PLATFORM_PACKAGE_REGEX, $input->getArgument('package')) && !$input->getOption('platform')) {
                            throw new \InvalidArgumentException('Package ' . $packageFilter . ' not found, try using --platform (-p) to show platform packages.');
                        }
                        throw new \InvalidArgumentException('Package ' . $packageFilter . ' not found');
                    }

                    $io->writeError('Package ' . $packageFilter . ' not found in ' . $options['working-dir'] . '/composer.json');

                    return 1;
                }
            } else {
                $versions = array($package->getPrettyVersion() => $package->getVersion());
            }

            $exitCode = 0;
            if ($input->getOption('tree')) {
                $arrayTree = $this->generatePackageTree($package, $installedRepo, $repos);

                if ('json' === $format) {
                    $io->write(JsonFile::encode(array('installed' => array($arrayTree))));
                } else {
                    $this->displayPackageTree(array($arrayTree));
                }
            } else {
                $latestPackage = null;
                if ($input->getOption('latest')) {
                    $latestPackage = $this->findLatestPackage($package, $composer, $phpVersion);
                }
                if ($input->getOption('outdated') && $input->getOption('strict') && $latestPackage && $latestPackage->getFullPrettyVersion() !== $package->getFullPrettyVersion() && !$latestPackage->isAbandoned()) {
                    $exitCode = 1;
                }
                if ($input->getOption('path')) {
                    $io->write($package->getName(), false);
                    $io->write(' ' . strtok(realpath($composer->getInstallationManager()->getInstallPath($package)), "\r\n"));

                    return $exitCode;
                }

                if ('json' === $format) {
                    $this->printPackageInfoAsJson($package, $versions, $installedRepo, $latestPackage ?: null);
                } else {
                    $this->printPackageInfo($package, $versions, $installedRepo, $latestPackage ?: null);
                }
            }

            return $exitCode;
        }

        // show tree view if requested
        if ($input->getOption('tree')) {
            $rootRequires = $this->getRootRequires();
            $packages = $installedRepo->getPackages();
            usort($packages, 'strcmp');
            $arrayTree = array();
            foreach ($packages as $package) {
                if (in_array($package->getName(), $rootRequires, true)) {
                    $arrayTree[] = $this->generatePackageTree($package, $installedRepo, $repos);
                }
            }

            if ('json' === $format) {
                $io->write(JsonFile::encode(array('installed' => $arrayTree)));
            } else {
                $this->displayPackageTree($arrayTree);
            }

            return 0;
        }

        if ($repos instanceof CompositeRepository) {
            $repos = $repos->getRepositories();
        } elseif (!is_array($repos)) {
            $repos = array($repos);
        }

        // list packages
        $packages = array();
        if (null !== $packageFilter) {
            $packageFilter = '{^'.str_replace('\\*', '.*?', preg_quote($packageFilter)).'$}i';
        }

        $packageListFilter = array();
        if ($input->getOption('direct')) {
            $packageListFilter = $this->getRootRequires();
        }

        if (class_exists('Symfony\Component\Console\Terminal')) {
            $terminal = new Terminal();
            $width = $terminal->getWidth();
        } else {
            // For versions of Symfony console before 3.2
            list($width) = $this->getApplication()->getTerminalDimensions();
        }
        if (null === $width) {
            // In case the width is not detected, we're probably running the command
            // outside of a real terminal, use space without a limit
            $width = PHP_INT_MAX;
        }
        if (Platform::isWindows()) {
            $width--;
        } else {
            $width = max(80, $width);
        }

        if ($input->getOption('path') && null === $composer) {
            $io->writeError('No composer.json found in the current directory, disabling "path" option');
            $input->setOption('path', false);
        }

        foreach ($repos as $repo) {
            if ($repo === $platformRepo) {
                $type = 'platform';
            } elseif (
                $repo === $installedRepo
                || ($installedRepo instanceof CompositeRepository && in_array($repo, $installedRepo->getRepositories(), true))
            ) {
                $type = 'installed';
            } else {
                $type = 'available';
            }
            if ($repo instanceof ComposerRepository && $repo->hasProviders()) {
                foreach ($repo->getProviderNames() as $name) {
                    if (!$packageFilter || preg_match($packageFilter, $name)) {
                        $packages[$type][$name] = $name;
                    }
                }
            } else {
                foreach ($repo->getPackages() as $package) {
                    if (!isset($packages[$type][$package->getName()])
                        || !is_object($packages[$type][$package->getName()])
                        || version_compare($packages[$type][$package->getName()]->getVersion(), $package->getVersion(), '<')
                    ) {
                        if (!$packageFilter || preg_match($packageFilter, $package->getName())) {
                            if (!$packageListFilter || in_array($package->getName(), $packageListFilter, true)) {
                                $packages[$type][$package->getName()] = $package;
                            }
                        }
                    }
                }
            }
        }

        $showAllTypes = $input->getOption('all');
        $showLatest = $input->getOption('latest');
        $showMinorOnly = $input->getOption('minor-only');
        $ignoredPackages = array_map('strtolower', $input->getOption('ignore'));
        $indent = $showAllTypes ? '  ' : '';
        $latestPackages = array();
        $exitCode = 0;
        $viewData = array();
        $viewMetaData = array();
        foreach (array('platform' => true, 'available' => false, 'installed' => true) as $type => $showVersion) {
            if (isset($packages[$type])) {
                ksort($packages[$type]);

                $nameLength = $versionLength = $latestLength = 0;

                if ($showLatest && $showVersion) {
                    foreach ($packages[$type] as $package) {
                        if (is_object($package)) {
                            $latestPackage = $this->findLatestPackage($package, $composer, $phpVersion, $showMinorOnly);
                            if ($latestPackage === false) {
                                continue;
                            }

                            $latestPackages[$package->getPrettyName()] = $latestPackage;
                        }
                    }
                }

                $writePath = !$input->getOption('name-only') && $input->getOption('path');
                $writeVersion = !$input->getOption('name-only') && !$input->getOption('path') && $showVersion;
                $writeLatest = $writeVersion && $showLatest;
                $writeDescription = !$input->getOption('name-only') && !$input->getOption('path');

                $hasOutdatedPackages = false;

                $viewData[$type] = array();
                foreach ($packages[$type] as $package) {
                    $packageViewData = array();
                    if (is_object($package)) {
                        $latestPackage = null;
                        if ($showLatest && isset($latestPackages[$package->getPrettyName()])) {
                            $latestPackage = $latestPackages[$package->getPrettyName()];
                        }

                        // Determine if Composer is checking outdated dependencies and if current package should trigger non-default exit code
                        $packageIsUpToDate = $latestPackage && $latestPackage->getFullPrettyVersion() === $package->getFullPrettyVersion() && !$latestPackage->isAbandoned();
                        $packageIsIgnored = \in_array($package->getPrettyName(), $ignoredPackages, true);
                        if ($input->getOption('outdated') && ($packageIsUpToDate || $packageIsIgnored)) {
                            continue;
                        } elseif ($input->getOption('outdated') || $input->getOption('strict')) {
                            $hasOutdatedPackages = true;
                        }

                        $packageViewData['name'] = $package->getPrettyName();
                        $nameLength = max($nameLength, strlen($package->getPrettyName()));
                        if ($writeVersion) {
                            $packageViewData['version'] = $package->getFullPrettyVersion();
                            $versionLength = max($versionLength, strlen($package->getFullPrettyVersion()));
                        }
                        if ($writeLatest && $latestPackage) {
                            $packageViewData['latest'] = $latestPackage->getFullPrettyVersion();
                            $packageViewData['latest-status'] = $this->getUpdateStatus($latestPackage, $package);
                            $latestLength = max($latestLength, strlen($latestPackage->getFullPrettyVersion()));
                        }
                        if ($writeDescription) {
                            $packageViewData['description'] = $package->getDescription();
                        }
                        if ($writePath) {
                            $packageViewData['path'] = strtok(realpath($composer->getInstallationManager()->getInstallPath($package)), "\r\n");
                        }

                        if ($latestPackage && $latestPackage->isAbandoned()) {
                            $replacement = is_string($latestPackage->getReplacementPackage())
                                ? 'Use ' . $latestPackage->getReplacementPackage() . ' instead'
                                : 'No replacement was suggested';
                            $packageWarning = sprintf(
                                'Package %s is abandoned, you should avoid using it. %s.',
                                $package->getPrettyName(),
                                $replacement
                            );
                            $packageViewData['warning'] = $packageWarning;
                        }
                    } else {
                        $packageViewData['name'] = $package;
                        $nameLength = max($nameLength, strlen($package));
                    }
                    $viewData[$type][] = $packageViewData;
                }
                $viewMetaData[$type] = array(
                    'nameLength' => $nameLength,
                    'versionLength' => $versionLength,
                    'latestLength' => $latestLength,
                );
                if ($input->getOption('strict') && $hasOutdatedPackages) {
                    $exitCode = 1;
                    break;
                }
            }
        }

        if ('json' === $format) {
            $io->write(JsonFile::encode($viewData));
        } else {
            foreach ($viewData as $type => $packages) {
                $nameLength = $viewMetaData[$type]['nameLength'];
                $versionLength = $viewMetaData[$type]['versionLength'];
                $latestLength = $viewMetaData[$type]['latestLength'];

                $writeVersion = $nameLength + $versionLength + 3 <= $width;
                $writeLatest = $nameLength + $versionLength + $latestLength + 3 <= $width;
                $writeDescription = $nameLength + $versionLength + $latestLength + 24 <= $width;

                if ($writeLatest && !$io->isDecorated()) {
                    $latestLength += 2;
                }

                if ($showAllTypes) {
                    if ('available' === $type) {
                        $io->write('<comment>' . $type . '</comment>:');
                    } else {
                        $io->write('<info>' . $type . '</info>:');
                    }
                }

                foreach ($packages as $package) {
                    $io->write($indent . str_pad($package['name'], $nameLength, ' '), false);
                    if (isset($package['version']) && $writeVersion) {
                        $io->write(' ' . str_pad($package['version'], $versionLength, ' '), false);
                    }
                    if (isset($package['latest']) && $writeLatest) {
                        $latestVersion = $package['latest'];
                        $updateStatus = $package['latest-status'];
                        $style = $this->updateStatusToVersionStyle($updateStatus);
                        if (!$io->isDecorated()) {
                            $latestVersion = str_replace(array('up-to-date', 'semver-safe-update', 'update-possible'), array('=', '!', '~'), $updateStatus) . ' ' . $latestVersion;
                        }
                        $io->write(' <' . $style . '>' . str_pad($latestVersion, $latestLength, ' ') . '</' . $style . '>', false);
                    }
                    if (isset($package['description']) && $writeDescription) {
                        $description = strtok($package['description'], "\r\n");
                        $remaining = $width - $nameLength - $versionLength - 4;
                        if ($writeLatest) {
                            $remaining -= $latestLength;
                        }
                        if (strlen($description) > $remaining) {
                            $description = substr($description, 0, $remaining - 3) . '...';
                        }
                        $io->write(' ' . $description, false);
                    }
                    if (isset($package['path'])) {
                        $io->write(' ' . $package['path'], false);
                    }
                    $io->write('');
                    if (isset($package['warning'])) {
                        $io->write('<warning>' . $package['warning'] . '</warning>');
                    }
                }

                if ($showAllTypes) {
                    $io->write('');
                }
            }
        }

        return $exitCode;
    }

    protected function getRootRequires()
    {
        $rootPackage = $this->getComposer()->getPackage();

        return array_map(
            'strtolower',
            array_keys(array_merge($rootPackage->getRequires(), $rootPackage->getDevRequires()))
        );
    }

    protected function getVersionStyle(PackageInterface $latestPackage, PackageInterface $package)
    {
        return $this->updateStatusToVersionStyle($this->getUpdateStatus($latestPackage, $package));
    }

    /**
     * finds a package by name and version if provided
     *
     * @param  RepositoryInterface        $installedRepo
     * @param  RepositoryInterface        $repos
     * @param  string                     $name
     * @param  ConstraintInterface|string $version
     * @throws \InvalidArgumentException
     * @return array                      array(CompletePackageInterface, array of versions)
     */
    protected function getPackage(RepositoryInterface $installedRepo, RepositoryInterface $repos, $name, $version = null)
    {
        $name = strtolower($name);
        $constraint = is_string($version) ? $this->versionParser->parseConstraints($version) : $version;

        $policy = new DefaultPolicy();
        $pool = new Pool('dev');
        $pool->addRepository($repos);

        $matchedPackage = null;
        $versions = array();
        $matches = $pool->whatProvides($name, $constraint);
        foreach ($matches as $index => $package) {
            // skip providers/replacers
            if ($package->getName() !== $name) {
                unset($matches[$index]);
                continue;
            }

            // select an exact match if it is in the installed repo and no specific version was required
            if (null === $version && $installedRepo->hasPackage($package)) {
                $matchedPackage = $package;
            }

            $versions[$package->getPrettyVersion()] = $package->getVersion();
            $matches[$index] = $package->getId();
        }

        // select preferred package according to policy rules
        if (!$matchedPackage && $matches && $preferred = $policy->selectPreferredPackages($pool, array(), $matches)) {
            $matchedPackage = $pool->literalToPackage($preferred[0]);
        }

        return array($matchedPackage, $versions);
    }

    /**
     * Prints package info.
     *
     * @param CompletePackageInterface $package
     * @param array                    $versions
     * @param RepositoryInterface      $installedRepo
     * @param PackageInterface|null    $latestPackage
     */
    protected function printPackageInfo(CompletePackageInterface $package, array $versions, RepositoryInterface $installedRepo, PackageInterface $latestPackage = null)
    {
        $io = $this->getIO();

        $this->printMeta($package, $versions, $installedRepo, $latestPackage ?: null);
        $this->printLinks($package, 'requires');
        $this->printLinks($package, 'devRequires', 'requires (dev)');

        if ($package->getSuggests()) {
            $io->write("\n<info>suggests</info>");
            foreach ($package->getSuggests() as $suggested => $reason) {
                $io->write($suggested . ' <comment>' . $reason . '</comment>');
            }
        }

        $this->printLinks($package, 'provides');
        $this->printLinks($package, 'conflicts');
        $this->printLinks($package, 'replaces');
    }

    /**
     * Prints package metadata.
     *
     * @param CompletePackageInterface $package
     * @param array                    $versions
     * @param RepositoryInterface      $installedRepo
     * @param PackageInterface|null    $latestPackage
     */
    protected function printMeta(CompletePackageInterface $package, array $versions, RepositoryInterface $installedRepo, PackageInterface $latestPackage = null)
    {
        $io = $this->getIO();
        $io->write('<info>name</info>     : ' . $package->getPrettyName());
        $io->write('<info>descrip.</info> : ' . $package->getDescription());
        $io->write('<info>keywords</info> : ' . implode(', ', $package->getKeywords() ?: array()));
        $this->printVersions($package, $versions, $installedRepo);
        if ($latestPackage) {
            $style = $this->getVersionStyle($latestPackage, $package);
            $io->write('<info>latest</info>   : <'.$style.'>' . $latestPackage->getPrettyVersion() . '</'.$style.'>');
        } else {
            $latestPackage = $package;
        }
        $io->write('<info>type</info>     : ' . $package->getType());
        $this->printLicenses($package);
        $io->write('<info>homepage</info> : ' . $package->getHomepage());
        $io->write('<info>source</info>   : ' . sprintf('[%s] <comment>%s</comment> %s', $package->getSourceType(), $package->getSourceUrl(), $package->getSourceReference()));
        $io->write('<info>dist</info>     : ' . sprintf('[%s] <comment>%s</comment> %s', $package->getDistType(), $package->getDistUrl(), $package->getDistReference()));
        if ($installedRepo->hasPackage($package)) {
            $io->write('<info>path</info>     : ' . sprintf('%s', realpath($this->getComposer()->getInstallationManager()->getInstallPath($package))));
        }
        $io->write('<info>names</info>    : ' . implode(', ', $package->getNames()));

        if ($latestPackage->isAbandoned()) {
            $replacement = ($latestPackage->getReplacementPackage() !== null)
                ? ' The author suggests using the ' . $latestPackage->getReplacementPackage(). ' package instead.'
                : null;

            $io->writeError(
                sprintf('<warning>Attention: This package is abandoned and no longer maintained.%s</warning>', $replacement)
            );
        }

        if ($package->getSupport()) {
            $io->write("\n<info>support</info>");
            foreach ($package->getSupport() as $type => $value) {
                $io->write('<comment>' . $type . '</comment> : '.$value);
            }
        }

        if ($package->getAutoload()) {
            $io->write("\n<info>autoload</info>");
            foreach ($package->getAutoload() as $type => $autoloads) {
                $io->write('<comment>' . $type . '</comment>');

                if ($type === 'psr-0') {
                    foreach ($autoloads as $name => $path) {
                        $io->write(($name ?: '*') . ' => ' . (is_array($path) ? implode(', ', $path) : ($path ?: '.')));
                    }
                } elseif ($type === 'psr-4') {
                    foreach ($autoloads as $name => $path) {
                        $io->write(($name ?: '*') . ' => ' . (is_array($path) ? implode(', ', $path) : ($path ?: '.')));
                    }
                } elseif ($type === 'classmap') {
                    $io->write(implode(', ', $autoloads));
                }
            }
            if ($package->getIncludePaths()) {
                $io->write('<comment>include-path</comment>');
                $io->write(implode(', ', $package->getIncludePaths()));
            }
        }
    }

    /**
     * Prints all available versions of this package and highlights the installed one if any.
     *
     * @param CompletePackageInterface $package
     * @param array                    $versions
     * @param RepositoryInterface      $installedRepo
     */
    protected function printVersions(CompletePackageInterface $package, array $versions, RepositoryInterface $installedRepo)
    {
        uasort($versions, 'version_compare');
        $versions = array_keys(array_reverse($versions));

        // highlight installed version
        if ($installedRepo->hasPackage($package)) {
            $installedVersion = $package->getPrettyVersion();
            $key = array_search($installedVersion, $versions);
            if (false !== $key) {
                $versions[$key] = '<info>* ' . $installedVersion . '</info>';
            }
        }

        $versions = implode(', ', $versions);

        $this->getIO()->write('<info>versions</info> : ' . $versions);
    }

    /**
     * print link objects
     *
     * @param CompletePackageInterface $package
     * @param string                   $linkType
     * @param string                   $title
     */
    protected function printLinks(CompletePackageInterface $package, $linkType, $title = null)
    {
        $title = $title ?: $linkType;
        $io = $this->getIO();
        if ($links = $package->{'get'.ucfirst($linkType)}()) {
            $io->write("\n<info>" . $title . "</info>");

            foreach ($links as $link) {
                $io->write($link->getTarget() . ' <comment>' . $link->getPrettyConstraint() . '</comment>');
            }
        }
    }

    /**
     * Prints the licenses of a package with metadata
     *
     * @param CompletePackageInterface $package
     */
    protected function printLicenses(CompletePackageInterface $package)
    {
        $spdxLicenses = new SpdxLicenses();

        $licenses = $package->getLicense();
        $io = $this->getIO();

        foreach ($licenses as $licenseId) {
            $license = $spdxLicenses->getLicenseByIdentifier($licenseId); // keys: 0 fullname, 1 osi, 2 url

            if (!$license) {
                $out = $licenseId;
            } else {
                // is license OSI approved?
                if ($license[1] === true) {
                    $out = sprintf('%s (%s) (OSI approved) %s', $license[0], $licenseId, $license[2]);
                } else {
                    $out = sprintf('%s (%s) %s', $license[0], $licenseId, $license[2]);
                }
            }

            $io->write('<info>license</info>  : ' . $out);
        }
    }

    /**
     * Prints package info in JSON format.
     *
     * @param CompletePackageInterface $package
     * @param array                    $versions
     * @param RepositoryInterface      $installedRepo
     * @param PackageInterface|null    $latestPackage
     */
    protected function printPackageInfoAsJson(CompletePackageInterface $package, array $versions, RepositoryInterface $installedRepo, PackageInterface $latestPackage = null)
    {
        $json = array(
            'name' => $package->getPrettyName(),
            'description' => $package->getDescription(),
            'keywords' => $package->getKeywords() ?: array(),
            'type' => $package->getType(),
            'homepage' => $package->getHomepage(),
            'names' => $package->getNames()
        );

        $json = $this->appendVersions($json, $versions);
        $json = $this->appendLicenses($json, $package);

        if ($latestPackage) {
            $json['latest'] = $latestPackage->getPrettyVersion();
        } else {
            $latestPackage = $package;
        }

        if ($package->getSourceType()) {
            $json['source'] = array(
                'type' => $package->getSourceType(),
                'url' => $package->getSourceUrl(),
                'reference' => $package->getSourceReference()
            );
        }

        if ($package->getDistType()) {
            $json['dist'] = array(
                'type' => $package->getDistType(),
                'url' => $package->getDistUrl(),
                'reference' => $package->getDistReference()
            );
        }

        if ($installedRepo->hasPackage($package)) {
            $json['path'] = realpath($this->getComposer()->getInstallationManager()->getInstallPath($package));
            if ($json['path'] === false) {
                unset($json['path']);
            }
        }

        if ($latestPackage->isAbandoned()) {
            $json['replacement'] = $latestPackage->getReplacementPackage();
        }

        if ($package->getSuggests()) {
            $json['suggests'] = $package->getSuggests();
        }

        if ($package->getSupport()) {
            $json['support'] = $package->getSupport();
        }

        $json = $this->appendAutoload($json, $package);

        if ($package->getIncludePaths()) {
            $json['include_path'] = $package->getIncludePaths();
        }

        $json = $this->appendLinks($json, $package);

        $this->getIO()->write(JsonFile::encode($json));
    }

    private function appendVersions($json, array $versions)
    {
        uasort($versions, 'version_compare');
        $versions = array_keys(array_reverse($versions));
        $json['versions'] = $versions;

        return $json;
    }

    private function appendLicenses($json, CompletePackageInterface $package)
    {
        if ($licenses = $package->getLicense()) {
            $spdxLicenses = new SpdxLicenses();

            $json['licenses'] = array_map(function ($licenseId) use ($spdxLicenses) {
                $license = $spdxLicenses->getLicenseByIdentifier($licenseId); // keys: 0 fullname, 1 osi, 2 url

                if (!$license) {
                    return $licenseId;
                }

                return array(
                    'name' => $license[0],
                    'osi' => $licenseId,
                    'url' => $license[2]
                );
            }, $licenses);
        }

        return $json;
    }

    private function appendAutoload($json, CompletePackageInterface $package)
    {
        if ($package->getAutoload()) {
            $autoload = array();

            foreach ($package->getAutoload() as $type => $autoloads) {
                if ($type === 'psr-0' || $type === 'psr-4') {
                    $psr = array();

                    foreach ($autoloads as $name => $path) {
                        if (!$path) {
                            $path = '.';
                        }

                        $psr[$name ?: '*'] = $path;
                    }

                    $autoload[$type] = $psr;
                } elseif ($type === 'classmap') {
                    $autoload['classmap'] = $autoloads;
                }
            }

            $json['autoload'] = $autoload;
        }

        return $json;
    }

    private function appendLinks($json, CompletePackageInterface $package)
    {
        foreach (array('requires', 'devRequires', 'provides', 'conflicts', 'replaces') as $linkType) {
            $json = $this->appendLink($json, $package, $linkType);
        }

        return $json;
    }

    private function appendLink($json, CompletePackageInterface $package, $linkType)
    {
        $links = $package->{'get' . ucfirst($linkType)}();

        if ($links) {
            $json[$linkType] = array();

            foreach ($links as $link) {
                $json[$linkType][$link->getTarget()] = $link->getPrettyConstraint();
            }
        }

        return $json;
    }

    /**
     * Init styles for tree
     *
     * @param OutputInterface $output
     */
    protected function initStyles(OutputInterface $output)
    {
        $this->colors = array(
            'green',
            'yellow',
            'cyan',
            'magenta',
            'blue',
        );

        foreach ($this->colors as $color) {
            $style = new OutputFormatterStyle($color);
            $output->getFormatter()->setStyle($color, $style);
        }
    }

    /**
     * Display the tree
     *
     * @param array $arrayTree
     */
    protected function displayPackageTree(array $arrayTree)
    {
        $io = $this->getIO();
        foreach ($arrayTree as $package) {
            $io->write(sprintf('<info>%s</info>', $package['name']), false);
            $io->write(' ' . $package['version'], false);
            $io->write(' ' . strtok($package['description'], "\r\n"));

            if (isset($package['requires'])) {
                $requires = $package['requires'];
                $treeBar = '├';
                $j = 0;
                $total = count($requires);
                foreach ($requires as $require) {
                    $requireName = $require['name'];
                    $j++;
                    if ($j === $total) {
                        $treeBar = '└';
                    }
                    $level = 1;
                    $color = $this->colors[$level];
                    $info = sprintf(
                        '%s──<%s>%s</%s> %s',
                        $treeBar,
                        $color,
                        $requireName,
                        $color,
                        $require['version']
                    );
                    $this->writeTreeLine($info);

                    $treeBar = str_replace('└', ' ', $treeBar);
                    $packagesInTree = array($package['name'], $requireName);

                    $this->displayTree($require, $packagesInTree, $treeBar, $level + 1);
                }
            }
        }
    }

    /**
     * Generate the package tree
     *
     * @param  PackageInterface $package
     * @param  RepositoryInterface     $installedRepo
     * @param  RepositoryInterface     $distantRepos
     * @return array
     */
    protected function generatePackageTree(
        PackageInterface $package,
        RepositoryInterface $installedRepo,
        RepositoryInterface $distantRepos
    ) {
        $requires = $package->getRequires();
        ksort($requires);
        $children = array();
        foreach ($requires as $requireName => $require) {
            $packagesInTree = array($package->getName(), $requireName);

            $treeChildDesc = array(
                'name' => $requireName,
                'version' => $require->getPrettyConstraint(),
            );

            $deepChildren = $this->addTree($requireName, $require, $installedRepo, $distantRepos, $packagesInTree);

            if ($deepChildren) {
                $treeChildDesc['requires'] = $deepChildren;
            }

            $children[] = $treeChildDesc;
        }
        $tree = array(
            'name' => $package->getPrettyName(),
            'version' => $package->getPrettyVersion(),
            'description' => $package->getDescription(),
        );

        if ($children) {
            $tree['requires'] = $children;
        }

        return $tree;
    }

    /**
     * Display a package tree
     *
     * @param PackageInterface|string $package
     * @param array                   $packagesInTree
     * @param string                  $previousTreeBar
     * @param int                     $level
     */
    protected function displayTree(
        $package,
        array $packagesInTree,
        $previousTreeBar = '├',
        $level = 1
    ) {
        $previousTreeBar = str_replace('├', '│', $previousTreeBar);
        if (isset($package['requires'])) {
            $requires = $package['requires'];
            $treeBar = $previousTreeBar . '  ├';
            $i = 0;
            $total = count($requires);
            foreach ($requires as $require) {
                $currentTree = $packagesInTree;
                $i++;
                if ($i === $total) {
                    $treeBar = $previousTreeBar . '  └';
                }
                $colorIdent = $level % count($this->colors);
                $color = $this->colors[$colorIdent];

                $circularWarn = in_array(
                    $require['name'],
                    $currentTree,
                    true
                ) ? '(circular dependency aborted here)' : '';
                $info = rtrim(sprintf(
                    '%s──<%s>%s</%s> %s %s',
                    $treeBar,
                    $color,
                    $require['name'],
                    $color,
                    $require['version'],
                    $circularWarn
                ));
                $this->writeTreeLine($info);

                $treeBar = str_replace('└', ' ', $treeBar);

                $currentTree[] = $require['name'];
                $this->displayTree($require, $currentTree, $treeBar, $level + 1);
            }
        }
    }

    /**
     * Display a package tree
     *
     * @param  string                  $name
     * @param  PackageInterface|string $package
     * @param  RepositoryInterface     $installedRepo
     * @param  RepositoryInterface     $distantRepos
     * @param  array                   $packagesInTree
     * @return array
     */
    protected function addTree(
        $name,
        $package,
        RepositoryInterface $installedRepo,
        RepositoryInterface $distantRepos,
        array $packagesInTree
    ) {
        $children = array();
        list($package, $versions) = $this->getPackage(
            $installedRepo,
            $distantRepos,
            $name,
            $package->getPrettyConstraint() === 'self.version' ? $package->getConstraint() : $package->getPrettyConstraint()
        );
        if (is_object($package)) {
            $requires = $package->getRequires();
            ksort($requires);
            foreach ($requires as $requireName => $require) {
                $currentTree = $packagesInTree;

                $treeChildDesc = array(
                    'name' => $requireName,
                    'version' => $require->getPrettyConstraint(),
                );

                if (!in_array($requireName, $currentTree, true)) {
                    $currentTree[] = $requireName;
                    $deepChildren = $this->addTree($requireName, $require, $installedRepo, $distantRepos, $currentTree);
                    if ($deepChildren) {
                        $treeChildDesc['requires'] = $deepChildren;
                    }
                }

                $children[] = $treeChildDesc;
            }
        }

        return $children;
    }

    private function updateStatusToVersionStyle($updateStatus)
    {
        // 'up-to-date' is printed green
        // 'semver-safe-update' is printed red
        // 'update-possible' is printed yellow
        return str_replace(array('up-to-date', 'semver-safe-update', 'update-possible'), array('info', 'highlight', 'comment'), $updateStatus);
    }

    private function getUpdateStatus(PackageInterface $latestPackage, PackageInterface $package)
    {
        if ($latestPackage->getFullPrettyVersion() === $package->getFullPrettyVersion()) {
            return 'up-to-date';
        }

        $constraint = $package->getVersion();
        if (0 !== strpos($constraint, 'dev-')) {
            $constraint = '^'.$constraint;
        }
        if ($latestPackage->getVersion() && Semver::satisfies($latestPackage->getVersion(), $constraint)) {
            // it needs an immediate semver-compliant upgrade
            return 'semver-safe-update';
        }

        // it needs an upgrade but has potential BC breaks so is not urgent
        return 'update-possible';
    }

    private function writeTreeLine($line)
    {
        $io = $this->getIO();
        if (!$io->isDecorated()) {
            $line = str_replace(array('└', '├', '──', '│'), array('`-', '|-', '-', '|'), $line);
        }

        $io->write($line);
    }

    /**
     * Given a package, this finds the latest package matching it
     *
     * @param PackageInterface $package
     * @param Composer         $composer
     * @param string           $phpVersion
     * @param bool             $minorOnly
     *
     * @return PackageInterface|null
     */
    private function findLatestPackage(PackageInterface $package, Composer $composer, $phpVersion, $minorOnly = false)
    {
        // find the latest version allowed in this pool
        $name = $package->getName();
        $versionSelector = new VersionSelector($this->getPool($composer));
        $stability = $composer->getPackage()->getMinimumStability();
        $flags = $composer->getPackage()->getStabilityFlags();
        if (isset($flags[$name])) {
            $stability = array_search($flags[$name], BasePackage::$stabilities, true);
        }

        $bestStability = $stability;
        if ($composer->getPackage()->getPreferStable()) {
            $bestStability = $package->getStability();
        }

        $targetVersion = null;
        if (0 === strpos($package->getVersion(), 'dev-')) {
            $targetVersion = $package->getVersion();
        }

        if ($targetVersion === null && $minorOnly) {
            $targetVersion = '^' . $package->getVersion();
        }

        return $versionSelector->findBestCandidate($name, $targetVersion, $phpVersion, $bestStability);
    }

    private function getPool(Composer $composer)
    {
        if (!$this->pool) {
            $this->pool = new Pool($composer->getPackage()->getMinimumStability(), $composer->getPackage()->getStabilityFlags());
            $this->pool->addRepository(new CompositeRepository($composer->getRepositoryManager()->getRepositories()));
        }

        return $this->pool;
    }
}
