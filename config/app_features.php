<?php

/**
 * Master feature catalogue used by the dashboard's "Explore What's Included"
 * section and the subscription-plan feature checklist.
 */
return [
    'annual-plans' => [
        'label' => 'Annual & Monthly Plans',
        'description' => 'Set annual goals, break them into monthly plans, track progress, status, target dates and reminders.',
        'icon' => 'fa-solid fa-calendar-check', 'color' => 'indigo', 'route' => 'annual-plans.index',
    ],
    'daily-planner' => [
        'label' => 'Daily Planner & Top 3',
        'description' => 'Plan each day, prioritize your Top 3, schedule timed items, review history and receive the daily 8:00 AM digest.',
        'icon' => 'fa-solid fa-list-check', 'color' => 'blue', 'route' => 'daily-planner.index',
    ],
    'financial-planner' => [
        'label' => 'Financial Planner',
        'description' => 'Model retirement savings, monthly contributions, expected returns, inflation and future income goals.',
        'icon' => 'fa-solid fa-chart-line', 'color' => 'emerald', 'route' => 'financial-planner.index',
    ],
    'finance' => [
        'label' => 'Income, Budgets & Expenses',
        'description' => 'Track income, budgets, debts and expenses; scan receipt images/PDFs/documents to extract itemized purchases automatically.',
        'icon' => 'fa-solid fa-wallet', 'color' => 'emerald', 'route' => 'expenses.index',
    ],
    'savings' => [
        'label' => 'Savings Goals',
        'description' => 'Create savings goals, record contributions and monitor progress toward each target.',
        'icon' => 'fa-solid fa-piggy-bank', 'color' => 'teal', 'route' => 'savings-goals.index',
    ],
    'projects' => [
        'label' => 'Projects & Tasks',
        'description' => 'Manage projects and tasks with deadlines, statuses and project selection instead of manual project IDs.',
        'icon' => 'fa-solid fa-diagram-project', 'color' => 'cyan', 'route' => 'projects.index',
    ],
    'meetings' => [
        'label' => 'Meetings, Recording & AI Notes',
        'description' => 'Schedule meetings, record or upload audio, keep transcripts, generate AI summaries and share meeting notes.',
        'icon' => 'fa-solid fa-microphone-lines', 'color' => 'blue', 'route' => 'meetings.index',
    ],
    'reminders' => [
        'label' => 'Smart Reminders',
        'description' => 'Create one-time or recurring reminders with in-app, email and push delivery, including reminders linked to tracked items.',
        'icon' => 'fa-solid fa-bell', 'color' => 'amber', 'route' => 'reminders.index',
    ],
    'health' => [
        'label' => 'Health & Wellness',
        'description' => 'Track diet, exercise, sleep and health checkups from one wellness workspace.',
        'icon' => 'fa-solid fa-heart-pulse', 'color' => 'rose', 'route' => 'diet-logs.index',
    ],
    'education' => [
        'label' => 'Education Plans',
        'description' => 'Track courses, learning goals, progress and target completion dates.',
        'icon' => 'fa-solid fa-graduation-cap', 'color' => 'orange', 'route' => 'education-plans.index',
    ],
    'relationships' => [
        'label' => 'Network & Relationships',
        'description' => 'Manage professional contacts, follow-ups and important personal relationships.',
        'icon' => 'fa-solid fa-people-arrows', 'color' => 'pink', 'route' => 'network-contacts.index',
    ],
    'spiritual' => [
        'label' => 'Spiritual Growth',
        'description' => 'Track prayer, devotion, fasting, meditation and other personal spiritual practices.',
        'icon' => 'fa-solid fa-hands-praying', 'color' => 'lime', 'route' => 'spiritual-practices.index',
    ],
    'signature' => [
        'label' => 'Digital Signatures & Documents',
        'description' => 'Save signatures, sign uploaded documents and manage signed-document history.',
        'icon' => 'fa-solid fa-file-signature', 'color' => 'violet', 'route' => 'signature.show',
    ],
    'business-card' => [
        'label' => 'Digital Business Card',
        'description' => 'Create a shareable digital card with profile photo, custom branding, link and QR code.',
        'icon' => 'fa-solid fa-id-card', 'color' => 'teal', 'route' => 'business-card.edit',
    ],
    'ai-planner' => [
        'label' => 'AI Planner',
        'description' => 'Generate personalized planning suggestions using the information you track across My Digital Diary.',
        'icon' => 'fa-solid fa-robot', 'color' => 'purple', 'route' => 'ai-plans.index',
    ],
    'reports' => [
        'label' => 'Personal Report PDF',
        'description' => 'Download a cross-module PDF covering finances, planners, itemized expenses, projects, meetings, reminders, wellness and more.',
        'icon' => 'fa-solid fa-file-pdf', 'color' => 'slate', 'route' => 'report.download',
    ],
    'billing' => [
        'label' => 'Subscription, Invoices & Receipts',
        'description' => 'View subscription status, invoices and receipts, and complete pending Mobile Money or bank-transfer payments.',
        'icon' => 'fa-solid fa-file-invoice-dollar', 'color' => 'green', 'route' => 'subscription.show',
    ],
];
