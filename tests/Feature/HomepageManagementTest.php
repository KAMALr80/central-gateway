<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageConfig;
use App\Models\HomepageSectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('persists category and brand selections from the homepage editor', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $config = HomepageConfig::create(['client_key' => 'central', 'label' => 'Central', 'is_active' => true]);
    $category = Category::create(['name' => 'Disposable Vapes', 'is_active' => true]);
    $brand = Brand::create(['name' => 'Featured Brand', 'is_active' => true]);

    $this->actingAs($admin)->post("/admin/homepage/{$config->id}/sections", [
        'type' => 'featured_category',
        'title' => 'Featured Category',
        'is_active' => true,
        'settings' => ['category_id' => $category->id, 'product_limit' => 12],
    ])->assertRedirect();

    $this->actingAs($admin)->post("/admin/homepage/{$config->id}/sections", [
        'type' => 'brand_showcase',
        'title' => 'Brands',
        'is_active' => true,
        'settings' => ['brand_ids' => [$brand->id]],
    ])->assertRedirect();

    expect($config->sections()->where('type', 'featured_category')->firstOrFail()->settings)
        ->toBe(['category_id' => $category->id, 'product_limit' => 12])
        ->and($config->sections()->where('type', 'brand_showcase')->firstOrFail()->settings)
        ->toBe(['brand_ids' => [$brand->id]]);
});

it('returns only active homepage content in configured order', function () {
    $config = HomepageConfig::create(['client_key' => 'new-england', 'label' => 'New England', 'is_active' => true]);
    $catalog = $config->sections()->create(['type' => 'catalog_showcase', 'title' => 'Catalogs', 'sort_order' => 2, 'is_active' => true, 'settings' => ['eyebrow' => 'Distro']]);
    $hero = $config->sections()->create(['type' => 'hero', 'title' => 'Hero', 'sort_order' => 1, 'is_active' => true, 'settings' => []]);
    $config->sections()->create(['type' => 'banner', 'title' => 'Hidden', 'sort_order' => 0, 'is_active' => false, 'settings' => []]);

    $hero->items()->create(['kind' => 'slide', 'title' => 'First', 'desktop_image_path' => 'homepage/new-england/hero.jpg', 'alt_text' => 'Hero', 'sort_order' => 0, 'is_active' => true]);
    $hero->items()->create(['kind' => 'slide', 'title' => 'Hidden', 'desktop_image_path' => 'homepage/new-england/hidden.jpg', 'alt_text' => 'Hidden', 'sort_order' => 1, 'is_active' => false]);
    $catalog->items()->create(['kind' => 'catalog', 'title' => 'Wholesale Catalog', 'desktop_image_path' => 'homepage/new-england/cover.jpg', 'pdf_path' => 'homepage/new-england/catalog.pdf', 'alt_text' => 'Catalog cover', 'sort_order' => 0, 'is_active' => true]);

    $this->getJson('/api/homepage?client=new-england')
        ->assertOk()
        ->assertJsonPath('sections.0.id', $hero->id)
        ->assertJsonPath('sections.1.id', $catalog->id)
        ->assertJsonCount(2, 'sections')
        ->assertJsonCount(1, 'sections.0.items')
        ->assertJsonPath('sections.1.items.0.pdf_url', route('homepage.catalog.pdf', ['item' => $catalog->items()->first()->id]));
});

