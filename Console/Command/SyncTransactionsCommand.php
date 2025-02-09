<?php

namespace Taxdoo\VAT\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Event\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Taxdoo\VAT\Model\Logger;
use Taxdoo\VAT\Model\Transaction\Backfill;

class SyncTransactionsCommand extends Command
{
    const FROM_ARGUMENT = '<from>';
    const TO_ARGUMENT = '<to>';

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;

    /**
     * @var \Taxdoo\VAT\Model\Logger
     */
    protected $logger;

    /**
     * @var \Taxdoo\VAT\Model\Transaction\Backfill
     */
    protected $backfill;

    /**
     * @param State $state
     * @param ManagerInterface $eventManager
     * @param Logger $logger
     * @param Backfill $backfill
     */
    public function __construct(
        State $state,
        Manager $eventManager,
        Logger $logger,
        Backfill $backfill
    ) {
        $this->state = $state;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
        $this->backfill = $backfill;
        parent::__construct();
    }

    /**
     * Sets config for CLI command
     */
    protected function configure()
    {
        $this->setName('taxdoo:transactions:sync')
            ->setDescription('Sync transactions from Magento to Taxdoo')
            ->addArgument(self::FROM_ARGUMENT, InputArgument::OPTIONAL)
            ->addArgument(self::TO_ARGUMENT, InputArgument::OPTIONAL);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return string
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        try {
            $this->state->setAreaCode('adminhtml');
            $this->logger->console($output);
            $this->backfill->start([
                'from_date' => $input->getArgument(self::FROM_ARGUMENT),
                'to_date' => $input->getArgument(self::TO_ARGUMENT)
            ]);
        } catch (\Exception $e) {
            $output->writeln(PHP_EOL . '<error>Failed to sync transactions: ' . $e->getMessage() . '</error>');
        }
    }
}
