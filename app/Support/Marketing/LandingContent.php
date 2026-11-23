<?php

namespace App\Support\Marketing;

use App\Support\Billing\PlanCatalog;

/**
 * Copy and demo data for the marketing landing page, kept out of the Blade view
 * so the template stays readable.
 */
final class LandingContent
{
    /**
     * Module groups with their chip colours (Tailwind classes).
     *
     * @return array<string, array{chip: string, text: string}>
     */
    public static function moduleGroups(): array
    {
        return [
            'Academics' => ['chip' => 'bg-[#EEEEFD] text-brand-700', 'text' => 'text-brand-700'],
            'Students & staff' => ['chip' => 'bg-naija-50 text-naija-700', 'text' => 'text-naija-700'],
            'Money' => ['chip' => 'bg-gold-50 text-gold', 'text' => 'text-gold'],
            'Admin & control' => ['chip' => 'bg-sky-50 text-sky', 'text' => 'text-sky'],
        ];
    }

    /**
     * The 17 modules, in the order they appear on the page.
     *
     * @return list<array{name: string, group: string, icon: string, description: string}>
     */
    public static function modules(): array
    {
        return array_map(fn (array $module): array => [
            'name' => $module[0],
            'group' => $module[1],
            'icon' => $module[2],
            'description' => $module[3],
        ], [
            ['Admissions & Enrolment', 'Students & staff', 'user-plus', 'Online application forms, entrance exam scores and admission letters, then enrol straight into a class.'],
            ['Student Management', 'Students & staff', 'graduation-cap', 'Every student’s profile, parents or guardians, medical notes and class history across sessions.'],
            ['Staff & Payroll', 'Students & staff', 'briefcase', 'Staff records, salary structures, deductions and monthly payslips, all in one place.'],
            ['School Fees Management', 'Money', 'receipt', 'Fee items per class and term, sibling discounts, part-payments and receipts in Naira.'],
            ['Cashflow & Finance', 'Money', 'wallet', 'Income and expenses, bank and cash balances, and clear reports for the proprietor.'],
            ['Inventory Management', 'Money', 'package', 'Uniforms, textbooks and store items, with stock levels and sales to students.'],
            ['Exams & CBT', 'Academics', 'monitor-check', 'Set objective questions and run timed computer-based tests that mark themselves.'],
            ['Results Processing', 'Academics', 'file-text', 'CA and exam scores become report cards with grades, positions and remarks.'],
            ['Special Exams', 'Academics', 'award', 'Entrance, mock and WAEC, NECO or JAMB practice exams, kept apart from termly results.'],
            ['Attendance (Staff & Students)', 'Academics', 'clipboard-check', 'Daily class registers for students and clock-in for staff, with absence reports.'],
            ['Classes & Subjects', 'Academics', 'layers', 'Nursery to SS 3, class arms like JSS 1A and 1B, and subjects linked to their teachers.'],
            ['Sessions & Terms', 'Academics', 'calendar-range', 'Set up each session with First, Second and Third Term, then promote students at the end.'],
            ['Time Table Management', 'Academics', 'calendar-days', 'Build class and teacher timetables without clashes.'],
            ['School Information', 'Admin & control', 'school', 'Your school’s name, logo, address, motto and signatures on every report card and receipt.'],
            ['User & Admin Management', 'Admin & control', 'users-round', 'Logins and permissions for the proprietor, principal, bursar, teachers and parents.'],
            ['Activity Log Sessions', 'Admin & control', 'history', 'See who logged in and what they changed, so every score and payment is accountable.'],
            ['System Settings', 'Admin & control', 'settings', 'Grading scale, CA and exam weights, fee rules and notifications, set your way.'],
        ]);
    }

    /**
     * @return list<string>
     */
    public static function fits(): array
    {
        return ['3-term sessions', 'CA + exam scoring', 'Nursery to SS 3', 'Fees in Naira', 'WAEC, NECO & JAMB practice', 'Islamiyya subjects'];
    }

