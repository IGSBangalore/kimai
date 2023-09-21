<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Widget\Type;

use App\Repository\TimesheetRepository;
use App\Widget\WidgetException;
use App\Widget\WidgetInterface;

final class UserDurationToday extends AbstractCounterDuration
{
    public function __construct(private TimesheetRepository $repository)
    {
    }

    /**
     * @param array<string, string|bool|int|null> $options
     * @return array<string, string|bool|int|null>
     */
    public function getOptions(array $options = []): array
    {
        return array_merge(['color' => WidgetInterface::COLOR_TODAY], parent::getOptions($options));
    }

    public function getPermissions(): array
    {
        return ['view_own_timesheet'];
    }

    public function getId(): string
    {
        return 'userDurationToday';
    }

    /**
     * @param array<string, string|bool|int|null> $options
     */
    public function getData(array $options = []): mixed
    {
        try {
            return $this->repository->getStatistic(TimesheetRepository::STATS_QUERY_DURATION, $this->createTodayStartDate(), $this->createTodayEndDate(), $this->getUser());
        } catch (\Exception $ex) {
            throw new WidgetException(
                'Failed loading widget data: ' . $ex->getMessage()
            );
        }
    }
}
