<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipologiaUnidad extends Model
{
    use HasFactory;

    protected $table = 'tipologias_unidades';

    protected $fillable = [
        'idtipologia_unidad', 'tipologia_unidad', 'clave_tipologia'
    ];

    protected $primaryKey = 'idtipologia_unidad';

    public $timestamps = false;
}
