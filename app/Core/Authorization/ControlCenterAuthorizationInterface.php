<?php

namespace App\Core\Authorization;

interface ControlCenterAuthorizationInterface
{
    /**
     * @return array{id:int,email:string,displayName:string,sourceRole:string,platformRole:string}
     */
    public function authorize(string $permission): array;
}
