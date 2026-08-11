<?php

namespace App\Enums;

enum ApproverKind: string
{
    case DirectManager = 'direct_manager';
    case DepartmentManager = 'department_manager';
    case ProjectManager = 'project_manager';
    case SystemAdmin = 'system_admin';
    case SpecificEmployee = 'specific_employee';
    case Permission = 'permission';
}
