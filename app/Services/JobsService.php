<?php

namespace DTApi\Services;

use Validator;
use Illuminate\Database\Eloquent\Model;
use DTApi\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class JobService
{


    public function isAdminOrSuperAdmin($userType)
    {
        $adminRoleId = config('roles.admin');
        $superAdminRoleId = config('roles.superadmin');

        return in_array($userType, [$adminRoleId, $superAdminRoleId]);
    }
}
