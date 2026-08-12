<?php

namespace App\Enums;

enum NotificationReadStatus: string
{
    case Read = 'read';
    case Unread = 'unread';
}
