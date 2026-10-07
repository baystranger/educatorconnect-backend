<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMINISTRATOR = 'administrator';
    case CHILDCARE_CENTRE = 'childcare_centre';
    case EDUCATOR = 'educator';
    case PROFESSIONAL = 'professional';
}
