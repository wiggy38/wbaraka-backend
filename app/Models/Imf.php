<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Imf extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'imfs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nom',
        'logo_url',
        'description',
        'zones_couverture',
        'statut',
        'email_contact',
        'telephone',
    ];

    protected function casts(): array
    {
        return [
            'zones_couverture' => 'array',
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'id_imf', 'id');
    }

    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'id_imf', 'id');
    }
}
