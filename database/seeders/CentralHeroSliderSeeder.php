<?php

namespace Database\Seeders;

use App\Models\HomepageConfig;
use App\Models\HomepageSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sets up the storefront's two-column hero (main slider 2/3 + side slider 1/3)
 * and imports its slides into local storage so they are served by this gateway.
 */
class CentralHeroSliderSeeder extends Seeder
{
    private const MAIN_SLIDES = [
        ['url' => 'https://centralsmokedistro.com/wp-content/uploads/2026/06/raw-6.webp', 'alt' => 'Raw', 'link' => '/brand/222'],
        ['url' => 'https://centralsmokedistro.com/wp-content/uploads/2026/06/image-2.webp', 'alt' => 'Puffco Haze Collection', 'link' => '/category/289'],
    ];

    private const SIDE_SLIDES = [
        ['url' => 'https://centralsmokedistro.com/wp-content/uploads/2026/06/jubi-3-1-1.webp', 'alt' => 'Jubi', 'link' => '/brand/369'],
    ];

    public function run(): void
    {
        $config = HomepageConfig::where('client_key', config('services.storefront.client_key'))->firstOrFail();
        $heroes = $config->sections()->where('type', 'hero')->get();

        $main = $heroes->first() ?? $config->sections()->create(['type' => 'hero', 'title' => 'Hero Main Slider', 'sort_order' => 0, 'is_active' => true, 'settings' => ['interval_ms' => 5000]]);
        $main->update(['title' => 'Hero Main Slider']);

        $side = $heroes->firstWhere('id', '!=', $main->id);
        if (! $side) {
            DB::transaction(function () use ($config, $main, &$side) {
                $config->sections()->where('sort_order', '>', $main->sort_order)->increment('sort_order');
                $side = $config->sections()->create([
                    'type' => 'hero',
                    'title' => 'Hero Side Slider',
                    'sort_order' => $main->sort_order + 1,
                    'is_active' => true,
                    'settings' => ['interval_ms' => 6000],
                ]);
            });
        }

        $this->importSlides($config, $main, self::MAIN_SLIDES);
        $this->importSlides($config, $side, self::SIDE_SLIDES);
    }

    private function importSlides(HomepageConfig $config, HomepageSection $section, array $slides): void
    {
        if ($section->items()->exists()) {
            $this->command?->info("'{$section->title}' already has slides; skipped.");

            return;
        }

        foreach ($slides as $order => $slide) {
            $response = Http::withoutVerifying()->timeout(30)->withHeaders(['User-Agent' => 'CentralSmokeDistro/1.0'])->get($slide['url']);
            if (! $response->successful()) {
                $this->command?->error("Download failed ({$response->status()}): {$slide['url']}");

                continue;
            }

            $path = "homepage/{$config->client_key}/{$section->id}/".Str::slug($slide['alt']).'-'.Str::random(8).'.'.pathinfo(parse_url($slide['url'], PHP_URL_PATH), PATHINFO_EXTENSION);
            Storage::disk('public')->put($path, $response->body());

            $section->items()->create([
                'kind' => 'slide',
                'title' => $slide['alt'],
                'alt_text' => $slide['alt'],
                'link_url' => $slide['link'],
                'desktop_image_path' => $path,
                'desktop_image_external_url' => $slide['url'],
                'sort_order' => $order,
                'is_active' => true,
            ]);
            $this->command?->info("Imported '{$slide['alt']}' into '{$section->title}'.");
        }
    }
}
