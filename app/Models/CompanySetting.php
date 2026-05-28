<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'email',
        'phone',
        'address',
        'facebook_url',
        'instagram_url',
        'whatsapp',
        'hero_title',
        'hero_subtitle',
        'hero_image_path',
        'hero_image_2_path',
        'hero_image_3_path',
        'about_title',
        'about_description',
        'about_image_path',
        'footer_text',
        'logo_login_path',
        'logo_sidebar_path',
        'logo_sidebar_compact_path',
        'logo_landing_path',
    ];

    protected $appends = [
        'hero_image_url',
        'hero_image_2_url',
        'hero_image_3_url',
        'about_image_url',
        'logo_login_url',
        'logo_sidebar_url',
        'logo_sidebar_compact_url',
        'logo_landing_url',
    ];

    public function getHeroImageUrlAttribute(): ?string
    {
        return $this->hero_image_path ? asset('storage/' . $this->hero_image_path) : null;
    }

    public function getHeroImage2UrlAttribute(): ?string
    {
        return $this->hero_image_2_path ? asset('storage/' . $this->hero_image_2_path) : null;
    }

    public function getHeroImage3UrlAttribute(): ?string
    {
        return $this->hero_image_3_path ? asset('storage/' . $this->hero_image_3_path) : null;
    }

    public function getAboutImageUrlAttribute(): ?string
    {
        return $this->about_image_path ? asset('storage/' . $this->about_image_path) : null;
    }

    public function getLogoLoginUrlAttribute(): ?string
    {
        return $this->logo_login_path ? asset('storage/' . $this->logo_login_path) : null;
    }

    public function getLogoSidebarUrlAttribute(): ?string
    {
        return $this->logo_sidebar_path ? asset('storage/' . $this->logo_sidebar_path) : null;
    }

    public function getLogoSidebarCompactUrlAttribute(): ?string
    {
        return $this->logo_sidebar_compact_path ? asset('storage/' . $this->logo_sidebar_compact_path) : null;
    }

    public function getLogoLandingUrlAttribute(): ?string
    {
        return $this->logo_landing_path ? asset('storage/' . $this->logo_landing_path) : null;
    }
}
