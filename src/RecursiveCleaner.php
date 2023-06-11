<?php

namespace Druidfi\ComposerSlimmer;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class RecursiveCleaner
{
    use UtilsTrait;

    private Filesystem $filesystem;

    private array $folders = [
        '.circleci',
        '.ddev',
        '.git',
        '.github',
        '.psalm',
        '.tugboat',
        'doc',
        'docs',
        'examples',
        //'migrations',
        'test',
        'tests',
    ];

    private array $extensions = [
        'dist',
        'md',
        'txt',
    ];

    private array $filesToRemove;

    private int $totalSize = 0;

    private array $matchingFolders = [];
    private array $matchingFiles = [];

    public function __construct(IOInterface $io = null)
    {
        $this->io = $io;
        $this->filesystem = new Filesystem();
        $this->filesToRemove = require __DIR__ . '/../data/files.php' ?? [];
    }

    public function clean(string $path, bool $dry_run = false): int
    {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir() && $file->getFilename() !== '..') {
                $folderParts = explode(DIRECTORY_SEPARATOR, $file->getPath());

                if (in_array(end($folderParts), $this->folders)) {
                    $this->handleItem($file, $dry_run);
                }
            }
            else {
                if (in_array($file->getExtension(), $this->extensions)) {
                    $this->handleItem($file, $dry_run);
                }
                else if (in_array($file->getFilename(), $this->filesToRemove)) {
                    $this->handleItem($file, $dry_run);
                }
            }
        }

        return $this->totalSize;
    }

    public function getMatchingFolders(): array
    {
        return $this->matchingFolders;
    }

    private function handleItem($file, $dry_run = false): void
    {
        $resource = ($file->isDir()) ? 'folder' : 'file';

        try {
            $size = $this->filesystem->size($file->getRealPath());
        }
        catch (Exception $e) {
            return;
        }

        if ($resource === 'folder') {
            $this->matchingFolders[] = $file->getPathname();
        }
        else {
            $this->matchingFiles[] = $file->getPathname();
        }

        if ($this->io && $this->io->isVerbose()) {
            $this->write('Removing ' . $resource, $file, $size);
        }

        if ($dry_run) {
            echo PHP_EOL . 'DRYRUN: removing ' . $resource . ' ' . $file->getPath() . ' <> ' . $size;
        } else {
            $this->filesystem->remove($file->getRealPath());
        }

        $this->totalSize += $size;
    }
}
