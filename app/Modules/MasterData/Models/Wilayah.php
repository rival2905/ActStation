<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wilayah extends Model
{
    protected $table = 'wilayah';

    protected $primaryKey = 'kode';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'nama',
    ];

    public function kodepos(): HasOne
    {
        return $this->hasOne(WilayahKodepos::class, 'kode', 'kode');
    }
}
