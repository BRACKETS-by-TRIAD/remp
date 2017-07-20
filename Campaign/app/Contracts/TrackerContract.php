<?php

namespace App\Contracts;

interface TrackerContract
{
    public function event(
        string $beamToken,
        string $category,
        string $action,
        string $url,
        string $ipAddress,
        string $userAgent,
        string $userId,
        array $fields
    ): void;
}
