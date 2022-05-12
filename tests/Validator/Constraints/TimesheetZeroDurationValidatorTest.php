<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Validator\Constraints;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use App\Validator\Constraints\TimesheetZeroDuration;
use App\Validator\Constraints\TimesheetZeroDurationValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @covers \App\Validator\Constraints\TimesheetZeroDuration
 * @covers \App\Validator\Constraints\TimesheetZeroDurationValidator
 */
class TimesheetZeroDurationValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return $this->createMyValidator(false);
    }

    protected function createMyValidator(bool $allowZeroDuration = false)
    {
        $loader = $this->createMock(ConfigLoaderInterface::class);
        $config = new SystemConfiguration($loader, [
            'timesheet' => [
                'rules' => [
                    'allow_zero_duration' => $allowZeroDuration,
                ],
            ]
        ]);

        return new TimesheetZeroDurationValidator($config);
    }

    public function testConstraintIsInvalid()
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Timesheet(), new NotBlank());
    }

    public function testInvalidValueThrowsException()
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new NotBlank(), new TimesheetZeroDuration(['message' => 'myMessage']));
    }

    private function prepareTimesheet() {
        // creates Timesheet with same begin and endtime
        $begin = new Datetime();
        $timesheet = new Timesheet();
        $timesheet->setBegin($begin);
        $timesheet->setEnd($begin);

        return $timesheet;
    }

    public function testZeroDurationIsDisallowed()
    {
        $timesheet = prepareTimesheet();

        $this->validator->validate($timesheet, new TimesheetZeroDuration(['message' => 'myMessage']));

        $this->buildViolation('Duration cannot be zero.')
            ->atPath('property.path.duration')
            ->setCode(TimesheetZeroDuration::ZERO_DURATION_ERROR)
            ->assertRaised();
    }

    public function testZeroDurationIsAllowed()
    {
        $timesheet = prepareTimesheet();

        $this->validator->validate($timesheet, new TimesheetZeroDuration(['message' => 'myMessage']));

        self::assertEmpty($this->context->getViolations());
    }
}
