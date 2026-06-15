<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Simulation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'simulations';

    protected $primaryKey = 'id_simulation';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_utilisateur',
        'id_offre',
        'montant_emprunte',
        'duree_mois',
        'taux_utilise',
        'cout_total',
        'mensualite',
        'tableau_amortissement',
        'date_creation',
    ];

    protected function casts(): array
    {
        return [
            'tableau_amortissement' => 'array',
            'date_creation'         => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_utilisateur', 'id');
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'id_offre', 'id_offre');
    }
}
