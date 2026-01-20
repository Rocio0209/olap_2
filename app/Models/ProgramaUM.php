<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramaUM extends Model
{
    use HasFactory;

    protected $table = 'programas_um';

    protected $fillable = [
        'idprograma_um', 'programa_um'
    ];

    protected $casts = [
        'idprograma_um' => 'string',
    ];

    protected $primaryKey = 'idprograma_um';

    public $timestamps = false;

}
