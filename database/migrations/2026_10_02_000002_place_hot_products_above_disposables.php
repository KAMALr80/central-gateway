<?php

use App\Models\HomepageSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The "Hot Products" video heading introduces the Disposables banners, so it must
// sit directly above the "Continue Shopping" section. Both shared a sort_order on
// some installs, which let the older section win the tie and render first.
return new class extends Migration
{
    public function up(): void
    {
        $pairs = DB::table('homepage_sections as hot')
            ->join('homepage_sections as disposables', 'disposables.homepage_config_id', '=', 'hot.homepage_config_id')
            ->where('hot.title', 'Hot Products')
            ->where('disposables.title', 'Continue Shopping')
            ->select('hot.id as hot_id', 'disposables.id as disposables_id', 'hot.homepage_config_id as config_id')
            ->get();

        foreach ($pairs as $pair) {
            DB::transaction(function () use ($pair) {
                $ordered = HomepageSection::where('homepage_config_id', $pair->config_id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->reject(fn ($section) => $section->id === $pair->hot_id)
                    ->values();

                $position = $ordered->search(fn ($section) => $section->id === $pair->disposables_id);
                $ordered->splice($position, 0, [HomepageSection::find($pair->hot_id)]);

                foreach ($ordered as $index => $section) {
                    if ($section->sort_order !== $index) {
                        $section->update(['sort_order' => $index]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Ordering fix only; the previous tie is not worth restoring.
    }
};
