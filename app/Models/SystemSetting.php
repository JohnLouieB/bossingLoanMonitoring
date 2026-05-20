<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'monthly_server_amount',
        'yearly_domain_amount',
        'export_csv_email',
    ];

    public static function current(): self
    {
        return self::firstOrCreate([], [
            'monthly_server_amount' => null,
            'yearly_domain_amount' => null,
            'export_csv_email' => null,
        ]);
    }
}
