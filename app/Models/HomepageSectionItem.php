<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSectionItem extends Model
{
    protected $fillable = [
        'homepage_section_id',
        'kind',
        'title',
        'desktop_image_path',
        'desktop_image_external_url',
        'mobile_image_path',
        'mobile_image_external_url',
        'video_path',
        'video_external_url',
        'pdf_path',
        'alt_text',
        'link_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }
}
