<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NivelAtencion extends Model
{
    use HasFactory;

    protected $table = 'niveles_atencion';

    protected $fillable = [
        'idnivel_atencion', 'nivel_atencion'
    ];

    protected $primaryKey = 'idnivel_atencion';

    public $timestamps = false;

}
