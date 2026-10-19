<?php

return [
    'roles' => ['admin' => 'Administrator', 'curator' => 'Curator', 'visitor' => 'Visitor'],
    'statuses' => ['draft' => 'Draft', 'published' => 'Published'],
    'hotspot_types' => ['info' => 'Information', 'scene' => 'Go to room', 'artifact' => 'Artifact'],
    'difficulties' => ['de' => 'Easy', 'vua' => 'Moderate', 'kho' => 'Hard'],
    'tour_modes' => ['free' => 'Free roam', 'guided' => 'Guided', 'both' => 'Free & guided'],
    'package_types' => ['single' => 'Single ticket', 'membership' => 'Membership'],
    'visit_day_statuses' => ['open' => 'Available', 'sold_out' => 'Sold out', 'closed' => 'Closed'],
    'order_statuses' => [
        'pending' => 'Awaiting payment', 'paid' => 'Paid', 'cancelled' => 'Cancelled', 'expired' => 'Hold expired', 'refunded' => 'Refunded',
    ],
    'payment_statuses' => [
        'initiated' => 'Initiated', 'succeeded' => 'Succeeded', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ],
    'payment_methods' => ['card' => 'Bank card', 'bank' => 'Bank transfer', 'ewallet' => 'E-wallet'],
    'ticket_statuses' => ['valid' => 'Valid', 'used' => 'Used', 'void' => 'Void'],
    'membership_statuses' => ['active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled'],
    'review_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
    'guestbook_statuses' => ['pending' => 'Pending', 'approved' => 'Visible', 'hidden' => 'Hidden'],
    'contact_statuses' => ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied'],
    'blog_categories' => ['kham_pha' => 'Discover', 'su_kien' => 'Events', 'khoa_hoc' => 'Science'],
    'tag_groups' => ['chat_lieu' => 'Material', 'nguon_goc' => 'Origin', 'nhom_sinh_vat' => 'Organism group', 'chu_de' => 'Theme', 'phong' => 'Gallery'],
    'media_types' => ['image' => 'Image', 'audio' => 'Audio', 'video' => 'Video', 'panorama' => '360° image', 'document' => 'Document'],
    'devices' => ['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'],
    'tiles_states' => ['none' => 'Not generated', 'pending' => 'Pending', 'running' => 'Generating', 'done' => 'Done', 'error' => 'Error'],
    'event_types' => [
        'scene_enter' => 'Room enter', 'scene_leave' => 'Room leave', 'hotspot_click' => 'Hotspot click', 'tour_start' => 'Tour start',
        'tour_step' => 'Tour step', 'tour_complete' => 'Tour complete', 'tour_abandon' => 'Tour abandon', 'artifact_view' => 'Artifact view',
        'audio_play' => 'Audio play',
    ],
    'taxa' => [
        'dinosauria' => 'Dinosaurs', 'mammalia' => 'Mammals', 'aves' => 'Birds', 'reptilia' => 'Reptiles', 'amphibia' => 'Amphibians',
        'pisces' => 'Fish', 'insecta' => 'Insects', 'arthropoda' => 'Arthropods', 'mollusca' => 'Molluscs', 'plantae' => 'Plants',
        'fossil_plant' => 'Fossil plants', 'mineral' => 'Minerals', 'meteorite' => 'Meteorites', 'hominini' => 'Hominins', 'other' => 'Other',
        'crustacea' => 'Crustaceans', 'fossil_invertebrate' => 'Fossil invertebrates', 'fossil_vertebrate' => 'Fossil vertebrates',
        'fungi' => 'Fungi', 'human' => 'Humans', 'rock' => 'Rocks', 'invertebrata' => 'Invertebrates',
    ],
    'licenses' => [
        'cc_by_sa_3' => 'CC BY-SA 3.0', 'cc_by_sa_4' => 'CC BY-SA 4.0', 'cc_by_sa_2' => 'CC BY-SA 2.0', 'cc_by_3' => 'CC BY 3.0',
        'cc_by_4' => 'CC BY 4.0', 'cc_by_2' => 'CC BY 2.0', 'cc0' => 'CC0 (public domain)', 'public_domain' => 'Public domain',
        'cc_by' => 'CC BY', 'cc_by_sa' => 'CC BY-SA', 'cc_by_nc' => 'CC BY-NC', 'all_rights_reserved' => 'All rights reserved',
        'simulated' => 'Simulated illustration (by the team)', 'other' => 'Other',
    ],
    'audio_locales' => ['vi' => 'Tiếng Việt', 'en' => 'English', 'de' => 'Deutsch', 'nl' => 'Nederlands'],
];