    /**
     * Spotlight tabs. Each key matches a panel partial in marketing/partials/spotlight.
     *
     * @return list<array{key: string, label: string, kicker: string, title: string, body: string, points: list<string>}>
     */
    public static function spotlight(): array
    {
        return [
            [
                'key' => 'results',
                'label' => 'Results',
                'kicker' => 'Results processing',
                'title' => 'Report cards ready the day exams end.',
                'body' => 'Teachers enter CA and exam scores per subject. Ta’aleem works out totals, grades, class positions and cumulative results, then prints report cards with your logo, remarks and signatures.',
                'points' => ['Your CA and exam weights and grading scale', 'Positions, averages and broadsheets', 'Parents check results online'],
            ],
            [
                'key' => 'cbt',
                'label' => 'Exams & CBT',
                'kicker' => 'Exams & CBT · Special exams',
                'title' => 'Computer-based tests that mark themselves.',
                'body' => 'Build a question bank, set timed objective tests, and let students write on a computer, tablet or phone. Use it for termly exams, entrance exams and WAEC, NECO or JAMB practice.',
                'points' => ['Timed tests with shuffled questions', 'Automatic marking and score sheets', 'Mock exams kept apart from term results'],
            ],
            [
                'key' => 'fees',
                'label' => 'School fees',
                'kicker' => 'School fees · Cashflow & finance',
                'title' => 'Know who has paid, who is owing, and how much.',
                'body' => 'Set fees per class and term once. Record transfers, cash and POS payments, issue receipts and see every naira that comes in and goes out of the school.',
                'points' => ['Sibling discounts and part-payments', 'Receipts and debtors’ lists', 'Income and expense reports'],
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll',
                'kicker' => 'Staff & payroll',
                'title' => 'Pay your staff correctly, every month.',
                'body' => 'Keep staff records, salary structures, allowances and deductions in one place. Run payroll in minutes and send each staff member their payslip.',
                'points' => ['Allowances, deductions and loans', 'Monthly payslips', 'Payroll history per staff member'],
            ],
            [
                'key' => 'attendance',
                'label' => 'Attendance',
                'kicker' => 'Attendance · Staff & students',
                'title' => 'See who is in school, every morning.',
                'body' => 'Form teachers mark the class register in seconds, and staff clock in when they arrive. Spot students who keep missing school and staff who keep coming late.',
                'points' => ['Daily student registers', 'Staff clock-in and lateness', 'Weekly and termly attendance reports'],
            ],
        ];
    }

    /**
     * @return list<array{subject: string, ca: int, exam: int}>
     */
    public static function reportRows(): array
    {
        return [
            ['subject' => 'Mathematics', 'ca' => 38, 'exam' => 55],
            ['subject' => 'English Language', 'ca' => 36, 'exam' => 52],
            ['subject' => 'Basic Science', 'ca' => 37, 'exam' => 50],
            ['subject' => 'Civic Education', 'ca' => 35, 'exam' => 54],
            ['subject' => 'Islamic Studies', 'ca' => 39, 'exam' => 57],
        ];
    }

    /**
     * @return list<array{name: string, detail: string, amount: string, status: string}>
     */
    public static function invoices(): array
    {
        return [
            ['name' => 'Chinedu Okafor', 'detail' => 'JSS 2A · First Term', 'amount' => '₦185,000', 'status' => 'Paid'],
            ['name' => 'Aisha & Fatima Bello', 'detail' => 'JSS 2A & Primary 4 · sibling discount', 'amount' => '₦320,000', 'status' => 'Paid'],
            ['name' => 'Tunde Adeyemi', 'detail' => 'SS 1B · ₦120,000 of ₦210,000', 'amount' => '₦90,000', 'status' => 'Part-paid'],
            ['name' => 'Ngozi Eze', 'detail' => 'Primary 6 · First Term', 'amount' => '₦150,000', 'status' => 'Owing'],
        ];
    }

    /**
     * @return list<array{name: string, role: string, gross: string, deductions: string, net: string}>
     */
    public static function payroll(): array
    {
        return [
            ['name' => 'Mrs. Adebayo', 'role' => 'Principal', 'gross' => '₦450,000', 'deductions' => '₦62,000', 'net' => '₦388,000'],
            ['name' => 'Mrs. Udo', 'role' => 'Bursar', 'gross' => '₦200,000', 'deductions' => '₦24,000', 'net' => '₦176,000'],
            ['name' => 'Mr. Okonkwo', 'role' => 'Mathematics teacher', 'gross' => '₦180,000', 'deductions' => '₦21,600', 'net' => '₦158,400'],
            ['name' => 'Mallam Sani', 'role' => 'Islamic Studies teacher', 'gross' => '₦150,000', 'deductions' => '₦18,000', 'net' => '₦132,000'],
        ];
    }

    /**
     * @return list<array{day: string, percent: int}>
     */
    public static function attendanceWeek(): array
    {
        return [
            ['day' => 'Mon', 'percent' => 95],
            ['day' => 'Tue', 'percent' => 97],
            ['day' => 'Wed', 'percent' => 96],
            ['day' => 'Thu', 'percent' => 92],
            ['day' => 'Fri', 'percent' => 89],
        ];
    }

