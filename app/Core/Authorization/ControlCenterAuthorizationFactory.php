<?php

namespace App\Core\Authorization;

use App\Core\Request;
use App\Services\ControlCenterSuperAdminAuthorization;

final class ControlCenterAuthorizationFactory
{
    public static function make(Request $request): ControlCenterAuthorizationInterface
    {
        return new ControlCenterSuperAdminAuthorization($request);
    }
}
