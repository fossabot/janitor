<?php
namespace Janitor;

use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;
use Illuminate\Routing\Route;
use Janitor\Services\Tokenizers\BladeTokenizer;
use Janitor\Services\Tokenizers\DefaultTokenizer;
use Janitor\Services\Tokenizers\JsonTokenizer;
use Janitor\Services\Tokenizers\PhpTokenizer;
use Janitor\Services\Tokenizers\TwigTokenizer;
use Janitor\Services\Tokenizers\XmlTokenizer;
use Janitor\Services\Tokenizers\YamlTokenizer;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The user's codebase.
 *
 * @author Maxime Fabre <ehtnam6@gmail.com>
 */
class Codebase
{
    /**
     * The files that are part of the codebase.
     *
     * @type SplFileInfo[]
     */
    protected $files = [];

    /**
     * @type Route[]
     */
    protected $routes;

    /**
     * @type \Illuminate\Cache\CacheManager
     */
    protected $cache;

    /**
     * The files to ignore.
     *
     * @type string[]
     */
    protected $ignored;

    /**
     * Serialized version of the codebase.
     *
     * @type string[]
     */
    protected $tokenized;

    /**
     * Build a new codebase.
     *
     * @param string|null $folder  Where the codebase resides
     * @param array       $ignored
     */
    public function __construct($folder = null, $ignored = [])
    {
        $finder = new Finder();
        $files  = $finder
            ->files()
            ->name('/\.(php|twig|json|xml|yml|yaml|md)$/')
            ->in($folder);

        $this->ignored = $ignored;
        $this->files   = iterator_to_array($files);
        $this->cache   = new Repository(new NullStore());
    }

    /**
     * @return \Symfony\Component\Finder\SplFileInfo[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// OPTIONS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * @return Route[]
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @param Route[] $routes
     */
    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    /**
     * @return \Illuminate\Cache\CacheManager
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param \Illuminate\Cache\CacheManager $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @return string[]
     */
    public function getIgnored()
    {
        return $this->ignored;
    }

    /**
     * @param string[] $ignored
     */
    public function setIgnored($ignored)
    {
        $this->ignored = $ignored;
    }

    //////////////////////////////////////////////////////////////////////
    //////////////////////////// TOKENIZATION ////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Get a serialized version of the codebase.
     *
     * @return string[][]
     */
    public function getTokenized()
    {
        if (!$this->tokenized) {
            foreach ($this->files as $key => $file) {
                $this->tokenized[$file->getBasename()] = $this->extractStringTokens($file);
            }
        }

        return $this->tokenized;
    }

    /**
     * Extract all strings from a given file.
     *
     * @param SplFileInfo $file
     *
     * @return string[]
     */
    protected function extractStringTokens(SplFileInfo $file)
    {
        // Fetch tokens from cache if available
        $hash = 'janitor.tokens.'.md5($file->getPathname()).'-'.$file->getMTime();

        return $this->cache->rememberForever($hash, function () use ($file) {
            $contents = $file->getContents();

            // See if we have an available Tokenizer
            // and use it to extract the contents
            switch ($file->getExtension()) {
                case 'php':
                    $tokenizer = strpos($file->getBasename(), 'blade.php') !== false
                        ? new BladeTokenizer()
                        : new PhpTokenizer();
                    break;

                case 'twig':
                    $tokenizer = new TwigTokenizer();
                    break;

                case 'json':
                    $tokenizer = new JsonTokenizer();
                    break;

                case 'yml':
                case 'yaml':
                    $tokenizer = new YamlTokenizer();
                    break;

                case 'xml':
                    $tokenizer = new XmlTokenizer();
                    break;

                default:
                    $tokenizer = new DefaultTokenizer();
                    break;
            }

            return $tokenizer->tokenize($contents);
        });
    }
}
