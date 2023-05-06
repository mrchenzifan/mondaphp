<?php

declare(strict_types=1);

namespace herosphp\plugins\csv;

interface ExportInterface
{
    public function setHeader();

    public function putTitle(array $title);

    public function putData(array $data);

    public function export();
}
