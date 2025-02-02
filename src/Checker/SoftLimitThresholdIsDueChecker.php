<?php

declare(strict_types=1);

namespace Synolia\SyliusSchedulerCommandPlugin\Checker;

use Cron\CronExpression;
use Synolia\SyliusSchedulerCommandPlugin\Components\Exceptions\Checker\IsNotDueException;
use Synolia\SyliusSchedulerCommandPlugin\Entity\CommandInterface;
use Synolia\SyliusSchedulerCommandPlugin\Repository\ScheduledCommandRepositoryInterface;

class SoftLimitThresholdIsDueChecker implements IsDueCheckerInterface
{
    /** @var \Synolia\SyliusSchedulerCommandPlugin\Repository\ScheduledCommandRepositoryInterface */
    private $scheduledCommandRepository;

    /**
     * @var int
     *
     * Threshold in minutes
     */
    private $threshold;

    public static function getDefaultPriority(): int
    {
        return -100;
    }

    public function __construct(
        ScheduledCommandRepositoryInterface $scheduledCommandRepository,
        int $threshold = 5
    ) {
        $this->scheduledCommandRepository = $scheduledCommandRepository;
        $this->threshold = $threshold;
    }

    /**
     * @throws \Synolia\SyliusSchedulerCommandPlugin\Components\Exceptions\Checker\IsNotDueException
     */
    public function isDue(CommandInterface $command, ?\DateTimeInterface $dateTime = null): bool
    {
        if (null === $dateTime) {
            $dateTime = new \DateTime();
        }

        $cron = new CronExpression($command->getCronExpression());
        if ($cron->isDue($dateTime)) {
            return true;
        }

        $previousRunDate = $cron->getPreviousRunDate();
        $previousRunDateThreshold = (clone $previousRunDate)->add(new \DateInterval(\sprintf('PT%dM', $this->threshold)));

        $lastCreatedScheduledCommand = $this->scheduledCommandRepository->findLastCreatedCommand($command);

        // if never, do my command is valid for the least "threshold" minutes
        if ($lastCreatedScheduledCommand === null) {
            if ($dateTime->getTimestamp() >= $previousRunDate->getTimestamp() && $dateTime->getTimestamp() <= $previousRunDateThreshold->getTimestamp()) {
                return true;
            }

            throw new IsNotDueException();
        }

        // check if last command has been started since scheduled datetime +0..5 minutes
        if ($lastCreatedScheduledCommand->getCreatedAt()->getTimestamp() >= $previousRunDate->getTimestamp() &&
            $lastCreatedScheduledCommand->getCreatedAt()->getTimestamp() <= $previousRunDateThreshold->getTimestamp() &&
            $dateTime->getTimestamp() <= $previousRunDateThreshold->getTimestamp()) {
            throw new IsNotDueException();
        }

        if ($dateTime->getTimestamp() >= $previousRunDate->getTimestamp() &&
            $dateTime->getTimestamp() <= $previousRunDateThreshold->getTimestamp()
        ) {
            return true;
        }

        throw new IsNotDueException();
    }
}
