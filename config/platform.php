<?php

/*
| Platform vocabulary shared by validation and the frontend (GET /api/v1/meta/options).
| Values are stable machine keys; labels are what users see.
*/

return [

    // Times in emails and seeded demo data are expressed in this zone (storage stays UTC).
    'timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Vancouver'),

    'moderation' => [
        // When true, a centre profile goes live as soon as the owner submits it.
        // When false it waits in `pending_review` for an administrator.
        'auto_publish_centres' => (bool) env('CENTRE_AUTO_PUBLISH', true),
    ],

    'account' => [
        'reactivation_window_days' => 30,
    ],

    'uploads' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'private_disk' => env('PRIVATE_MEDIA_DISK', 'local'),
        'image_max_kb' => 10240,
        'video_max_kb' => 204800,
        'resume_max_kb' => 5120,
        'document_max_kb' => 10240,
    ],

    'employment_types' => [
        'full_time' => 'Full-time',
        'part_time' => 'Part-time',
        'casual' => 'Casual',
        'substitute' => 'Substitute / On-call',
        'contract' => 'Contract',
        'practicum' => 'Practicum',
    ],

    'job_categories' => [
        'early_childhood_educator' => 'Early Childhood Educator',
        'infant_toddler_educator' => 'Infant / Toddler Educator',
        'ece_assistant' => 'ECE Assistant',
        'supervisor_manager' => 'Supervisor / Manager',
        'inclusion_support' => 'Inclusion support',
        'cook_support_staff' => 'Cook / Support staff',
    ],

    'education_requirements' => [
        'ece_certificate' => 'ECE certificate',
        'ece_assistant_certificate' => 'ECE Assistant certificate',
        'ece_diploma' => 'Diploma in ECE',
        'enrolled_ece_program' => 'Enrolled in ECE program (practicum)',
    ],

    'experience_levels' => [
        'none' => 'No experience required',
        'one_year' => '1+ year in group care',
        'three_years' => '3+ years',
        'five_years' => '5+ years',
    ],

    'certifications' => [
        'ece_certificate' => 'ECE certificate',
        'infant_toddler' => 'Infant/Toddler',
        'special_needs' => 'Special Needs',
        'first_aid_cpr' => 'First Aid & CPR',
        'criminal_record_check' => 'Criminal record check',
    ],

    'practicum_levels' => ['level_1' => 'Level 1', 'level_2' => 'Level 2', 'level_3' => 'Level 3'],

    'salary_periods' => ['hour' => 'Hour', 'year' => 'Year'],

    'application_statuses' => [
        'new' => 'New',
        'reviewed' => 'Reviewed',
        'shortlisted' => 'Shortlisted',
        'interview' => 'Interview',
        'offered' => 'Offered',
        'hired' => 'Hired',
        'not_selected' => 'Not selected',
        'withdrawn' => 'Withdrawn',
    ],

    'teaching_approaches' => [
        'play_based' => 'Play-based',
        'emergent' => 'Emergent',
        'reggio' => 'Reggio-inspired',
        'montessori' => 'Montessori',
        'nature_based' => 'Nature-based',
        'waldorf' => 'Waldorf',
    ],

    'licence_types' => [
        'group_child_care' => 'Group child care',
        'family_child_care' => 'Family child care',
        'preschool' => 'Preschool',
        'montessori' => 'Montessori',
        'school_age_care' => 'School-age care',
    ],

    'program_schedules' => [
        'full_day' => 'Full day',
        'part_day' => 'Part day',
        'part_or_full_day' => 'Part or full day',
        'extended_hours' => 'Extended hours',
        'before_after_school' => 'Before & after school',
    ],

    'program_availability' => ['spaces' => 'Spaces', 'waitlist' => 'Waitlist', 'full' => 'Full'],

    'gallery_categories' => [
        'classrooms' => 'Classrooms',
        'outdoor_play' => 'Outdoor play',
        'learning_environments' => 'Learning environments',
        'special_events' => 'Special events',
    ],

    'age_groups' => [
        'infant' => ['label' => 'Infant', 'min_months' => 0, 'max_months' => 18],
        'toddler' => ['label' => 'Toddler', 'min_months' => 18, 'max_months' => 36],
        'preschool' => ['label' => 'Preschool', 'min_months' => 36, 'max_months' => 72],
        'school_age' => ['label' => 'School age', 'min_months' => 60, 'max_months' => 156],
    ],

    // Directory filter groups → professional category slugs.
    'professional_groups' => [
        'therapy' => ['speech-language-pathologists', 'occupational-therapists', 'behaviour-consultants', 'early-intervention-specialists', 'child-psychologists', 'special-education-teachers'],
        'business' => ['licensing-consultants', 'childcare-consultants', 'bookkeepers', 'accountants', 'legal-professionals', 'insurance-brokers', 'business-coaches', 'grant-writers', 'recruiters', 'marketing-professionals', 'childcare-website-developers', 'graphic-designers'],
        'training' => ['trainers-workshop-facilitators', 'curriculum-specialists', 'ece-instructors', 'early-childhood-educators', 'nutritionists'],
        'design' => ['playground-designers', 'interior-designers', 'cleaning-companies', 'photographers'],
    ],

    'contact_methods' => ['email' => 'Email', 'phone' => 'Phone', 'text' => 'Text message'],

    'profile_visibility' => [
        'verified_centres' => 'Verified centres only',
        'public' => 'Public',
        'applied_only' => 'Only centres I apply to',
    ],

    'job_alert_frequencies' => ['daily' => 'Daily digest', 'instant' => 'As soon as posted', 'weekly' => 'Weekly summary'],

    /*
    | Notification preference keys per role. Each defaults to [email, in_app].
    */
    'notification_types' => [
        'educator' => [
            'application_updates' => ['label' => 'Application updates', 'description' => 'When a centre reviews, shortlists or responds to you', 'default' => [true, true]],
            'interview_requests' => ['label' => 'Interview requests', 'description' => 'When a centre invites you to interview', 'default' => [true, true]],
            'matching_jobs' => ['label' => 'New matching jobs', 'description' => 'Jobs within your radius and preferences', 'default' => [true, true]],
            'profile_views' => ['label' => 'Profile views', 'description' => 'When a centre views your profile', 'default' => [false, true]],
            'practicum' => ['label' => 'Practicum opportunities', 'description' => 'New placements (student mode only)', 'default' => [false, false]],
            'tips' => ['label' => 'Tips & resources', 'description' => 'Occasional guides for educators', 'default' => [false, false]],
        ],
        'childcare_centre' => [
            'new_applicants' => ['label' => 'New applicants', 'description' => 'When an educator applies to one of your postings', 'default' => [true, true]],
            'parent_inquiries' => ['label' => 'Parent inquiries', 'description' => 'Questions sent from your public profile', 'default' => [true, true]],
            'tour_requests' => ['label' => 'Tour requests', 'description' => 'When a family asks to book a tour', 'default' => [true, true]],
            'waitlist' => ['label' => 'Waitlist sign-ups', 'description' => 'When a family joins your waitlist', 'default' => [true, true]],
            'applicant_messages' => ['label' => 'Applicant messages', 'description' => 'Replies from educators you contacted', 'default' => [true, true]],
            'profile_review' => ['label' => 'Profile review', 'description' => 'When your profile changes are approved', 'default' => [true, true]],
            'tips' => ['label' => 'Tips & resources', 'description' => 'Occasional guides for centres', 'default' => [false, false]],
        ],
        'professional' => [
            'listing_review' => ['label' => 'Listing review', 'description' => 'When your directory listing or claim is reviewed', 'default' => [true, true]],
            'tips' => ['label' => 'Tips & resources', 'description' => 'Occasional guides for professionals', 'default' => [false, false]],
        ],
    ],
];
