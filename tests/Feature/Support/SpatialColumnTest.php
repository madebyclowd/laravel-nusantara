<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Support\SpatialColumn;
use MadeByClowd\Nusantara\Tests\TestCase;

class SpatialColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('testing')->dropIfExists('spatial_column_test');
        Schema::connection('testing')->create('spatial_column_test', function (Blueprint $table) {
            $table->id();
            $table->text('plain_text_column')->nullable();
            $table->geometry('geometry_column')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('testing')->dropIfExists('spatial_column_test');

        parent::tearDown();
    }

    /** @test */
    public function test_it_returns_true_for_a_geometry_column()
    {
        $this->assertTrue(SpatialColumn::isSpatial('testing', 'spatial_column_test', 'geometry_column'));
    }

    /** @test */
    public function test_it_returns_false_for_a_plain_text_column()
    {
        $this->assertFalse(SpatialColumn::isSpatial('testing', 'spatial_column_test', 'plain_text_column'));
    }

    /** @test */
    public function test_it_treats_a_geography_type_as_spatial()
    {
        Schema::connection('testing')->table('spatial_column_test', function (Blueprint $table) {
            $table->addColumn('geography', 'geography_column')->nullable();
        });

        $this->assertTrue(SpatialColumn::isSpatial('testing', 'spatial_column_test', 'geography_column'));
    }

    /** @test */
    public function test_it_uses_the_default_connection_when_none_is_given()
    {
        config(['database.default' => 'testing']);

        $this->assertFalse(SpatialColumn::isSpatial(null, 'spatial_column_test', 'plain_text_column'));
    }
}
