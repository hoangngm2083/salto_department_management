<?php

namespace App\Enums;

enum RoleChangeMode: string
{
    case Add = 'add';
    case Replace = 'replace';
    case Remove = 'remove';
}
