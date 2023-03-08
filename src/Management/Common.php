<?php

namespace App\Management;

class Common
{
    public const ID_SCHEMA = [
        'type' => 'integer',
        'minimum' => 1,
        'maximum' => 99999999999,
    ];

    public const REQUIRED_VARCHAR_SCHEMA = [
        'type' => 'string',
        'minLength' => 1,
        'maxLength' => 255,
    ];

    public const TEXT_SCHEMA = [
        'type' => 'string',
        'maxLength' => 4055,
    ];

    public const DATE_SCHEMA = [
        'type' => 'string',
        'format' => 'date',
    ];

    public const TIME_SCHEMA = [
        'type' => 'string',
        'format' => 'time',
        'pattern' => '^\d{2}:\d{2}:\d{2}$',
    ];

    public const YEAR_SCHEMA = [
        'type' => 'string',
        'format' => 'date',
        'pattern' => '^\d{4}$',
    ];
}