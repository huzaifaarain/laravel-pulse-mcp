<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Laravel\Sanctum\HasApiTokens;
use Workbench\Database\Factories\SanctumUserFactory;

class SanctumUser extends User
{
    use HasApiTokens;

    protected $table = 'users';

    protected static function newFactory(): SanctumUserFactory
    {
        return SanctumUserFactory::new();
    }
}
