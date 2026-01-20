<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institucion extends Model
{
    use HasFactory;

    protected $table = 'instituciones';

    protected $fillable = [
        'idinstitucion', 'institucion', 'descripcion_corta', 'iniciales'
    ];

    protected $primaryKey = 'idinstitucion';

    public $timestamps = false;
}
