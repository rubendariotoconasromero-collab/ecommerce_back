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
        'about_title',
        'about_description',
        'about_image_path',
        'footer_text'
    ];

    protected $appends = [
        'hero_image_url',
        'about_image_url'
    ];

    public function getHeroImageUrlAttribute()
    {
        return $this->hero_image_path ? asset('storage/' . $this->hero_image_path) : null;
    }

    public function getAboutImageUrlAttribute()
    {
        return $this->about_image_path ? asset('storage/' . $this->about_image_path) : null;
    }
}
