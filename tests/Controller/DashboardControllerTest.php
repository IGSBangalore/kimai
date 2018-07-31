<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\User;

/**
 * @coversDefaultClass \App\Controller\DashboardController
 * @group integration
 */
class DashboardControllerTest extends ControllerBaseTest
{
    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/dashboard/');
    }

    public function testIndexAction()
    {
        $client = $this->getClientForAuthenticatedUser();
        $this->request($client, '/dashboard/');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertMainContentClass($client, 'dashboard');
    }

    public function testIndexActionForAdmin()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->request($client, '/dashboard/');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertMainContentClass($client, 'dashboard');
    }
}
