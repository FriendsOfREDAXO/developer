<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class rex_developer_command extends rex_console_command
{
    protected function configure()
    {
        $this
            ->setName('developer:sync')
            ->setDescription('Synchronizes the developer files')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->getStyle($input, $output);

        $io->title('Developer Sync');

        rex_developer_manager::start();

        $io->success('Synchronized developer files.');
    }
}
