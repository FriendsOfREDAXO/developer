<?php

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class rex_developer_command extends rex_console_command
{
    protected function configure()
    {
        $this
            ->setName('developer:sync')
            ->setDescription('Synchronizes the developer files')
            ->addOption('force-db', null, InputOption::VALUE_NONE, 'Force the current status in db, files will be overridden')
            ->addOption('force-files', null, InputOption::VALUE_NONE, 'Force the current status in file system, db data will be overridden')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->getStyle($input, $output);

        $io->title('Developer Sync');

        $forceDb = $input->getOption('force-db');
        $forceFiles = $input->getOption('force-files');

        if ($forceDb && $forceFiles) {
            throw new InvalidArgumentException('Options --force-db and --force-files can not be used at once.');
        }

        $force = false;
        if ($forceDb) {
            $force = rex_developer_synchronizer::FORCE_DB;
        } elseif ($forceFiles) {
            $force = rex_developer_synchronizer::FORCE_FILES;
        }

        rex_developer_manager::start($force);

        $io->success('Synchronized developer files.');

        return 0;
    }
}
