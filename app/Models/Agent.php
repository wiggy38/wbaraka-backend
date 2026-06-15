<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'agents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_imf',
        'nom',
        'email',
        'password',
        'role',
        'statut',
    ];

    protected $hidden = [
        'password',
    ];

    public function imf(): BelongsTo
    {
        return $this->belongsTo(Imf::class, 'id_imf', 'id');
    }
}
