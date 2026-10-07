<?php

namespace App\Enums;

enum ProfileStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case PUBLISHED = 'published';
    case SUSPENDED = 'suspended';
    case ARCHIVED = 'archived';
}
