<?php
/*
 * This file is part of the Cecil/Cecil package.
 *
 * Copyright (c) Arnaud Ligny <arnaud@ligny.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cecil\Command;

use Cecil\Util\Plateform;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Yosymfony\ResourceWatcher\Crc32ContentHash;
use Yosymfony\ResourceWatcher\ResourceCacheMemory;
use Yosymfony\ResourceWatcher\ResourceWatcher;

/**
 * Class Serve.
 */
class Serve extends AbstractCommand
{
    /**
     * @var string
     */
    public static $tmpDir = '.cecil';
    /**
     * @var bool
     */
    protected $open;
    /**
     * @var bool
     */
    protected $nowatcher;
    /**
     * @var string
     */
    protected $host;
    /**
     * @var string
     */
    protected $port;

    /**
     * {@inheritdoc}
     */
    public function processCommand()
    {
        $this->open = $this->getRoute()->getMatchedParam('open', false);
        $this->nowatcher = $this->getRoute()->getMatchedParam('no-watcher', false);
        $this->host = $this->getRoute()->getMatchedParam('host', 'localhost');
        $this->port = $this->getRoute()->getMatchedParam('port', '8000');

        $this->setUpServer();
        $command = sprintf(
            'php -S %s:%d -t %s %s',
            $this->host,
            $this->port,
            $this->getPath().'/'.$this->getBuilder()->getConfig()->get('output.dir'),
            sprintf('%s/%s/router.php', $this->getPath(), self::$tmpDir)
        );
        $process = new Process($command);

        // (re)build before serve
        $callable = new Build();
        $callable($this->getRoute(), $this->getConsole());

        // handle process
        if (!$process->isStarted()) {
            // write changes cache
            $finder = new Finder();
            $finder->files()
                ->name('*.md')
                ->name('*.twig')
                ->name('*.yml')
                ->name('*.css')
                ->name('*.scss')
                ->name('*.js')
                ->in($this->getPath())
                ->exclude($this->getBuilder()->getConfig()->get('output.dir'));
            if (!$this->nowatcher) {
                $hashContent = new Crc32ContentHash();
                $resourceCache = new ResourceCacheMemory();
                $resourceWatcher = new ResourceWatcher($resourceCache, $finder, $hashContent);
                $resourceWatcher->initialize();
            }
            // start server
            try {
                $this->wlAnnonce(sprintf('Starting server (http://%s:%d)...', $this->host, $this->port));
                $process->start();
                if ($this->open) {
                    Plateform::openBrowser(sprintf('http://%s:%s', $this->host, $this->port));
                }
                while ($process->isRunning()) {
                    if (!$this->nowatcher) {
                        $result = $resourceWatcher->findChanges();
                        if ($result->hasChanges()) {
                            // re-build
                            $this->wlAlert('Changes detected!');
                            $callable($this->getRoute(), $this->getConsole());
                        }
                        usleep(1000000); // wait 1s
                    }
                }
            } catch (ProcessFailedException $e) {
                $this->tearDownServer();

                throw new \Exception(sprintf($e->getMessage()));
            }
        }
    }

    public function setUpServer()
    {
        try {
            $root = __DIR__.'/../../';
            if (Plateform::isPhar()) {
                $root = Plateform::getPharPath().'/';
            }
            // copy router
            $this->fs->copy(
                $root.'res/server/router.php',
                $this->getPath().'/'.self::$tmpDir.'/router.php',
                true
            );
            // copy livereload JS
            if (!$this->nowatcher) {
                $this->fs->copy(
                    $root.'res/server/livereload.js',
                    $this->getPath().'/'.self::$tmpDir.'/livereload.js',
                    true
                );
            }
            // copy baseurl text file
            $this->fs->dumpFile(
                $this->getPath().'/'.self::$tmpDir.'/baseurl',
                sprintf(
                    '%s;%s',
                    $this->getBuilder()->getConfig()->get('baseurl'),
                    sprintf('http://%s:%s/', $this->host, $this->port)
                )
            );
        } catch (IOExceptionInterface $e) {
            throw new \Exception(sprintf('An error occurred while copying file at "%s"', $e->getPath()));
        }
        if (!is_file(sprintf('%s/%s/router.php', $this->getPath(), self::$tmpDir))) {
            throw new \Exception(sprintf('Router not found: "./%s/router.php"', self::$tmpDir));
        }
    }

    public function tearDownServer()
    {
        try {
            $this->fs->remove($this->getPath().'/'.self::$tmpDir);
        } catch (IOExceptionInterface $e) {
            throw new \Exception(sprintf($e->getMessage()));
        }
    }
}
