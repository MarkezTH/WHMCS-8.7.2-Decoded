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

namespace Composer\Repository\Vcs;

use Composer\Util\ProcessExecutor;
use Composer\Util\Filesystem;
use Composer\Util\Git as GitUtil;
use Composer\IO\IOInterface;
use Composer\Cache;
use Composer\Config;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class GitDriver extends VcsDriver
{
    protected $cache;
    protected $tags;
    protected $branches;
    protected $rootIdentifier;
    protected $repoDir;
    protected $infoCache = array();

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        if (Filesystem::isLocalPath($this->url)) {
            $this->url = preg_replace('{[\\/]\.git/?$}', '', $this->url);
            $this->repoDir = $this->url;
            $cacheUrl = realpath($this->url);
        } else {
            if (!Cache::isUsable($this->config->get('cache-vcs-dir'))) {
                throw new \RuntimeException('GitDriver requires a usable cache directory, and it looks like you set it to be disabled');
            }

            $this->repoDir = $this->config->get('cache-vcs-dir') . '/' . preg_replace('{[^a-z0-9.]}i', '-', $this->url) . '/';

            GitUtil::cleanEnv();

            $fs = new Filesystem();
            $fs->ensureDirectoryExists(dirname($this->repoDir));

            if (!is_writable(dirname($this->repoDir))) {
                throw new \RuntimeException('Can not clone '.$this->url.' to access package information. The "'.dirname($this->repoDir).'" directory is not writable by the current user.');
            }

            if (preg_match('{^ssh://[^@]+@[^:]+:[^0-9]+}', $this->url)) {
                throw new \InvalidArgumentException('The source URL '.$this->url.' is invalid, ssh URLs should have a port number after ":".'."\n".'Use ssh://git@example.com:22/path or just git@example.com:path if you do not want to provide a password or custom port.');
            }

            $gitUtil = new GitUtil($this->io, $this->config, $this->process, $fs);
            if (!$gitUtil->syncMirror($this->url, $this->repoDir)) {
                $this->io->writeError('<error>Failed to update '.$this->url.', package information from this repository may be outdated</error>');
            }

            $cacheUrl = $this->url;
        }

        $this->getTags();
        $this->getBranches();

        $this->cache = new Cache($this->io, $this->config->get('cache-repo-dir').'/'.preg_replace('{[^a-z0-9.]}i', '-', $cacheUrl));
    }

    /**
     * {@inheritDoc}
     */
    public function getRootIdentifier()
    {
        if (null === $this->rootIdentifier) {
            $this->rootIdentifier = 'master';

            // select currently checked out branch if master is not available
            $this->process->execute('git branch --no-color', $output, $this->repoDir);
            $branches = $this->process->splitLines($output);
            if (!in_array('* master', $branches)) {
                foreach ($branches as $branch) {
                    if ($branch && preg_match('{^\* +(\S+)}', $branch, $match)) {
                        $this->rootIdentifier = $match[1];
                        break;
                    }
                }
            }
        }

        return $this->rootIdentifier;
    }

    /**
     * {@inheritDoc}
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * {@inheritDoc}
     */
    public function getSource($identifier)
    {
        return array('type' => 'git', 'url' => $this->getUrl(), 'reference' => $identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function getDist($identifier)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileContent($file, $identifier)
    {
        $resource = sprintf('%s:%s', ProcessExecutor::escape($identifier), ProcessExecutor::escape($file));
        $this->process->execute(sprintf('git show %s', $resource), $content, $this->repoDir);

        if (!trim($content)) {
            return null;
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    public function getChangeDate($identifier)
    {
        $this->process->execute(sprintf(
            'git -c log.showSignature=false log -1 --format=%%at %s',
            ProcessExecutor::escape($identifier)
        ), $output, $this->repoDir);

        return new \DateTime('@'.trim($output), new \DateTimeZone('UTC'));
    }

    /**
     * {@inheritDoc}
     */
    public function getTags()
    {
        if (null === $this->tags) {
            $this->tags = array();

            $this->process->execute('git show-ref --tags --dereference', $output, $this->repoDir);
            foreach ($output = $this->process->splitLines($output) as $tag) {
                if ($tag && preg_match('{^([a-f0-9]{40}) refs/tags/(\S+?)(\^\{\})?$}', $tag, $match)) {
                    $this->tags[$match[2]] = $match[1];
                }
            }
        }

        return $this->tags;
    }

    /**
     * {@inheritDoc}
     */
    public function getBranches()
    {
        if (null === $this->branches) {
            $branches = array();

            $this->process->execute('git branch --no-color --no-abbrev -v', $output, $this->repoDir);
            foreach ($this->process->splitLines($output) as $branch) {
                if ($branch && !preg_match('{^ *[^/]+/HEAD }', $branch)) {
                    if (preg_match('{^(?:\* )? *(\S+) *([a-f0-9]+)(?: .*)?$}', $branch, $match)) {
                        $branches[$match[1]] = $match[2];
                    }
                }
            }

            $this->branches = $branches;
        }

        return $this->branches;
    }

    /**
     * {@inheritDoc}
     */
    public static function supports(IOInterface $io, Config $config, $url, $deep = false)
    {
        if (preg_match('#(^git://|\.git/?$|git(?:olite)?@|//git\.|//github.com/)#i', $url)) {
            return true;
        }

        // local filesystem
        if (Filesystem::isLocalPath($url)) {
            $url = Filesystem::getPlatformPath($url);
            if (!is_dir($url)) {
                return false;
            }

            $process = new ProcessExecutor($io);
            // check whether there is a git repo in that path
            if ($process->execute('git tag', $output, $url) === 0) {
                return true;
            }
        }

        if (!$deep) {
            return false;
        }

        $process = new ProcessExecutor($io);

        return $process->execute('git ls-remote --heads ' . ProcessExecutor::escape($url), $output) === 0;
    }
}
