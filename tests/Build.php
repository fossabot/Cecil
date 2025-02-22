<?php
/*
 * Copyright (c) Arnaud Ligny <arnaud@ligny.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cecil\Test;

use Cecil\Builder;
use Symfony\Component\Filesystem\Filesystem;

class Build extends \PHPUnit\Framework\TestCase
{
    protected $wsSourceDir;
    protected $wsDestinationDir;
    const DEBUG = false;

    public function setUp()
    {
        $this->wsSourceDir = __DIR__.'/fixtures/website';
        $this->wsDestinationDir = $this->wsSourceDir;
    }

    public function tearDown()
    {
        $fs = new Filesystem();
        if (!self::DEBUG) {
            $fs->remove($this->wsDestinationDir.'/_site');
            $fs->remove(__DIR__.'/../_cache');
        }
    }

    public function testBuid()
    {
        putenv('CECIL_DESCRIPTION=Description from environment variable');
        Builder::create(
            [
                'title'     => 'Cecil test',
                'language'  => 'fr-fr',
                'languages' => [
                    'fr-fr' => [
                        'name'   => 'Français',
                        'locale' => 'fr_FR',
                    ],
                ],
                'menu'   => [
                    'main' => [
                        'home' => [
                            'id'   => 'index',
                            'name' => 'Da home! \o/',
                        ],
                        'about' => [
                            'enabled' => false,
                        ],
                        'aligny' => [
                            'name'   => 'The author',
                            'url'    => 'https://arnaudligny.fr',
                            'weight' => 9999,
                        ],
                    ],
                ],
                'pagination' => [
                    'enabled'  => true,
                ],
                'taxonomies' => [
                    'tests' => 'disabled',
                ],
                'googleanalytics' => 'UA-XXXXX',
                'virtualpages'    => [
                    'sitemap' => [
                        'published' => true,
                    ],
                    'rss' => [
                        'published' => false,
                    ],
                ],
                'output' => [
                    'pagetypeformats' => [
                        'page'       => ['html', 'json'],
                        'homepage'   => ['html', 'atom', 'rss', 'json'],
                        'section'    => ['html', 'atom', 'rss', 'json'],
                        'vocabulary' => ['html'],
                        'term'       => ['html', 'atom', 'rss'],
                    ],
                ],
                'theme'  => [
                    'a-theme',
                    'hyde',
                ],
                'static' => [
                    'exclude' => [
                        'test.txt',
                    ],
                ],
                'generators' => [
                    99  => 'Cecil\Generator\Test',
                    //100 => 'Cecil\Generator\TitleReplace',
                ],
                'debug' => true,
            ]
        )->setSourceDir($this->wsSourceDir)
        ->setDestinationDir($this->wsDestinationDir)
        ->build([
            'verbosity' => Builder::VERBOSITY_DEBUG,
            'drafts'    => false,
            'dry-run'   => false,
        ]);

        self::assertTrue(true);
    }
}