it('requires an administrator for homepage management', function () {
    $config = HomepageConfig::create(['client_key' => 'central', 'label' => 'Central', 'is_active' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get("/admin/homepage/{$config->id}/edit")->assertForbidden();
});

it('uploads a catalog cover and PDF for an administrator', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['email' => 'jay@brainbean.in', 'is_admin' => true]);
    $config = HomepageConfig::create(['client_key' => 'central', 'label' => 'Central', 'is_active' => true]);
    $section = $config->sections()->create(['type' => 'catalog_showcase', 'title' => 'Catalogs', 'sort_order' => 0, 'is_active' => true, 'settings' => []]);

    $response = $this->actingAs($admin)->post("/admin/homepage/{$config->id}/sections/{$section->id}/items", [
        'kind' => 'catalog',
        'title' => '2026 Catalog',
        'alt_text' => '2026 catalog cover',
        'is_active' => true,
        'desktop_image' => UploadedFile::fake()->image('cover.jpg', 420, 594),
        'pdf_file' => UploadedFile::fake()->createWithContent('catalog.pdf', '%PDF-1.4 test'),
    ]);

    $response->assertRedirect();
    $item = HomepageSectionItem::firstOrFail();
    Storage::disk('public')->assertExists($item->desktop_image_path);
    Storage::disk('public')->assertExists($item->pdf_path);
    $this->get(route('homepage.catalog.pdf', ['item' => $item->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('stores uploaded site branding and removes legacy URL fallbacks', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $config = HomepageConfig::create([
        'client_key' => 'central',
        'label' => 'Central',
        'is_active' => true,
        'site_logo_path' => 'homepage/central/site/old-logo.png',
        'catalog_background_path' => 'homepage/central/site/old-background.png',
        'site_settings' => [
            'site_name' => 'New England Distribution',
            'logo_url' => '/images/brand/old-logo.png',
            'catalog_background_logo_url' => '/images/brand/old-background.png',
        ],
    ]);
    Storage::disk('public')->put($config->site_logo_path, 'old logo');
    Storage::disk('public')->put($config->catalog_background_path, 'old background');

    $this->actingAs($admin)->patch("/admin/homepage/{$config->id}", [
        'client_key' => 'central',
        'label' => 'Central',
        'is_active' => true,
        'site_settings' => $config->site_settings,
        'site_logo' => UploadedFile::fake()->image('logo.png', 400, 400),
        'catalog_background_image' => UploadedFile::fake()->image('background.png', 1200, 800),
    ])->assertRedirect();

    $config->refresh();

    expect($config->site_settings['logo_url'])->toBeNull()
        ->and($config->site_settings['catalog_background_logo_url'])->toBeNull()
        ->and($config->site_logo_path)->not->toBe('homepage/central/site/old-logo.png')
        ->and($config->catalog_background_path)->not->toBe('homepage/central/site/old-background.png');
    Storage::disk('public')->assertExists($config->site_logo_path);
    Storage::disk('public')->assertExists($config->catalog_background_path);
    Storage::disk('public')->assertMissing('homepage/central/site/old-logo.png');
    Storage::disk('public')->assertMissing('homepage/central/site/old-background.png');
});

it('removes stored branding safely and keeps valid external fallbacks', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $config = HomepageConfig::create([
        'client_key' => 'central',
        'label' => 'Central',
        'is_active' => true,
        'site_logo_path' => 'homepage/central/site/logo.png',
        'catalog_background_path' => 'homepage/central/site/background.png',
        'site_settings' => ['site_name' => 'New England Distribution'],
    ]);
    Storage::disk('public')->put($config->site_logo_path, 'logo');
    Storage::disk('public')->put($config->catalog_background_path, 'background');

    $this->actingAs($admin)->patch("/admin/homepage/{$config->id}", [
        'client_key' => 'central',
        'label' => 'Central',
        'is_active' => true,
        'site_settings' => [
            'site_name' => 'New England Distribution',
            'logo_url' => 'https://cdn.example.com/logo.png',
            'catalog_background_logo_url' => 'https://cdn.example.com/background.png',
        ],
        'remove_site_logo' => true,
        'remove_catalog_background' => true,
    ])->assertRedirect();

    $config->refresh();

    expect($config->site_logo_path)->toBeNull()
        ->and($config->catalog_background_path)->toBeNull()
        ->and($config->site_settings['logo_url'])->toBe('https://cdn.example.com/logo.png')
        ->and($config->site_settings['catalog_background_logo_url'])->toBe('https://cdn.example.com/background.png');
    Storage::disk('public')->assertMissing('homepage/central/site/logo.png');
    Storage::disk('public')->assertMissing('homepage/central/site/background.png');
    $this->getJson('/api/homepage?client=central')
        ->assertOk()
        ->assertJsonPath('site.logo_url', 'https://cdn.example.com/logo.png')
        ->assertJsonPath('site.catalog_background_logo_url', 'https://cdn.example.com/background.png');
});

it('keeps the homepage client key immutable after creation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $config = HomepageConfig::create([
        'client_key' => 'central',
        'label' => 'Central',
        'is_active' => true,
        'site_settings' => [],
    ]);

    $this->actingAs($admin)->patch("/admin/homepage/{$config->id}", [
        'client_key' => 'changed-client',
        'label' => 'Central',
        'is_active' => true,
        'site_settings' => [],
    ])->assertSessionHasErrors('client_key');

    expect($config->fresh()->client_key)->toBe('central');
});

it('accepts a secure external image URL instead of an uploaded image', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('fake-image-content', 200, ['Content-Type' => 'image/webp'])]);
    $admin = User::factory()->create(['is_admin' => true]);
    $config = HomepageConfig::create(['client_key' => 'central', 'label' => 'Central', 'is_active' => true]);
    $section = $config->sections()->create(['type' => 'banner', 'title' => 'Promotions', 'sort_order' => 0, 'is_active' => true, 'settings' => []]);

    $this->actingAs($admin)->post("/admin/homepage/{$config->id}/sections/{$section->id}/items", [
        'kind' => 'content',
        'title' => 'Remote banner',
        'alt_text' => 'Remote promotional banner',
        'desktop_image_external_url' => 'https://example.com/banner.webp',
        'mobile_image_external_url' => 'https://example.com/banner-mobile.webp',
        'is_active' => true,
    ])->assertRedirect();

    $item = HomepageSectionItem::firstOrFail();
    expect($item->desktop_image_path)->not->toBeNull()
        ->and($item->desktop_image_external_url)->toBe('https://example.com/banner.webp');
    Storage::disk('public')->assertExists($item->desktop_image_path);

    $this->getJson('/api/homepage?client=central')
        ->assertOk()
        ->assertJsonPath('sections.0.items.0.desktop_image_url', url(Storage::disk('public')->url($item->desktop_image_path)))
        ->assertJsonPath('sections.0.items.0.mobile_image_url', url(Storage::disk('public')->url($item->mobile_image_path)));
});

it('rejects nested homepage items from another section', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $first = HomepageConfig::create(['client_key' => 'first', 'label' => 'First', 'is_active' => true]);
    $second = HomepageConfig::create(['client_key' => 'second', 'label' => 'Second', 'is_active' => true]);
    $firstSection = $first->sections()->create(['type' => 'banner', 'title' => 'First', 'sort_order' => 0, 'is_active' => true, 'settings' => []]);
    $secondSection = $second->sections()->create(['type' => 'banner', 'title' => 'Second', 'sort_order' => 0, 'is_active' => true, 'settings' => []]);
    $item = $secondSection->items()->create(['kind' => 'content', 'desktop_image_path' => 'homepage/second/banner.jpg', 'alt_text' => 'Banner', 'sort_order' => 0, 'is_active' => true]);

    $this->actingAs($admin)->delete("/admin/homepage/{$first->id}/sections/{$firstSection->id}/items/{$item->id}")->assertNotFound();
    expect($item->fresh())->not->toBeNull();
});
