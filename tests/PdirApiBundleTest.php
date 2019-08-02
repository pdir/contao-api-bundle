<?php

/*
 * This file is part of [package name].
 *
 * (c) John Doe
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\ApiBundle\Tests;

use Pdir\ApiBundle\PdirApiBundle;
use PHPUnit\Framework\TestCase;

class PdirApiBundleTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $bundle = new PdirApiBundle();

        $this->assertInstanceOf('Pdir\ApiBundle\PdirApiBundle', $bundle);
    }
}
