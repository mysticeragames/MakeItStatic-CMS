<?php

namespace App\Repositories;

use Nette\Utils\FileSystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitRepository
{
    protected string $dir;
    private string $originalDir;
    private ?string $subDir = null;

    public function __construct(string $dir)
    {
        $this->originalDir = $dir;
        $this->dir = $this->originalDir;
    }

    protected function setSubDir(string $dir): void
    {
        $this->subDir = $dir;

        if (empty($this->subDir)) {
            $this->dir = $this->originalDir;
        } else {
            $this->dir = $this->originalDir . '/' . $this->subDir;
        }
    }

    public function git(array $arguments): Process
    {
        $cmd = array_merge(['git', '-C', $this->dir], $arguments);

        $process = new Process($cmd);
        $process->run();

        return $process;
    }

    // public function addRemote(string $repositoryUrl): bool
    // {
    //     if (empty($repositoryUrl)) {
    //         dump('empty repo url');
    //         return false;
    //     }

    //     if ($this->hasRemote()) {
    //         dump('already has remote');
    //         return false; // Remote already connected, should be removed first
    //     }

    //     $this->disconnectRemote();

    //     $this->clone($this->dir, $repositoryUrl);

    //     return $this->hasRemote();
    // }

    public function destroy(bool $recreateEmptyDir = false): void
    {
        $fs = new FileSystem();
        $fs->delete($this->dir);

        if ($recreateEmptyDir) {
            $fs->createDir($this->dir);
        }
    }

    public function getRootDir(): string
    {
        $process = $this->git(['rev-parse', '--show-toplevel']);

        return trim($process->getOutput());
    }

    public function isRootGitDir(): bool
    {
        if ($this->dir === $this->getRootDir()) {
            return true;
        }
        return false;

        // For code coverage tests, it's split up
        // return $dir === $this->getRootDir($dir);
    }

    public function isNonRootGitDir(): bool
    {
        $rootDir = $this->getRootDir();

        if (empty($rootDir)) {
            return false; // Not a git repository
        }
        if ($rootDir !== $this->dir) {
            return false; // The given $dir is part of a git repository, but not the root
        }
        return true;

        // For code coverage tests, it's split up, don't use below...
        //return !empty($output) && $output !== $dir;
    }

    public function hasRemote(): bool
    {
        return !empty($this->getRemote());
    }

    public function getRemote(): ?string
    {
        if (!$this->isInitiated()) {
            return null;
        }

        if (!$this->isNonRootGitDir()) {
            return null;
        }

        $process = $this->git(['config', 'remote.origin.url']);

        return trim($process->getOutput());
    }

    public function init(): bool
    {
        if (is_dir($this->dir) === false) {
            // Directory does not exist
            //dd('init: dir does not exist: ' . $this->dir);
            return false;
        }

        if ($this->isRootGitDir()) {
            // This is already a git dir
            //dd('init: This is already a root git dir: ' . $this->dir);
            return false;
        }

        $process = $this->git(['init']);

        // For code coverage tests, it's split up (therefore it's not 'return $process->isSuccessful()' )
        if ($process->isSuccessful()) {
            return true;
        }
        return false;
    }

    public function isInitiated(): bool
    {
        if (is_dir($this->dir) === false) {
            // Directory does not exist
            return false;
        }

        if (empty($this->getRootDir())) {
            // Dir does not have a git-root, so it's not yet initiated
            return false;
        }

        return true;
    }

    public function clone(string $repositoryUrl, string $origin = 'origin'): bool
    {
        // TODO: Check if this can be done just normally with 'git clone' and then depth = 1, and only default branch

        $result = $this->init();

        if (!$result) {
            return false;
        }
        $result = $this->git(['remote', 'add', $origin, $repositoryUrl]);
        if (!$result->isSuccessful()) {
            //dd('clone: could not add remote');
            return false;
        }
        //$result = $this->git(['fetch', '--all']);
        $result = $this->git(['fetch']);


        $currentBranch = $this->getCurrentBranch();
        $this->git(['checkout', '--track', $origin . '/' . $currentBranch]);

        return true;
    }

    public function setRemote(string $url): bool
    {
        if (!$this->hasRemote()) {
            $this->git(['remote', 'add', 'origin', trim($url)]);
        } else {
            $this->git(['remote', 'set-url', 'origin', trim($url)]);
        }
        return $this->getRemote() === trim($url);
    }

    public function disconnectRemote(): bool
    {
        if ($this->hasRemote()) {
            $this->git(['remote', 'remove', 'origin']);
        }
        return $this->hasRemote();
    }



    public function pull(): void
    {
        $process = $this->git(['pull']);

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function addAll(): bool
    {
        return $this->add('.');
    }

    public function add(?string $path = null): bool
    {
        if ($path === null) {
            return false;
        }

        $process = $this->git(['add', $path]);

        if (!$process->isSuccessful()) {
            //throw new ProcessFailedException($process);
            return false;
        }
        return true;
    }

    public function commit(string $message = ''): void
    {
        $process = $this->git(['commit', '-m', $message]);

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function parseGitStatus(string $gitOutput): array
    {
        $lines = array_filter(explode("\n", trim($gitOutput)));
        $results = [];

        foreach ($lines as $line) {
            $entry = [
                'path' => '',
                'x' => '',
                'y' => '',
                'status' => '',
                'orig_path' => '',
            ];

            // Determine status by looking at each part of the line
            $x = $line[0] ?? '';
            $y = $line[1] ?? '';
            $entry['x'] = $x;
            $entry['y'] = $y;

            // Extract the rest of the line
            $rest = trim(substr($line, 3));

            // Handle specific statuses
            if ($x === 'R') {
                // Renamed: split into original and new path
                [$origPath, $newPath] = explode(" -> ", $rest, 2);
                $entry['status'] = 'renamed';
                $entry['orig_path'] = $origPath;
                $entry['path'] = $newPath;
            } elseif ($x === 'M') {
                $entry['status'] = 'modified';
                $entry['path'] = $rest;
            } elseif ($x === 'A') {
                $entry['status'] = 'added';
                $entry['path'] = $rest;
            } elseif ($x === 'D') {
                $entry['status'] = 'deleted';
                $entry['path'] = $rest;
            } elseif ($x === 'C') {
                $entry['status'] = 'copied';
                $entry['path'] = $rest;
            } elseif ($x === '?' && $y === '?') {
                $entry['status'] = 'untracked';
                $entry['path'] = $rest;
            } elseif ($x === '!' && $y === '!') {
                $entry['status'] = 'ignored';
                $entry['path'] = $rest;
            } elseif ($x === 'U' || $y === 'U') {
                $entry['status'] = 'unmerged';
                $entry['path'] = $rest;
            } else {
                $entry['status'] = 'unknown';
                $entry['path'] = $rest;
            }

            $results[] = $entry;
        }
        return $results;
    }

    public function hasChanges(): bool
    {
        return !empty($this->changes());
    }

    public function flatChanges(): array
    {
        return array_map(function ($item) {
            $status = $item['x'];
            if ($status === '?') {
                $status = $item['y'];
            }

            if ($status === 'R' && isset($item['orig_path'])) {
                $item['path'] = $item['orig_path'] . ' -> ' . $item['path'];
            }

            return $status . ' ' . $item['path'];
        }, $this->changes());
    }

    public function changes(): array
    {
        if (!$this->isInitiated()) {
            return [];
        }

        // For this project, just always add any changes
        $this->addAll();

        // https://git-scm.com/docs/git-status#_short_format
        // X          Y     Meaning
        // -------------------------------------------------
        //      [AMD]   not updated
        // M        [ MTD]  updated in index
        // T        [ MTD]  type changed in index
        // A        [ MTD]  added to index
        // D                deleted from index
        // R        [ MTD]  renamed in index
        // C        [ MTD]  copied in index
        // [MTARC]          index and work tree matches
        // [ MTARC]    M    work tree changed since index
        // [ MTARC]    T    type changed in work tree since index
        // [ MTARC]    D    deleted in work tree
        //         R    renamed in work tree
        //         C    copied in work tree
        // -------------------------------------------------
        // D           D    unmerged, both deleted
        // A           U    unmerged, added by us
        // U           D    unmerged, deleted by them
        // U           A    unmerged, added by them
        // D           U    unmerged, deleted by us
        // A           A    unmerged, both added
        // U           U    unmerged, both modified
        // -------------------------------------------------
        // ?           ?    untracked
        // !           !    ignored
        // -------------------------------------------------

        $process = $this->git(['status', '-u', '--porcelain']);

        return $this->parseGitStatus($process->getOutput());

        // $output = $process->getOutput();
        // $lines = array_filter(explode("\n", $output));

        // $files = [];
        // foreach ($lines as $line) {
        //     $line = trim($line);
        //     $parts = explode(' ', $line);

        //     dump($parts);

        //     $status = array_shift($parts);
        //     $file = trim(implode(' ', $parts));
        //     if ($status === '??') {
        //         $status = '?';
        //     }
        //     $files[$file] = $status;
        // }
        // return $files;
    }

    public function getCurrentBranch(): string
    {
        $process = $this->git(['branch', '--show-current']);
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return trim($process->getOutput());
    }

    public function addCommitPush(?string $message = null): void
    {
        if (empty($message)) {
            $message = 'deployment ' . date('Y-m-d H:i:s');
        }

        // Get current branch
        $branch = $this->getCurrentBranch();

        // Add all files in working directory
        $process = $this->git(['add', '.']);
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Commit files
        $process = $this->git(['commit', '-m', $message]);
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Push
        $process = $this->git(['push', '-u', 'origin', $branch]);
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