    /**
     * @return list<array{tag: string, tagClass: string, title: string, body: string, items: list<string>}>
     */
    public static function roles(): array
    {
        return [
            ['tag' => 'Proprietor & principal', 'tagClass' => 'bg-brand-200', 'title' => 'The whole school on one dashboard.', 'body' => 'Enrolment, results, fees and staff at a glance, with reports for board meetings and PTA.', 'items' => ['Dashboards', 'Results overview', 'Activity log']],
            ['tag' => 'Bursar & accounts', 'tagClass' => 'bg-gold-200', 'title' => 'Every naira accounted for.', 'body' => 'Fees, receipts, debtors, expenses and payroll, without a separate ledger book.', 'items' => ['School fees', 'Cashflow', 'Payroll']],
            ['tag' => 'Teachers', 'tagClass' => 'bg-[#BFE8D3]', 'title' => 'Scores in, report cards out.', 'body' => 'Mark the register, enter CA and exam scores and set CBT questions from any device.', 'items' => ['Registers', 'Score entry', 'CBT']],
            ['tag' => 'Parents & students', 'tagClass' => 'bg-sky-100', 'title' => 'Results and fees, without coming to school.', 'body' => 'Parents check results, fee balances and attendance. Students write CBT exams and practice tests.', 'items' => ['Online results', 'Fee balance', 'CBT']],
        ];
    }

    /**
     * @return list<array{number: string, time: string, title: string, body: string}>
     */
    public static function steps(): array
    {
        return [
            ['number' => '01', 'time' => 'Day 1', 'title' => 'Send us your records', 'body' => 'Share your student list, classes, subjects and fee schedule, in Excel or from your old software.'],
            ['number' => '02', 'time' => 'Day 2–5', 'title' => 'We set up your school', 'body' => 'Your session, terms, class arms, subjects, grading scale and fees, set up to match how you run.'],
            ['number' => '03', 'time' => 'Before resumption', 'title' => 'Train your staff, invite parents', 'body' => 'A hands-on session for your teachers and bursar, then parents get their logins.'],
        ];
    }

    /**
     * Pricing plans. Prices come from config('taaleem.marketing.prices'); a null price shows "Get a quote".
     *
     * @return list<array{key: string, name: string, price: ?string, description: string, cta: string, popular: bool, features: list<string>}>
     */
    public static function plans(): array
    {
        return collect(PlanCatalog::publicPlans())->map(function (array $plan): array {
            return [
                'key' => $plan['key'],
                'name' => $plan['name'],
                'price' => $plan['flat_termly'],
                'description' => $plan['description'],
                'cta' => $plan['popular'] ? 'Start free trial' : ($plan['key'] === 'premium' ? 'Contact sales' : 'Start free trial'),
                'popular' => $plan['popular'],
                'features' => $plan['features'],
                'per_student' => $plan['per_student'],
                'flat_annual' => $plan['flat_annual'],
                'seats' => number_format((int) $plan['max_students']).' students · '.number_format((int) $plan['max_staff']).' staff',
            ];
        })->all();
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(): array
    {
        return array_map(fn (array $faq): array => ['question' => $faq[0], 'answer' => $faq[1]], [
            ['Does it follow the Nigerian school calendar?', 'Yes. You set up each academic session with First, Second and Third Term. At the end of the session you promote students to their next class in one step.'],
            ['Can it compute results the way our school does?', 'Yes. You choose your CA and exam weights, your grading scale (A1 to F9 or your own), and whether to show positions. Ta’aleem works out totals, averages, positions and cumulative results for you.'],
            ['Can students write CBT exams on it?', 'Yes. Teachers set objective questions with a time limit, and the scores are marked automatically. You can also run entrance exams and WAEC, NECO or JAMB practice exams under Special Exams.'],
            ['How do we record school fees?', 'Set fee items per class and term, then record each payment by transfer, cash or POS. Parents get a receipt, and the bursar always has an up-to-date list of who is owing.'],
            ['Can we move from Excel or our old software?', 'Yes. Send us your student list, classes and fee records, and we’ll import them for you before resumption.'],
            ['Who can see and change what?', 'Each person gets a role, such as proprietor, principal, bursar, teacher or parent, and only sees what that role allows. The activity log shows who changed any score or payment, and when.'],
        ]);
    }

    /**
     * WAEC grade for a score out of 100 (used by the static report card demo).
     */
    public static function waecGrade(int $total): string
    {
        return match (true) {
            $total >= 75 => 'A1',
            $total >= 70 => 'B2',
            $total >= 65 => 'B3',
            $total >= 60 => 'C4',
            $total >= 55 => 'C5',
            $total >= 50 => 'C6',
            $total >= 45 => 'D7',
            $total >= 40 => 'E8',
            default => 'F9',
        };
    }
}
