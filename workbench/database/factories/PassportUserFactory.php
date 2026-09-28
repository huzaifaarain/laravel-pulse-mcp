<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Workbench\App\Models\PassportUser;

/**
 * @extends UserFactory
 */
class PassportUserFactory extends UserFactory
{
    /**
     * @var class-string<PassportUser>
     */
    protected $model = PassportUser::class;
}
