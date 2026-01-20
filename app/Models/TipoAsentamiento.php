<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoAsentamiento extends Model
{
    use HasFactory;

    protected $table = 'tipos_asentamientos';

    protected $fillable = [
        'idtipo_asentamiento', 'tipo_asentamiento'
    ];

    protected $primaryKey = 'idtipo_asentamiento';

    public $timestamps = false;
}
