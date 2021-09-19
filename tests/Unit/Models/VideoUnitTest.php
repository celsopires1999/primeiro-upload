<?php

namespace Tests\Models\Unit;

use Tests\TestCase;
use App\Models\Video;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Uuid;

class VideoUnitTest extends TestCase
{
    use SoftDeletes, Uuid;

    /** @var Video $video */
    private $video;

    protected function setUp(): void
    {
        parent::setUp();
        $this->video = new Video();
    }

    public function testFillableAttribute()
    {
        $fillable = [
            'title',
            'description',
            'year_launched',
            'opened',
            'rating',
            'duration',
        ];

        $this->assertEquals($fillable, $this->video->getFillable());
    }

    public function testIfUseTraits()
    {
        $traits = [
            SoftDeletes::class,
            Uuid::class
        ];

        $videoTraits = array_keys(class_uses(Video::class));
        $this->assertEquals($traits, $videoTraits);
    }

    public function testIncrementingAttribute()
    {
        $this->assertFalse($this->video->incrementing);
    }

    public function testDatesAttribute()
    {
        $dates = ['deleted_at', 'created_at', 'updated_at'];
        foreach ($dates as $date) {
            $this->assertContains($date, $this->video->getDates());
        }
        $this->assertCount(count($dates), $this->video->getDates());
    }

}
