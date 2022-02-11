<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Utils;

use App\Utils\Duration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Utils\Duration
 */
class DurationTest extends TestCase
{
    public function testFormat()
    {
        $sut = new Duration();

        $this->assertNull($sut->format(null));
        $this->assertEquals('02:38', $sut->format(9494));
        $this->assertEquals('02:38:14', $sut->format(9494, Duration::FORMAT_WITH_SECONDS));
    }

    /**
     * @group legacy
     */
    public function testParseDurationStringSpecials()
    {
        $sut = new Duration();
        $this->assertEquals(0, $sut->parseDuration('-1', Duration::FORMAT_SECONDS));
        $this->assertEquals(0, $sut->parseDuration('0', Duration::FORMAT_SECONDS));
        $this->assertEquals(3600, $sut->parseDuration('3600', Duration::FORMAT_SECONDS));
        $this->assertEquals(0, $sut->parseDuration('', Duration::FORMAT_SECONDS));
        $this->assertEquals(0, $sut->parseDuration('-12', Duration::FORMAT_SECONDS));
    }

    /**
     * @dataProvider getParseDurationTestData
     */
    public function testParseDurationString($expected, $duration, $mode)
    {
        $sut = new Duration();
        $this->assertEquals($expected, $sut->parseDurationString($duration));
    }

    /**
     * @dataProvider getParseDurationTestData
     */
    public function testParseDuration($expected, $duration, $mode)
    {
        $sut = new Duration();
        $this->assertEquals($expected, $sut->parseDuration($duration, $mode));
    }

    public function getParseDurationTestData()
    {
        return [
            [3600, null, Duration::FORMAT_DECIMAL],
            [3600, 1, Duration::FORMAT_DECIMAL],
            [5400, 1.5, Duration::FORMAT_DECIMAL],
            [3600, '1', Duration::FORMAT_DECIMAL],
            [5400, '1.5', Duration::FORMAT_DECIMAL],
            [5400, '1,5', Duration::FORMAT_DECIMAL],

            [0, '', Duration::FORMAT_NATURAL],
            [0, 0, Duration::FORMAT_NATURAL],

            [99, '99s', Duration::FORMAT_NATURAL],
            [7200, '2h', Duration::FORMAT_NATURAL],
            [2280, '38m', Duration::FORMAT_NATURAL],
            [9480, '2h38m', Duration::FORMAT_NATURAL],
            [9497, '2h38m17s', Duration::FORMAT_NATURAL],
            [9497, '1h96m137s', Duration::FORMAT_NATURAL],

            [0, '', Duration::FORMAT_COLON],
            [0, 0, Duration::FORMAT_COLON],

            [12420, '3:27', Duration::FORMAT_COLON],
            [12420, '3h27m', Duration::FORMAT_NATURAL],

            [48420, '13:27', Duration::FORMAT_COLON],
            [48474, '13:27:54', Duration::FORMAT_COLON],
            [48474, '12:87:54', Duration::FORMAT_COLON],
            [11257200, '3127:00:00', Duration::FORMAT_COLON],
            [11257200, '3127:00', Duration::FORMAT_COLON],
        ];
    }

    public function getParseDurationInvalidData()
    {
        return [
            // invalid input
            ['13', Duration::FORMAT_COLON],
            ['13-13', Duration::FORMAT_COLON],
            ['13.13', Duration::FORMAT_COLON],
            [1111, Duration::FORMAT_NATURAL],

            // invalid modes
            [17, 'foo'],
            [12, ''],

            ['3127::00', Duration::FORMAT_COLON],
            ['00::', Duration::FORMAT_COLON],
            ['3127:00:', Duration::FORMAT_COLON],
            [':3127:00', Duration::FORMAT_COLON],
            ['::3127', Duration::FORMAT_COLON],
            ['3127:-01', Duration::FORMAT_COLON],
            ['-3127:01:17', Duration::FORMAT_COLON],
        ];
    }

    /**
     * @dataProvider getParseDurationInvalidData
     */
    public function testParseDurationThrowsInvalidArgumentException($duration, $mode)
    {
        $this->expectException(\InvalidArgumentException::class);

        $sut = new Duration();
        $sut->parseDuration($duration, $mode);
    }
}
