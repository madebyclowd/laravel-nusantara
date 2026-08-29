<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Exceptions;

use MadeByClowd\Nusantara\Exceptions\MalformedWkbException;
use MadeByClowd\Nusantara\Exceptions\NusantaraException;
use MadeByClowd\Nusantara\Exceptions\SpatialWkbException;
use MadeByClowd\Nusantara\Tests\TestCase;

class SpatialWkbExceptionTest extends TestCase
{
    /** @test */
    public function test_spatial_wkb_exception_extends_nusantara_exception()
    {
        $exception = new SpatialWkbException('Boundary column bytes could not be parsed.');

        $this->assertInstanceOf(NusantaraException::class, $exception);
        $this->assertSame('Boundary column bytes could not be parsed.', $exception->getMessage());
    }

    /** @test */
    public function test_malformed_wkb_exception_extends_spatial_wkb_exception_and_nusantara_exception()
    {
        $exception = new MalformedWkbException(
            "Malformed WKB in 'boundary' column: truncated or corrupt binary data. Re-run nusantara:download-boundaries --force to re-seed this row."
        );

        $this->assertInstanceOf(SpatialWkbException::class, $exception);
        $this->assertInstanceOf(NusantaraException::class, $exception);
        $this->assertStringContainsString('Malformed WKB', $exception->getMessage());
    }
}
