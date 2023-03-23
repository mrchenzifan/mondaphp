<?php

namespace herosphp\utils;

use function chmod;
use herosphp\exception\FileException;
use function is_dir;
use function mkdir;
use function pathinfo;
use SplFileInfo;
use function sprintf;
use function umask;

class File extends SplFileInfo
{
    /**
     * Move.
     *
     * @param  string  $destination
     * @return File
     */
    public function move(string $destination): self
    {
        $path = pathinfo($destination, PATHINFO_DIRNAME);
        if (! is_dir($path) && ! mkdir($path, 0777, true)) {
            throw new FileException(sprintf('Unable to create the directory (%s)', $path));
        }
        if (! rename($this->getPathname(), $destination)) {
            throw new FileException(sprintf('Could not move the file "%s" to "%s"', $this->getPathname(), $destination));
        }
        @chmod($destination, 0666 & ~umask());

        return new self($destination);
    }
}
