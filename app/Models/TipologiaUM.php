<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipologiaUM extends Model
{
    use HasFactory;

    protected $table = 'tipologias_um';

    protected $fillable = [
        'idtipologia_um', 'tipologia_um'
    ];

    protected $primaryKey = 'idtipologia_um';

    public $timestamps = false;

}
