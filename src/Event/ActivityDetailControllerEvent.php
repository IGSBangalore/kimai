<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Event;

/**
 * Triggered for activity detail pages, to add additional content boxes.
 *
 * @see https://symfony.com/doc/5.4/templates.html#embedding-controllers
 */
final class ActivityDetailControllerEvent extends AbstractActivityEvent
{
    private $controller = [];

    public function addController(string $controller): void
    {
        $this->controller[] = $controller;
    }

    public function getController(): array
    {
        return $this->controller;
    }
}
