<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Workbench\Database\Factories\PassportUserFactory;

class PassportUser extends User implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected static function newFactory(): PassportUserFactory
    {
        return PassportUserFactory::new();
    }
}
