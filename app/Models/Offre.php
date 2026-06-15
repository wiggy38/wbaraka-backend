<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offre extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'offres';

    protected $primaryKey = 'id_offre';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_imf',
        'nom_produit',
        'taux_interet_mensuel',
        'montant_min',
        'montant_max',
        'duree_min_mois',
        'duree_max_mois',
        'frais_dossier',
        'assurance',
        'garantie_requise',
        'delai_traitement_jours',
        'cible_specifique',
        'zones_couverture',
        'statut',
        'motif_rejet',
        'date_mise_a_jour',
    ];

    protected function casts(): array
    {
        return [
            'cible_specifique'  => 'array',
            'zones_couverture'  => 'array',
            'date_mise_a_jour'  => 'datetime',
        ];
    }

    public function imf(): BelongsTo
    {
        return $this->belongsTo(Imf::class, 'id_imf', 'id');
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class, 'id_offre', 'id_offre');
    }
}
