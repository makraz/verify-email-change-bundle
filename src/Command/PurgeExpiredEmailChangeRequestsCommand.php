<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Command;

use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'verify:email-change:purge-expired',
    description: 'Purge expired email change requests from the database',
)]
class PurgeExpiredEmailChangeRequestsCommand extends Command
{
    public function __construct(
        private readonly EmailChangeRequestRepositoryInterface $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show how many records would be deleted without deleting them')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Delete requests expired more than X seconds ago (default: 0, meaning all expired)', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $olderThan = (int) $input->getOption('older-than');

        if ($olderThan > 0) {
            $cutoff = new \DateTimeImmutable('-'.$olderThan.' seconds');

            if ($dryRun) {
                $count = $this->repository->countExpiredOlderThan($cutoff);
                $io->info(sprintf('Would purge %d expired email change request(s).', $count));
            } else {
                $count = $this->repository->removeExpiredOlderThan($cutoff);
                $io->success(sprintf('Purged %d expired email change request(s).', $count));
            }
        } else {
            if ($dryRun) {
                $count = $this->repository->countExpiredEmailChangeRequests();
                $io->info(sprintf('Would purge %d expired email change request(s).', $count));
            } else {
                $count = $this->repository->removeExpiredEmailChangeRequests();
                $io->success(sprintf('Purged %d expired email change request(s).', $count));
            }
        }

        return Command::SUCCESS;
    }
}
