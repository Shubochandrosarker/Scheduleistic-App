<?php

namespace Tests\Unit;

use App\Services\MediaCropService;
use PHPUnit\Framework\TestCase;

class MediaCropTest extends TestCase
{
    public function test_wide_source_is_cropped_on_the_sides_for_a_square_target(): void
    {
        $spec = (new MediaCropService())->specFor('instagram', 1920, 1080);

        // Square (1:1) target from a 16:9 source → crop width down to height.
        $this->assertSame(1080, $spec['crop']['height']);
        $this->assertSame(1080, $spec['crop']['width']);
        $this->assertSame(420, $spec['crop']['x']); // (1920-1080)/2
        $this->assertSame(0, $spec['crop']['y']);
        $this->assertSame([1080, 1080], [$spec['output']['width'], $spec['output']['height']]);
    }

    public function test_tall_source_is_cropped_top_and_bottom_for_a_landscape_target(): void
    {
        $spec = (new MediaCropService())->specFor('facebook', 1000, 2000); // target 1200x630 (~1.9:1)

        $this->assertSame(1000, $spec['crop']['width']);
        $this->assertSame(525, $spec['crop']['height']); // 1000 / (1200/630)
        $this->assertSame(0, $spec['crop']['x']);
    }

    public function test_specs_for_multiple_providers(): void
    {
        $specs = (new MediaCropService())->specsFor(['instagram', 'pinterest'], 1080, 1080);

        $this->assertArrayHasKey('instagram', $specs);
        $this->assertArrayHasKey('pinterest', $specs);
        $this->assertSame([1000, 1500], [
            $specs['pinterest']['output']['width'],
            $specs['pinterest']['output']['height'],
        ]);
    }
}
