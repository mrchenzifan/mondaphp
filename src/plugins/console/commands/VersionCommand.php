<?php

namespace herosphp\plugins\console\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'constants.php';

class VersionCommand extends Command
{
    protected static $defaultName = 'version';

    protected static $defaultDescription = 'Show Herosphp Version';

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Herosphp-Framework Version: '.X_POWER);

        return self::SUCCESS;
    }
}
