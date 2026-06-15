<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasUuids;

    protected $fillable = ['ordre', 'image_url', 'titre', 'lien', 'actif'];

    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];
}
