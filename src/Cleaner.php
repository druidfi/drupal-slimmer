<?php

namespace Druidfi\DrupalSlimmer;

use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Util\Filesystem;

class Cleaner
{
    private IOInterface $io;
    private Filesystem $filesystem;
    private string $packagePath;

    private array $files = [];
    private array $folders = [];
    private array $packages = [];

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
        $this->filesystem = new Filesystem();

        $this->load('common');
        $this->load('drupal');
    }

    public function clean(Package $package, string $packagePath): int
    {
        $this->packagePath = $packagePath;

        //$this->io->write('<info>Clean up on ' . $package->getName() . '</info>');

        $totalSize = 0;

        $this->find($packagePath,'*.{md,dist,txt}');

        if (isset($this->packages[$package->getPrettyName()])) {
            if (isset($this->packages[$package->getPrettyName()]['files'])) {
                $this->files = array_merge($this->files, $this->packages[$package->getPrettyName()]['files'] ?? []);
            }

            if (isset($this->packages[$package->getPrettyName()]['folders'])) {
                $this->folders = array_merge($this->folders, $this->packages[$package->getPrettyName()]['folders'] ?? []);
            }

            unset($this->packages[$package->getPrettyName()]);
        }

        foreach ($this->files as $file) {
            $totalSize += $this->removeFile($file);
        }

        foreach ($this->folders as $folder) {
            $totalSize += $this->removeFolder($folder);
        }

        return $totalSize;
    }

    private function removeFile(string $fileName): int
    {
        $file = $this->packagePath . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($file)) {
            $size = $this->filesystem->size($file);

            if ($this->io->isVerbose()) {
                $this->write('Removing file', $file, $size);
            }

            $this->filesystem->remove($file);

            return $size;
        }

        return 0;
    }

    private function removeFolder(string $folderName): int
    {
        $folder = $this->packagePath . DIRECTORY_SEPARATOR . $folderName;

        if (file_exists($folder)) {
            $size = $this->filesystem->size($folder);

            if ($this->io->isVerbose()) {
                $this->write('Removing folder', $folder, $size);
            }

            $this->filesystem->removeDirectory($folder);

            return $size;
        }

        return 0;
    }

    private function write(string $action, string $target, int $size): void
    {
        $message = sprintf('  - %s <comment>%s</comment> (%s)', $action, $this->nice($target), $this->size($size));

        $this->io->write($message);
    }

    private function nice(string $path): string
    {
        return str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $this->filesystem->normalizePath($path));
    }

    private function load(string $name): array
    {
        $file = __DIR__ . sprintf('/../data/%s.php', $name);

        if (file_exists($file)) {
            $data = require $file;

            $this->files += $data['files'] ?? [];
            $this->folders += $data['folders'] ?? [];
            $this->packages += $data['packages'] ?? [];
        }

        return [];
    }

    private function find($dir, $ext = '*') {
        foreach (glob("$dir$ext", GLOB_BRACE) as $ff) {
            if (is_file($ff)) {
                $this->files[] = $ff;
            }
        }

        foreach (glob("$dir*", GLOB_ONLYDIR | GLOB_MARK) as $ff) {
            $this->rglob($ff, $ext);
        }
    }

    public static function size(int $bytes, int $dec = 2): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        if ($factor == 0) $dec = 0;

        return sprintf("%.{$dec}f %s", $bytes / (1024 ** $factor), $size[$factor]);
    }
}
