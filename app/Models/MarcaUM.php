<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarcaUM extends Model
{
    use HasFactory;

    protected $table = 'marcas_um';

    protected $fillable = [
        'idmarca_um', 'marca_um'
    ];

    protected $primaryKey = 'idmarca_um';

    public $timestamps = false;

}
