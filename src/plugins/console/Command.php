<?php

namespace herosphp\plugins\console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command as Commands;

class Command extends Application
{
    public function installInternalCommands()
    {
        $this->installCommands(__DIR__.'/commands', 'herosphp\plugins\console\commands');
    }

    public function installCommands($path, $namespace = 'app\command')
    {
        $dir_iterator = new \RecursiveDirectoryIterator($path);
        $iterator = new \RecursiveIteratorIterator($dir_iterator);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            // abc\def.php
            $relativePath = str_replace(
                str_replace('/', '\\', $path.'\\'),
                '',
                str_replace('/', '\\', $file->getRealPath())
            );
            // app\command\abc
            $realNamespace = trim($namespace.'\\'.trim(
                dirname(str_replace('\\', DIRECTORY_SEPARATOR, $relativePath)),
                '.'
            ), '\\');
            // app\command\doc\def
            $className = trim($realNamespace.'\\'.$file->getBasename('.php'), '\\');
            if (! class_exists($className) || ! is_a($className, Commands::class, true)) {
                continue;
            }

            $this->add(new $className);
        }
    }
}
