<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Workbench\App\Models\SanctumUser;

/**
 * @extends UserFactory
 */
class SanctumUserFactory extends UserFactory
{
    /**
     * @var class-string<SanctumUser>
     */
    protected $model = SanctumUser::class;
}
