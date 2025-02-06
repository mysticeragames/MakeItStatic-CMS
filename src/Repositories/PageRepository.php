<?php

namespace App\Repositories;

use App\Services\ContentParser;
use Exception;
use Nette\Utils\FileSystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class PageRepository
{
    public const string EDIT_PATH_SUFFIX = '.__EDITING_CONCEPT__.md';

    private string $projectDir;
    //private string $contentPagesDirectory;
    private ContentParser $contentParser;

    public function __construct(string $projectDir, ContentParser $contentParser)
    {
        $this->projectDir = $projectDir;
        //$directory = $this->getParameter('kernel.project_dir') . '/content/pages';
        //$this->contentPagesDirectory = $this->projectDir . '/content/pages';
        $this->contentParser = $contentParser;
    }

    private function getContentPagesDirectory(string $site): string
    {
        return "$this->projectDir/content/src/$site/pages";
    }

    public function create(string $site, ?string $path = null): bool
    {
        if (empty($path)) {
            $path = 'index';
        }

        $title = $path;
        $title = explode('/', $title);
        $title = array_pop($title);
        $title = ucfirst($title);

        $now = date('Y-m-d H:i:s');
        $content = <<<EOD
---
createdAt: $now
updatedAt: $now
title: $title
---

# $title

EOD;

        $filePath = $this->getContentPagesDirectory($site) . '/' . $path . '.md';

        if (!is_file($filePath)) {
            $fs = new FileSystem();

            try {
                //$fs->createDir(dirname($filePath));
                $fs->write($filePath, $content);
            } catch (Exception $e) {
                return false;
            }
        }
        return is_file($filePath);
    }

    public function remove(string $siteName, ?string $path = null): bool
    {
        if (empty($siteName)) {
            return false;
        }

        if (empty($path)) {
            $path = 'index';
        }
        $file = $this->projectDir . '/content/src/' . $siteName . '/pages/' . $path . '.md';
        $dir = dirname($file);

        if (is_file($file)) {
            $fs = new FileSystem();
            $fs->delete($file);
        }

        // If directory is empty, remove dir
        $finder = new Finder();
        $finder->files()->in($dir);

        if (!$finder->hasResults()) {
            $fs = new FileSystem();
            $fs->delete($dir);
        }
        return true;
    }

    public function getPages(string $site, ?string $path = null): array
    {
        $files = [];

        $finder = new Finder();
        $finder->files()->sortByName(true)->in($this->getContentPagesDirectory($site));

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                if (
                    $file->isFile() &&
                    strtolower($file->getExtension()) === 'md' &&
                    !str_ends_with($file->getPathname(), PageRepository::EDIT_PATH_SUFFIX)
                ) {
                    $files[] = $this->parsePagePath($site, $file);
                }
            }
        }
        return $files;
    }

    private function createRegexExactPath(string $path): string
    {
        return '/^' . preg_quote($path, '/') . '$/';
    }

    private function findPageByPaths(string $site, array $searchPaths, bool $includeMarkdown = false): ?array
    {
        $siteDir = $this->getContentPagesDirectory($site);
        if (!is_dir($siteDir)) {
            return null;
        }

        foreach ($searchPaths as $searchPath) {
            $finder = new Finder();
            $finder->path($this->createRegexExactPath($searchPath))->in($siteDir)->files();

            if ($finder->hasResults()) {
                foreach ($finder as $file) {
                    return $this->parsePagePath($site, $file, $includeMarkdown);
                }
            }
        }
        return null;
    }

    public function getPage(string $site, string $path, bool $includeMarkdown = false, bool $editMode = false): ?array
    {
        $searchPaths = [];

        $prefix = '';
        if ($editMode === true) {
            $prefix = '.__EDITING_CONCEPT__.md';
        }

        if ($path === '' || rtrim($path, '/') === '') {
            $searchPaths[] = 'index.md' . $prefix;
        } elseif (str_ends_with($path, '.html')) {
            // my/path.html -> my/path/index.md
            $searchPaths[] = substr($path, 0, -strlen('.html')) . '/index.md' . $prefix;

            // my/path.html -> my/path.md
            $searchPaths[] = substr($path, 0, -strlen('.html')) . '.md' . $prefix;
        } else {
            $searchPaths[] = rtrim($path, '/') . '/index.md' . $prefix;  // my/path/ -> my/path/index.md
            $searchPaths[] = rtrim($path, '/') . '.md' . $prefix; // my/path/ -> my/path.md
        }

        return $this->findPageByPaths($site, $searchPaths, $includeMarkdown);
    }

    private function parsePagePath(string $site, SplFileInfo $file, bool $includeMarkdown = false): ?array
    {
        if (is_file($file->getRealPath())) {
            $markdown = file_get_contents($file->getRealPath());
            $parsedPage = $this->contentParser->parse($markdown);
            $pageVariables = $parsedPage['variables'];

            // Generate default slug/title (overridable by the variables in the page yaml)
            $path = substr($file->getPathname(), strlen($this->getContentPagesDirectory($site)) + 1, -3);
            $parts = explode('/', $path);
            $defaultSlug = array_pop($parts);
            if ($defaultSlug === 'index' && isset($parts[0])) {
                $defaultSlug = array_pop($parts);
            }

            $page = array_merge([
                'path' => $path,
                'name' => basename($path),
                'filePath' => $file->getRealPath(),
                'createdAt' => null,
                'updatedAt' => null,
                'slug' => $defaultSlug,

                //'variables' => $result['variables'],
                //'content' => $result['content'],
            ], $pageVariables);

            $page['createdAt'] = $this->getValidDateTime($file, $page['createdAt']);
            $page['updatedAt'] = $this->getValidDateTime($file, $page['updatedAt']);

            if (!isset($page['title'])) {
                $page['title'] = ucfirst($page['slug']);
            }

            if ($includeMarkdown) {
                $page['__markdown'] = $markdown;
            }
            return $page;
        }
        return null;
    }

    public function isValidDateString(?string $dateString = null): bool
    {
        $pattern = '/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}( [0-9]{2}\:[0-9]{2}(\:[0-9]{2})?)?$/';

        if ($dateString !== null && !empty($dateString) && preg_match($pattern, $dateString)) {
            return true;
        }
        return false;
    }

    public function getValidDateTime(SplFileInfo $file, ?string $overrideDateTimeString = null): string
    {
        if ($overrideDateTimeString !== null && $this->isValidDateString($overrideDateTimeString)) {
            return date("Y-m-d H:i:s", strtotime($overrideDateTimeString));
        }
        return date("Y-m-d H:i:s", $file->getMTime());
    }
}
