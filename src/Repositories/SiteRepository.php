<?php

namespace App\Repositories;

use Exception;
use Nette\Utils\FileSystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class SiteRepository
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function ensureValid(string $siteName): bool
    {
        $dir = $this->projectDir . '/content/src/' . $siteName;

        if (!is_dir($dir . '/pages')) {
            return $this->create($siteName);
        }
        return true;
    }

    public function create(string $siteName): bool
    {
        $dir = $this->projectDir . '/content/src/' . $siteName . '/pages';

        if (!is_dir($dir)) {
            $fs = new FileSystem();

            try {
                $fs->createDir($dir);
            } catch (Exception $e) {
                return false;
            }
        }
        return is_dir($dir);
    }

    public function remove(string $siteName): bool
    {
        $dir = $this->projectDir . '/content/src/' . $siteName;

        if (is_dir($dir)) {
            $fs = new FileSystem();

            try {
                $fs->delete($dir);
            } catch (Exception $e) {
                return false;
            }
        }
        return ! is_dir($dir);
    }

    public function getSites(): array
    {
        $siteDir = $this->projectDir . '/content/src';
        if (!is_dir($siteDir)) {
            mkdir($siteDir, recursive: true);
        }

        $finder = new Finder();
        $finder->depth(0)->sortByName(true)->directories()->in($siteDir);

        $sites = [];
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                if ($file->isDir()) {
                    $sites[] = $this->parseSitePath($file);
                }
            }
        }
        return $sites;
    }

    // public function getSite(string $path = null): string
    // {
    //     $siteDir = $this->projectDir . '/content/' . $path;

    //     return $path;
    //     // if(is_dir($siteDir)) {
    //     //     return $this->parseSitePath($path);
    //     // }
    //     return null;
    // }

    private function parseSitePath(SplFileInfo $file, bool $includeMarkdown = false): ?array
    {
        $siteDir = $this->projectDir . '/content/src';

        if (is_dir($file->getRealPath())) {
            // Generate default slug/title (overridable by the variables in the page yaml)
            $path = substr($file->getPathname(), strlen($siteDir) + 1);
            $parts = explode('/', $path);
            $defaultSlug = array_pop($parts);
            if ($defaultSlug === 'index' && isset($parts[0])) {
                $defaultSlug = array_pop($parts);
            }

            $site = [
                'path' => $path,
                'filePath' => $file->getRealPath(),
                'slug' => $defaultSlug,
            ];

            return $site;

            // $markdown = file_get_contents($file->getRealPath());
            // $parsedPage = $this->contentParser->parse($markdown);
            // $pageVariables = $parsedPage['variables'];

            // // Generate default slug/title (overridable by the variables in the page yaml)
            // $path = substr($file->getPathname(), strlen($this->getContentPagesDirectory($site)) + 1, -3);
            // $parts = explode('/', $path);
            // $defaultSlug = array_pop($parts);
            // if($defaultSlug === 'index' && isset($parts[0])) {
            //     $defaultSlug = array_pop($parts);
            // }

            // $page = array_merge([
            //     'path' => $path,
            //     'filePath' => $file->getRealPath(),
            //     'createdAt' => null,
            //     'updatedAt' => null,
            //     'slug' => $defaultSlug,

            //     //'variables' => $result['variables'],
            //     //'content' => $result['content'],
            // ], $pageVariables);

            // $page['createdAt'] = $this->getValidDateTime($file, $page['createdAt']);
            // $page['updatedAt'] = $this->getValidDateTime($file, $page['updatedAt']);

            // if(!isset($page['title'])) {
            //     $page['title'] = ucfirst($page['slug']);
            // }

            // if($includeMarkdown) {
            //     $page['__markdown'] = $markdown;
            // }
            // return $page;
        }
        return null;
    }
}
