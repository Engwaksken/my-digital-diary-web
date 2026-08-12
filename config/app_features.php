<?php

/**
 * The master list of app modules shown on the dashboard's "Highlights"
 * section (see DashboardController) and used to build the feature
 * checklist on the admin subscription-plan form. A plan whose
 * `features` column is empty/null shows ALL of these — restricting is
 * opt-in per plan, not the default.
 */
return [
    'plans' => ['label' => 'Plans', 'description' => 'Set goals and track progress against them.', 'icon' => 'fa-solid fa-list-check', 'color' => 'indigo', 'route' => 'plans.index'],
    'finance' => ['label' => 'Financial Tracking', 'description' => 'Income, expenses, budgets, debts, and savings goals in one place.', 'icon' => 'fa-solid fa-sack-dollar', 'color' => 'emerald', 'route' => 'expenses.index'],
    'health' => ['label' => 'Health & Activity', 'description' => 'Log diet, exercise, sleep, and checkups without a separate app.', 'icon' => 'fa-solid fa-heart-pulse', 'color' => 'rose', 'route' => 'diet-logs.index'],
    'reminders' => ['label' => 'Reminders', 'description' => 'Never miss a task, bill, or follow-up again.', 'icon' => 'fa-solid fa-bell', 'color' => 'amber', 'route' => 'reminders.index'],
    'meetings' => ['label' => 'Meetings', 'description' => 'Schedule meetings, record them, and get AI-generated summaries.', 'icon' => 'fa-solid fa-calendar-days', 'color' => 'blue', 'route' => 'meetings.index'],
    'signature' => ['label' => 'Document Signing', 'description' => 'Sign documents digitally with drag-and-drop signature placement.', 'icon' => 'fa-solid fa-signature', 'color' => 'violet', 'route' => 'signature.show'],
    'business-card' => ['label' => 'Digital Business Card', 'description' => 'A shareable profile with a QR code — no printing needed.', 'icon' => 'fa-solid fa-id-card', 'color' => 'teal', 'route' => 'business-card.edit'],
    'projects' => ['label' => 'Projects & Tasks', 'description' => 'Break big goals into projects with their own task lists.', 'icon' => 'fa-solid fa-diagram-project', 'color' => 'cyan', 'route' => 'projects.index'],
    'ai-planner' => ['label' => 'AI Planner', 'description' => "Get an AI-generated plan based on your own tracked data.", 'icon' => 'fa-solid fa-robot', 'color' => 'purple', 'route' => 'ai-plans.index'],
    'reports' => ['label' => 'Reports', 'description' => 'A personal report of everything you have logged, ready to download.', 'icon' => 'fa-solid fa-chart-line', 'color' => 'slate', 'route' => 'report.download'],
    'relationships' => ['label' => 'Relationships & Network', 'description' => 'Keep track of the people who matter, personally and professionally.', 'icon' => 'fa-solid fa-people-arrows', 'color' => 'pink', 'route' => 'network-contacts.index'],
    'education' => ['label' => 'Education', 'description' => 'Track courses, certifications, and learning goals.', 'icon' => 'fa-solid fa-graduation-cap', 'color' => 'orange', 'route' => 'education-plans.index'],
    'spiritual' => ['label' => 'Spiritual Growth', 'description' => 'Log prayer, meditation, and other practices that matter to you.', 'icon' => 'fa-solid fa-hands-praying', 'color' => 'lime', 'route' => 'spiritual-practices.index'],
];
