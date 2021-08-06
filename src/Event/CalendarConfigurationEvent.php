<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class CalendarConfigurationEvent extends Event
{
    /**
     * @var array<string, string|int|bool|array>
     */
    private $configuration;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration)
    {
        foreach ($configuration as $key => $value) {
            if (array_key_exists($key, $this->configuration)) {
                $this->configuration[$key] = $value;
            }
        }
    }
}
