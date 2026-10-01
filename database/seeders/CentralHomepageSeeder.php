<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomepageConfig;
use App\Models\HomepageSection;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CentralHomepageSeeder extends Seeder
{
    public function run(): void
    {
        $clientKey = config('services.storefront.client_key');
        $logoPath = "homepage/{$clientKey}/site/central-smoke-distro-logo.gif";
        $navigation = json_decode(file_get_contents(database_path('data/central-navigation.json')), true, 512, JSON_THROW_ON_ERROR);

        $config = HomepageConfig::updateOrCreate(['client_key' => $clientKey], [
            'label' => 'Central Smoke Distro',
            'is_active' => true,
            'site_logo_path' => Storage::disk('public')->exists($logoPath) ? $logoPath : null,
            'site_settings' => [
                'site_name' => 'Central Smoke Distro',
                'logo_url' => null,
                'catalog_background_logo_url' => null,
                'phone' => '+1 (914) 539-5580',
                'email' => 'info@centralsmokedistro.com',
                'address' => 'Brooklyn - Portland - Chicago',
                'search_placeholder' => 'Search for products, brands or categories',
                'login_text' => 'Login',
                'register_text' => 'Register for Wholesale',
                'sale_label' => 'On Sale',
                'sale_url' => '/sale',
                'newsletter_enabled' => true,
                'newsletter_title' => 'Sign up to Newsletter',
                'newsletter_placeholder' => 'Enter your email address',
                'newsletter_button_text' => 'Sign up',
                'seo_title' => 'Disposable Vape Distributor in White Plains - Central Smoke Distro',
                'seo_description' => 'Central Smoke Distro is a wholesale distributor of disposable vapes and smoke shop essentials in White Plains.',
                'homepage_heading' => 'Wholesale Disposable Vapes and Smoke Shop Essentials',
                'footer_copyright' => 'Central Smoke Distribution',
                'footer_legal_notice' => 'Proudly Powered by Brainbean Technolabs',
                'footer_assistance_title' => 'Need Assistance? Call us!',
                'footer_whatsapp_notice' => 'Text or Whatsapp Available 24/7!',
                'support_email' => 'support@centralsmokedistro.com',
                'business_hours_title' => 'Business Hours',
                'view_all_label' => 'View all',
                'all_brands_label' => 'All brands',
                'shop_by_category_title' => 'Shop by category',
                'featured_brands_title' => 'Featured brands',
                'new_arrivals_title' => 'New arrivals',
                'sold_out_label' => 'Sold out',
                'login_to_buy_label' => 'Login to buy',
                'wholesale_label' => 'Wholesale',
                'price_unavailable_label' => 'Price N/A',
                'currency_code' => 'USD',
                'nav_groups' => $navigation,
                'footer_columns' => [
                    ['title' => 'Find It Fast', 'links' => [
                        ['label' => 'Cigar Accessories', 'url' => '/shop?search=cigar%20accessories'],
                        ['label' => 'Cleaning Products', 'url' => '/shop?search=cleaning'],
                        ['label' => 'Detox Supplements and Health', 'url' => '/shop?search=detox'],
                    ]],
                    ['title' => 'Navigation', 'links' => [
                        ['label' => 'Wishlist', 'url' => '/wishlist'],
                    ]],
                    ['title' => 'Customer Support', 'links' => [
                        ['label' => 'My Account', 'url' => '/account/profile'],
                        ['label' => 'Track your Order', 'url' => '/orders'],
                        ['label' => 'Category Directory', 'url' => '/shop'],
                        ['label' => 'Contact Us', 'url' => 'mailto:support@centralsmokedistro.com'],
                    ]],
                ],
                'business_hours' => [
                    'Monday: 9:00 AM - 7:00 PM', 'Tuesday: 9:00 AM - 7:00 PM',
                    'Wednesday: 9:00 AM - 7:00 PM', 'Thursday: 9:00 AM - 7:00 PM',
                    'Friday: 9:00 AM - 7:00 PM', 'Saturday: 10:00 AM - 5:00 PM',
                    'Sunday: Closed',
                ],
            ],
        ]);

        $baseProducts = Product::query()->where('is_active', true)->whereNull('parent_id');
        $newest = (clone $baseProducts)->latest('id')->limit(14)->pluck('id')->all();
        $trending = (clone $baseProducts)->where('in_stock', true)->whereNotIn('id', $newest)->latest('updated_at')->limit(14)->pluck('id')->all();

        // Mirrors the supplied Central Smoke Distro homepage from top to bottom.
        $sections = [
            ['type' => 'hero', 'title' => 'Hero Main Slider', 'settings' => ['interval_ms' => 5000]],
            ['type' => 'hero', 'title' => 'Hero Side Slider', 'settings' => ['interval_ms' => 6000]],
            ['type' => 'brand_showcase', 'title' => 'Featured Brands', 'settings' => []],
            ['type' => 'banner', 'title' => 'Promotions', 'settings' => []],
            ['type' => 'featured_category', 'title' => 'Our Categories', 'settings' => [
                'category_id' => Category::where('name', 'E-Juice')->value('id'),
                'product_limit' => 8,
            ]],
            ['type' => 'product_carousel', 'title' => 'New Arrivals', 'settings' => ['limit' => 14, 'product_ids' => $newest]],
            ['type' => 'banner', 'title' => 'Continue Shopping', 'settings' => []],
            ['type' => 'product_carousel', 'title' => 'Top Trending Products', 'settings' => ['limit' => 14, 'product_ids' => $trending]],
        ];

        $managed = [];
        foreach ($sections as $order => $section) {
            $record = $config->sections()->updateOrCreate(
                ['title' => $section['title']],
                $section + ['sort_order' => $order, 'is_active' => true],
            );
            $managed[$section['title']] = $record;
        }

        // Keep any older CMS sections and their uploads recoverable, but hide blocks
        // that do not exist in the supplied reference homepage.
        $config->sections()->whereNotIn('id', collect($managed)->pluck('id'))->update(['is_active' => false]);

        $this->syncItems($managed['Featured Brands'], [
            $this->item('heading', 'Featured Brands', 'https://centralsmokedistro.com/wp-content/uploads/2024/05/slider-1.webp', '/brands'),
            $this->item('brand', 'Just CBD', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Just-Cbd_3.mp4', '/brand/147'),
            $this->item('brand', 'MIT45', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Mit45-1.mp4', '/brands'),
            $this->item('brand', 'Puffco', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/puffco-1.mp4', '/brand/219'),
            $this->item('brand', 'Geek Bar', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Geek-Bar.mp4', '/brand/374'),
            $this->item('brand', 'OPMS', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/OPMS.mp4', '/brands'),
            $this->item('brand', 'Coastal Clouds', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Coastal-Clouds.mp4', '/brand/50'),
            $this->item('brand', 'Jubi', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Jubi_1-1.mp4', '/brands'),
            $this->item('brand', 'Smok', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Smoke.mp4', '/brand/240'),
            $this->item('brand', 'Formula 420', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Formula-420_2.mp4', '/brand/96'),
            $this->item('brand', 'Raw', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Raw.mp4', '/brand/222'),
            $this->item('brand', 'Blazy Susan', 'https://centralsmokedistro.com/wp-content/uploads/2026/06/Blazy-Susan.mp4', '/brands'),
        ]);

        $this->syncItems($managed['Promotions'], [
            $this->item('content', 'Infuzd Kratom Shots', 'https://centralsmokedistro.com/wp-content/uploads/2025/05/Banner-Infuzd-1.jpg', '/shop?search=infuzd'),
            $this->item('content', '7-Hydroxy', 'https://centralsmokedistro.com/wp-content/uploads/2026/09/7-hydroxybanner.jpg', '/shop?search=7-hydroxy'),
            $this->item('content', 'E-Liquid Collection', 'https://centralsmokedistro.com/wp-content/uploads/2025/05/IMG-20250502-WA0003.jpg', '/shop?search=e-liquid'),
            $this->item('content', 'Vaporizers', 'https://centralsmokedistro.com/wp-content/uploads/2025/05/IMG-20250430-WA0000.jpg', '/shop?search=vaporizer'),
        ]);

        $this->syncItems($managed['Our Categories'], [
            $this->item('heading', 'Our Categories', 'https://centralsmokedistro.com/wp-content/uploads/2024/05/our-categories.webp', '/shop'),
            $this->item('content', 'Zenith E-Liquids', 'https://centralsmokedistro.com/wp-content/uploads/2024/12/ZENITH-E-LIQUIED-1-1-png.webp', '/shop?search=zenith'),
            $this->item('content', 'Zig-Zag', 'https://centralsmokedistro.com/wp-content/uploads/2024/05/zig-zag.webp', '/shop?search=zig-zag'),
            $this->item('content', 'Ooze', 'https://centralsmokedistro.com/wp-content/uploads/2024/05/ooze.webp', '/shop?search=ooze'),
            $this->item('content', 'Raw', 'https://centralsmokedistro.com/wp-content/uploads/2024/05/raw.webp', '/brand/222'),
        ]);

        $this->syncItems($managed['New Arrivals'], [
            $this->item('heading', 'New Arrivals', 'https://centralsmokedistro.com/wp-content/uploads/2024/05/new-arrivals.webp', '/new'),
        ]);

        $this->syncItems($managed['Continue Shopping'], [
            $this->item('content', 'Disposables', 'https://centralsmokedistro.com/wp-content/uploads/2024/05/image-3-5.webp', '/shop?search=disposables'),
            $this->item('content', 'Nicotine Disposable', 'https://centralsmokedistro.com/wp-content/uploads/2025/05/Banner-940x236-1.jpg', '/shop?search=nicotine%20disposable'),
            $this->item('content', '7-Hydroxy Products', 'https://centralsmokedistro.com/wp-content/uploads/2026/09/7-hydroxy-2-banner.jpg', '/shop?search=7-hydroxy'),
        ]);

        $this->syncItems($managed['Top Trending Products'], [
            $this->item('heading', 'Top Trending Products', 'https://centralsmokedistro.com/wp-content/uploads/2026/03/image-4-2.webp', '/shop'),
        ]);

        $this->command?->info("Synchronized homepage config '{$clientKey}' with ".count($sections).' reference sections.');
    }

    private function item(string $kind, string $title, string $url, string $link): array
    {
        return compact('kind', 'title', 'url', 'link');
    }


    private function syncItems(HomepageSection $section, array $items): void
    {
        $keep = [];

        foreach ($items as $order => $item) {
            $values = [
                'alt_text' => $item['title'],
                'link_url' => $item['link'],
                'sort_order' => $order,
                'is_active' => true,
            ];

            if ($item['kind'] === 'heading') {
                $values['desktop_image_external_url'] = $item['url'];
                $values['desktop_image_path'] = $this->storeHeadingBanner($section, $item['title'], $item['url']);
            } elseif ($item['kind'] === 'brand' && str_ends_with(strtolower(parse_url($item['url'], PHP_URL_PATH)), '.mp4')) {
                $values['desktop_image_path'] = null;
                $values['desktop_image_external_url'] = null;
                $values['video_path'] = $this->storeBrandVideo($section, $item['title'], $item['url']);
                $values['video_external_url'] = $item['url'];
            } else {
                $values['desktop_image_external_url'] = $item['url'];
            }

            $record = $section->items()->updateOrCreate(
                ['kind' => $item['kind'], 'title' => $item['title']],
                $values,
            );
            $keep[] = $record->id;
        }

        $section->items()->whereNotIn('id', $keep)->update(['is_active' => false]);
    }

    private function storeBrandVideo(HomepageSection $section, string $title, string $url): string
    {
        $path = "homepage/central/{$section->id}/brands/".Str::slug($title).'.mp4';

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        $request = Http::connectTimeout(10)->timeout(90)
            ->withHeaders(['User-Agent' => 'CentralSmokeDistro/1.0']);

        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get($url);
        $contentType = strtolower(trim(explode(';', $response->header('Content-Type', ''))[0]));

        if (! $response->successful() || ! in_array($contentType, ['video/mp4', 'application/octet-stream'], true) || strlen($response->body()) > 30 * 1024 * 1024) {
            throw new \RuntimeException("Unable to import brand video '{$title}' from {$url}.");
        }

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function storeHeadingBanner(HomepageSection $section, string $title, string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'webp';
        $path = "homepage/central/{$section->id}/headings/".Str::slug($title).'.'.$extension;

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        $request = Http::connectTimeout(5)
            ->timeout(30)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders(['User-Agent' => 'CentralSmokeDistro/1.0']);

        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get($url);
        $contentType = strtolower(trim(explode(';', $response->header('Content-Type', ''))[0]));

        if (! $response->successful() || ! str_starts_with($contentType, 'image/') || strlen($response->body()) > 5 * 1024 * 1024) {
            throw new \RuntimeException("Unable to import heading banner '{$title}' from {$url}.");
        }

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }
}
