<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoVialidad extends Model
{
    use HasFactory;

    protected $table = 'tipos_vialidades';

    protected $fillable = [
        'idtipo_vialidad', 'tipo_vialidad'
    ];

    protected $primaryKey = 'idtipo_vialidad';

    public $timestamps = false;
}
