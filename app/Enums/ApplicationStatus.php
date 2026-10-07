<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case NEW = 'new';
    case REVIEWED = 'reviewed';
    case SHORTLISTED = 'shortlisted';
    case INTERVIEW = 'interview';
    case OFFERED = 'offered';
    case HIRED = 'hired';
    case NOT_SELECTED = 'not_selected';
    case WITHDRAWN = 'withdrawn';
}
