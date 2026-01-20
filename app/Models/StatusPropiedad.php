<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusPropiedad extends Model
{
    use HasFactory;

    protected $table = 'status_propiedades';

    protected $fillable = [
        'idstatus_propiedad', 'status_propiedad'
    ];

    protected $primaryKey = 'idstatus_propiedad';

    public $timestamps = false;

}
