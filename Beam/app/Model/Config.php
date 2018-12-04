<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'value',
        'description',
        'type',
        'sorting',
        'autoload',
        'locked',
    ];

    protected $casts = [
        'sorting' => 'integer',
        'autoload' => 'boolean',
        'locked' => 'boolean',
    ];

    public static function loadByName(string $name)
    {
        $result = Config::where('name', $name)->first();
        if (!$result) {
            throw new \Exception("missing configuration for '$name'");
        }

        switch (mb_strtolower($result->type)) {
            case 'double':
                return (double) $result->value;
            case 'float':
                return (float) $result->value;
            case 'int':
            case 'integer':
                return (int) $result->value;
            case 'bool':
            case 'boolean':
                return (bool) $result->value;
            default:
                return $result->value;
        }
    }
}
