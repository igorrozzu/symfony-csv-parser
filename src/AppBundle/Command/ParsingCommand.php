<?php

namespace AppBundle\Command;

use AppBundle\Service\ImportService;
use Ddeboer\DataImport\Result;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ParsingCommand extends ContainerAwareCommand
{
    private $output;

    protected function configure()
    {
        $this->setName('app:parse-csv')
            ->setDescription('Parsing CSV file and write data to database')
            ->setHelp('This command allows you to parse CSV file and write data to database');

        $this->addArgument('filename', InputArgument::REQUIRED, 'The path to file.')
            ->addOption('testmode', null, InputOption::VALUE_NONE, 'Run test parsing without insert to database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): bool
    {
        $this->output = $output;
        $fileName = $input->getArgument('filename');
        $testMode = $input->getOption('testmode');
        $helper = $this->getContainer()->get('app.helper.import');

        try {
            $reader = $helper->getReader($fileName);
        } catch (\Exception $e) {
            $this->output->writeln($e->getMessage());

            return false;
        }
        $writer = $helper->getDoctrineWriter($testMode, 'AppBundle:Product');

        $importer = $this->getContainer()->get('app.import');
        $mapping = $this->getContainer()->getParameter('mapping');
        $importer->setMapping($mapping);
        try {
            $result = $importer->process($reader, $writer);
        } catch (UniqueConstraintViolationException $e) {
            $this->output->writeln('Dublicate Product Code field');

            return false;
        } catch (\Exception $e) {
            $this->output->writeln($e->getMessage());

            return false;
        }
        $this->output->writeln(sprintf(
            "Total processed count: %d \n Success count: %d \n Failed count: %d \n",
            $result->getTotalProcessedCount() + $importer->getSkippedRows(),
            $result->getSuccessCount(),
            $result->getErrorCount() + $importer->getSkippedRows()
        ));
        $this->printReport($result, $importer);

        return true;
    }

    protected function printReport(Result $result, ImportService $service)
    {
        $counter = 1;
        $message = "Report: \n";
        if ($result->getExceptions()->count() === 0 && count($service->getExceptions()) === 0) {
            $message .= 'All data is valid. The import was successful';
        } else {
            foreach ($result->getExceptions() as $exception) {
                $message .= $exception->getLineNumber() + $counter++." row, invalid data:\n";
                foreach ($exception->getViolations() as $violation) {
                    $message .= "\t".$violation->getMessage()."\n";
                }
            }
            foreach ($service->getExceptions() as $exception) {
                $message .= $exception->getMessage()."\n";
            }
        }

        $this->output->writeln($message);
    }
}
