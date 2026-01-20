<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKeyTrait;
use App\Models\Estado;

class Regional extends Model
{
    use HasFactory;
    use HasCompositePrimaryKeyTrait;
    use \Awobaz\Compoships\Compoships;
    use \Staudenmeir\EloquentHasManyDeep\HasRelationships;

    protected $table = 'regionales';

    protected $fillable = [
        'idestado', 'idregional', 'regional'
    ];

    protected $primaryKey = ['idestado', 'idregional'];

    public $timestamps = false;

    public $incrementing = false;

    public function estado()
    {
        return $this->belongsTo(Estado::class,'idestado','idestado');
    }

    public function municipios() {
        return $this->hasMany(Municipio::class, ['idestado', 'idregional'], ['idestado', 'idregional']);
    }

    public function unidades() {
        return $this->hasManyDeepFromRelations($this->municipios(), (new Municipio())->unidades());
    }
}
