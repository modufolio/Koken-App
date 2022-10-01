<?php

namespace Modufolio\Toolkit;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Modufolio\Toolkit\A
 */

class DateTest extends TestCase
{
    public function testTranslate()
    {
        $this->assertEquals('31 march 2017', Date::translate('31 maart 2017', 'nl'));
    }
    public function testConvert()
    {
        $this->assertEquals('2019-02-11', Date::convert('11/02/2019', 'd/m/Y', 'Y-m-d'));
    }
}