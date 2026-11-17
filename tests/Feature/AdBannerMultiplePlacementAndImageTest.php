<?php

namespace Tests\Feature;

use App\Models\AdBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdBannerMultiplePlacementAndImageTest extends TestCase
{
    use RefreshDatabase;
    public function test_ad_banner_supports_multiple_placements_and_active_slot_query(): void
    {
        $banner = AdBanner::create([
            'name' => 'Multi Slot Banner',
            'placement' => ['news_top', 'news_in_feed', 'home_top'],
            'type' => 'image_link',
            'image_path' => 'ad-banners/test-banner.webp',
            'target_url' => 'https://example.com',
            'is_active' => true,
        ]);

        $this->assertIsArray($banner->placement);
        $this->assertContains('news_top', $banner->placement);
        $this->assertContains('news_in_feed', $banner->placement);
        $this->assertContains('home_top', $banner->placement);

        // Test scopeActiveSlot matching
        $matchTop = AdBanner::activeSlot('news_top')->where('id', $banner->id)->first();
        $this->assertNotNull($matchTop);

        $matchFeed = AdBanner::activeSlot('news_in_feed')->where('id', $banner->id)->first();
        $this->assertNotNull($matchFeed);

        $matchHome = AdBanner::activeSlot('home_top')->where('id', $banner->id)->first();
        $this->assertNotNull($matchHome);

        $noMatch = AdBanner::activeSlot('unrelated_slot')->where('id', $banner->id)->first();
        $this->assertNull($noMatch);

        // Test resolved image URL
        $this->assertStringContainsString('storage/ad-banners/test-banner.webp', $banner->resolved_image_url);

        $banner->delete();
    }

    public function test_legacy_single_string_placement_backward_compatibility(): void
    {
        $banner = new AdBanner();
        $banner->name = 'Legacy Banner';
        // Manually set raw string in attributes to simulate legacy database record
        $banner->setRawAttributes([
            'name' => 'Legacy Banner',
            'placement' => 'match_sidebar',
            'type' => 'image_link',
            'image_url' => 'https://external-ads.com/ad.png',
            'is_active' => true,
        ]);
        $banner->save();

        $fresh = AdBanner::find($banner->id);
        $this->assertIsArray($fresh->placement);
        $this->assertEquals(['match_sidebar'], $fresh->placement);

        $matched = AdBanner::activeSlot('match_sidebar')->where('id', $banner->id)->first();
        $this->assertNotNull($matched);

        $this->assertEquals('https://external-ads.com/ad.png', $fresh->resolved_image_url);

        $banner->delete();
    }
}
