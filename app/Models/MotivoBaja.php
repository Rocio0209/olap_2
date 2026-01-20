<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotivoBaja extends Model
{
    use HasFactory;

    protected $table = 'motivos_baja';

    protected $fillable = [
        'idmotivo_baja', 'motivo_baja'
    ];

    protected $primaryKey = 'idmotivo_baja';

    public $timestamps = false;

}
