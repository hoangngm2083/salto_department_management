<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HR Department Slug
    |--------------------------------------------------------------------------
    |
    | Identifies which department is Human Resources. An employee's
    | manager_employee_id must reference someone in this department -
    | direct-manager duties in this system belong to HR staff, not to the
    | employee's own functional department head.
    |
    */

    'hr_slug' => env('HR_DEPARTMENT_SLUG', 'phong-nhan-su'),

];
