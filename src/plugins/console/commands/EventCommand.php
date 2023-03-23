<?php

namespace herosphp\plugins\console\commands;

use herosphp\plugins\event\Event;
use herosphp\plugins\event\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EventCommand extends Command
{
    protected static $defaultName = 'show:event';

    protected static $defaultDescription = 'Show Event Table';

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table();
        $table->setCellStyle(Table::COLOR_GREEN);
        foreach (Event::list() as $id => $item) {
            $eventName = $item[0];
            $callback = $item[1];
            if (is_array($callback) && is_object($callback[0])) {
                $callback[0] = get_class($callback[0]);
            }
            $cb = $callback instanceof \Closure ? 'Closure' : (is_array($callback) ? json_encode($callback) : var_export(
                $callback,
                true
            ));
            $table->row([
                'id' => $id,
                'event_name' => $eventName,
                'callback' => $cb,
            ]);
        }
        $output->writeln($table);

        return self::SUCCESS;
    }
}
