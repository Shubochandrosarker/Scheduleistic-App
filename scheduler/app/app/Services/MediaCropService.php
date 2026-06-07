<?php

namespace App\Services;

/**
 * Computes per-network crop specifications for a source image. The pure
 * geometry lives here (and is unit-tested); the actual pixel cropping is
 * performed by the worker via GD/Intervention using these specs.
 */
class MediaCropService
{
    /** Recommended output dimensions per network. */
    public const DIMENSIONS = [
        'linkedin'         => [1200, 627],
        'linkedin_company' => [1200, 627],
        'facebook'         => [1200, 630],
        'instagram'        => [1080, 1080],
        'pinterest'        => [1000, 1500],
        'google_business'  => [1200, 900],
        'threads'          => [1080, 1080],
        'twitter'          => [1200, 675],
    ];

    /**
     * Compute a centered crop box for a source image to fit a network's aspect
     * ratio, then the target output size.
     *
     * @return array{crop:array{x:int,y:int,width:int,height:int}, output:array{width:int,height:int}}
     */
    public function specFor(string $provider, int $srcWidth, int $srcHeight): array
    {
        [$outW, $outH] = self::DIMENSIONS[$provider] ?? [1080, 1080];

        $targetRatio = $outW / $outH;
        $srcRatio    = $srcWidth / $srcHeight;

        if ($srcRatio > $targetRatio) {
            // Source is wider: crop the sides.
            $cropH = $srcHeight;
            $cropW = (int) round($srcHeight * $targetRatio);
        } else {
            // Source is taller: crop top/bottom.
            $cropW = $srcWidth;
            $cropH = (int) round($srcWidth / $targetRatio);
        }

        return [
            'crop' => [
                'x'      => (int) round(($srcWidth - $cropW) / 2),
                'y'      => (int) round(($srcHeight - $cropH) / 2),
                'width'  => $cropW,
                'height' => $cropH,
            ],
            'output' => ['width' => $outW, 'height' => $outH],
        ];
    }

    /**
     * Crop specs for every network a post targets.
     *
     * @param  array<int, string>  $providers
     * @return array<string, array<string, mixed>>
     */
    public function specsFor(array $providers, int $srcWidth, int $srcHeight): array
    {
        $specs = [];

        foreach ($providers as $provider) {
            $specs[$provider] = $this->specFor($provider, $srcWidth, $srcHeight);
        }

        return $specs;
    }
}
