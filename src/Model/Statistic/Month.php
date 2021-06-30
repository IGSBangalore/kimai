<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Model\Statistic;

use InvalidArgumentException;

final class Month
{
    private $month;
    private $totalDuration = 0;
    private $totalRate = 0.00;
    private $totalInternalRate = 0.00;
    private $billableDuration = 0;
    private $billableRate = 0.00;

    public function __construct(string $month)
    {
        $monthNumber = (int) $month;
        if ($monthNumber < 1 || $monthNumber > 12) {
            throw new InvalidArgumentException(
                sprintf('Invalid month given. Expected 1-12, received "%s".', $monthNumber)
            );
        }
        $this->month = str_pad($month, 2, '0', STR_PAD_LEFT);
    }

    public function getMonth(): string
    {
        return $this->month;
    }

    public function getMonthNumber(): int
    {
        return (int) $this->month;
    }

    public function getTotalDuration(): int
    {
        return $this->totalDuration;
    }

    public function setTotalDuration(int $seconds): Month
    {
        $this->totalDuration = $seconds;

        return $this;
    }

    public function getTotalRate(): float
    {
        return $this->totalRate;
    }

    public function setTotalRate(float $totalRate): Month
    {
        $this->totalRate = $totalRate;

        return $this;
    }

    public function getTotalInternalRate(): float
    {
        return $this->totalInternalRate;
    }

    public function setTotalInternalRate(float $totalInternalRate): void
    {
        $this->totalInternalRate = $totalInternalRate;
    }

    public function getBillableDuration(): int
    {
        return $this->billableDuration;
    }

    public function setBillableDuration(int $billableDuration): void
    {
        $this->billableDuration = $billableDuration;
    }

    public function getBillableRate(): float
    {
        return $this->billableRate;
    }

    public function setBillableRate(float $billableRate): void
    {
        $this->billableRate = $billableRate;
    }
}
