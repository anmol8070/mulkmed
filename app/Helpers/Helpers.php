<?php

namespace App\Helpers;

use App\Models\Admin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class Helpers
{
    public static function sidebarPermissionTree(): array
    {
        return [
            'doctor_limited' => [
                'label' => 'Doctors Management (Limited Access)',
                'children' => [
                    'doctor_limited.doctors' => 'Doctors',
                    'doctor_limited.bulk_upload_doctors' => 'Bulk Upload of Doctors',
                    'doctor_limited.bulk_upload_doctor_slots' => 'Bulk Upload Doctors Slots',
                    'doctor_limited.smo_hospitals' => 'Add Service Providers',
                    'doctor_limited.bulk_upload_hospitals' => 'Bulk Upload Hospitals',
                ],
            ],
            'doctor' => [
                'label' => 'Doctors Management',
                'children' => [
                    'doctor.doctors' => 'Doctors',
                    'doctor.bulk_upload_doctors' => 'Bulk Upload of Doctors',
                    'doctor.bulk_upload_doctor_slots' => 'Bulk Upload Doctors Slots',
                    'doctor.bulk_upload_specialities' => 'Bulk Upload Specialities',
                    'doctor.bulk_upload_doctor_mobile' => 'Bulk Upload of Doctors Mobile number',
                    'doctor.bulk_upload_doctors_speciality' => 'Bulk Upload Doctors and Speciality',
                    'doctor.bulk_upload_dha' => 'Bulk Upload of DHA Number and Signature',
                ],
            ],
            'main' => [
                'label' => 'Main',
                'children' => [
                    'main.dashboard' => 'Dashboard',
                    'main.users' => 'Users',
                    'main.doctors' => 'Doctors',
                    'main.reviews' => 'Reviews',
                    'main.coupons' => 'Coupons',
                    'main.reels' => 'Reels',
                    'main.reports' => 'Reel Reports',
                    'main.faqs' => 'FAQs',
                    'main.notifications' => 'Notifications',
                ],
            ],
            'home_page' => [
                'label' => 'Home Page',
                'children' => [
                    'home_page.dashboard_banners' => 'Dashboard Banners',
                    'home_page.partner_network' => 'Partners Network',
                    'home_page.doctor_speciality' => 'Doctor By Speciality',
                    'home_page.doctors_by_symptoms' => 'Doctors By Symptoms',
                ],
            ],
            'patient_appointment' => [
                'label' => 'Patient Appointment',
                'children' => [
                    'patient_appointment.registration' => 'Patient Registration',
                    'patient_appointment.book_appointment' => 'Book Appointment',
                    'patient_appointment.appointments' => 'Appointments',
                ],
            ],
            'smo' => [
                'label' => 'World Best Treatment Finder',
                'children' => [
                    'smo.top_hospitals' => 'Top Hospitals',
                    'smo.hospitals' => 'Add Service Providers',
                    'smo.why_second_opinion' => 'Why Second Opinion Matters',
                    'smo.trusted_healthcare' => 'Explore Our Trusted Healthcare Providers',
                    'smo.hospital_categories' => 'Add Category',
                    'smo.hospital_procedures' => 'Hospital Procedures',
                    'smo.top_procedures' => 'Top Procedures',
                    'smo.mulkmed_choice_doctors' => 'Mulkmed Choice Of Doctors',
                    'smo.unlock_benefits_card' => 'Unlock More Benefits Card Banner',
                    'smo.submit_query_banner' => 'Submit Your Query Banner',
                    'smo.submitted_queries' => 'Submitted SMO Queries',
                    'smo.query_procedures' => 'Query Procedures',
                    'smo.bulk_upload_hospitals' => 'Bulk Upload Service Providers',
                    'smo.bulk_upload_hospital_procedures' => 'Bulk Upload of Hospital Procedures',
                    'smo.bulk_upload_query_procedures' => 'Bulk Upload of Query Procedures',
                    'smo.bulk_upload_doctors_speciality' => 'Bulk Upload Doctors and Speciality',
                ],
            ],
            'Tourist Management' => [
                'label' => 'Mulk Travel Cover Management',
                'children' => [
                    'Tourist Management.travel_banner' => 'Tourist banner',
                    'Tourist Management.travelers_list' => 'Travelers List',
                    'Tourist Management.home' => 'Home',
                    'Tourist Management.travelers_appointments' => 'Travelers Appointments',
                    'Tourist Management.agencies' => 'Agencies',
                    'Tourist Management.product_plan' => 'My Product Plan',
                    'Tourist Management.plan_allocation' => 'Plan Allocation',
                    'Tourist Management.allocated_agencies' => 'Allocated Agencies',
                    'Tourist Management.upload_history' => 'Excel Upload History',
                    'Tourist Management.transaction_summary' => 'Transaction Summary',
                ],
            ],
            'bidding' => [
                'label' => 'Lowest Price Finder (Bidding)',
                'children' => [
                    'bidding.submitted_bid' => 'Submitted Bid',
                    'bidding.bidding_services' => 'Bidding Services',
                    'bidding.bidding_banner' => 'Bidding Bottom Banner',
                    'bidding.bulk_upload_services' => 'Bulk Upload of Bidding services',
                ],
            ],
            'best_offers' => [
                'label' => 'Best Offers',
                'children' => [
                    'best_offers.plans' => 'Best Offers Plans',
                    'best_offers.plan_users' => 'Plan Purchased By Users',
                ],
            ],
            'mulk_cards' => [
                'label' => 'Mulk Cards',
                'children' => [
                    'mulk_cards.hnh_card' => 'Mulk HnH Card',
                    'mulk_cards.senior_card' => 'Mulk Senior Card',
                    'mulk_cards.tourist_card' => 'Mulk Tourist Gold Card',
                ],
            ],
            'bulk_upload_smo' => [
                'label' => 'Bulk Upload of Service Providers and Procedures',
                'children' => [
                    'bulk_upload_smo.top_hospitals' => 'Bulk Upload of Top Hospitals',
                    'bulk_upload_smo.why_second_opinion' => 'Why Second Opinion Matters Banner',
                    'bulk_upload_smo.trusted_partners' => 'Trusted Healthcare Partners Bulk Upload',
                    'bulk_upload_smo.categories' => 'Bulk Upload of Categories',
                    'bulk_upload_smo.top_procedures' => 'Bulk Upload of Top Procedures',
                    'bulk_upload_smo.dha_signature' => 'Bulk Upload of DHA Number and Signature',
                ],
            ],
            'business' => [
                'label' => 'Business',
                'children' => [
                    'business.user_withdraws' => 'User Withdraws',
                    'business.doctor_withdraws' => 'Doctor Withdraws',
                    'business.platform_earnings' => 'Platform Earnings',
                    'business.booking_payment' => 'Booking & Payment',
                    'business.wallet_recharge' => 'Recharge Logs (User)',
                ],
            ],
            'online_consultation' => [
                'label' => 'Online Consultation',
                'children' => [
                    'online_consultation.common_health' => 'Common Health Problems',
                    'online_consultation.speciality_disease' => 'Speciality Wise Disease',
                    'online_consultation.doctor_plans' => 'Doctors Plan',
                    'online_consultation.banners' => 'Banners',
                ],
            ],
            'order_medicine' => [
                'label' => 'Order Medicine',
                'children' => [
                    'order_medicine.categories' => 'Categories',
                ],
            ],
            'other_data' => [
                'label' => 'Other Data',
                'children' => [
                    'other_data.doctor_speciality' => 'Doctors By Speciality',
                    'other_data.settings' => 'Settings',
                    'other_data.admin_management' => 'Admin Management',
                    'other_data.emr_master_data' => 'EMR Master Data',
                    'other_data.test_classification' => 'Test Classification',
                    'other_data.major_organ_tests' => 'Major Organ Tests',
                    'other_data.longevity_plans' => 'Longevity Plans',
                    'other_data.homepage_sections' => 'Homepage Sections',
                    'other_data.bulk_upload_doctors' => 'Bulk Upload of Doctors',
                    'other_data.bulk_upload_doctor_slots' => 'Bulk Upload Doctors Slots',
                    'other_data.bulk_upload_specialities' => 'Bulk Upload Specialities',
                ],
            ],
            'pages' => [
                'label' => 'Pages',
                'children' => [
                    'pages.privacy' => 'Privacy Policy',
                    'pages.terms' => 'Terms Of Use',
                    'pages.help_center' => 'Help Center',
                    'pages.midas_description' => 'MIDAS Description',
                    'pages.healthcheck_description' => 'Mulk AI Healthcheck Description',
                ],
            ],
        ];
    }

    public static function allPermissionKeys(): array
    {
        $keys = [];

        foreach (self::sidebarPermissionTree() as $groupKey => $group) {
            $keys[] = $groupKey;
            foreach ($group['children'] as $childKey => $label) {
                $keys[] = $childKey;
            }
        }

        return $keys;
    }

    public static function permissionLabel(string $key): string
    {
        foreach (self::sidebarPermissionTree() as $groupKey => $group) {
            if ($groupKey === $key) {
                return $group['label'];
            }
            if (isset($group['children'][$key])) {
                return $group['children'][$key];
            }
        }

        return $key;
    }

    public static function sidebarModules(): array
    {
        $modules = [];
        foreach (self::sidebarPermissionTree() as $groupKey => $group) {
            $modules[$groupKey] = $group['label'];
            foreach ($group['children'] as $childKey => $label) {
                $modules[$childKey] = $label;
            }
        }

        return $modules;
    }

    public static function getAdminRolePermissions(): array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return [];
        }

        $adminRole = Admin::where('user_id', $userId)
            ->join('admin_roles', 'admin_user.admin_role_id', 'admin_roles.id')
            ->first();

        if (!$adminRole || (int) $adminRole->status !== 1) {
            return [];
        }

        if ((int) $adminRole->admin_role_id === 1) {
            return self::allPermissionKeys();
        }

        return (array) json_decode($adminRole->module_access ?? '[]', true);
    }

    public static function isPermissionGranted(string $key, ?array $permissions = null): bool
    {
        $permissions = $permissions ?? self::getAdminRolePermissions();

        if (in_array($key, $permissions, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (str_starts_with($key, $permission . '.')) {
                return true;
            }
        }

        $parent = explode('.', $key)[0] ?? $key;
        if ($parent !== $key && in_array($parent, $permissions, true)) {
            return true;
        }

        if (str_starts_with($key, 'doctor_limited.')
            && in_array('doctor', $permissions, true)
            && !in_array('main', $permissions, true)
            && !collect($permissions)->contains(
                fn ($permission) => str_starts_with($permission, 'doctor.') && !str_starts_with($permission, 'doctor_limited.')
            )) {
            return true;
        }

        return false;
    }

    public static function section_visible(string $section): bool
    {
        if (self::isFullAdmin()) {
            return true;
        }

        if (self::isPermissionGranted($section)) {
            return true;
        }

        $permissions = self::getAdminRolePermissions();
        foreach ($permissions as $permission) {
            if (str_starts_with($permission, $section . '.')) {
                return true;
            }
        }

        return false;
    }

    public static function hasLimitedDoctorAccess(): bool
    {
        if (self::isFullAdmin()) {
            return false;
        }

        $permissions = self::getAdminRolePermissions();

        if (self::section_visible('doctor_limited')) {
            return !self::section_visible('doctor');
        }

        if (in_array('doctor', $permissions, true) && !in_array('main', $permissions, true)) {
            $hasFullDoctorChild = collect($permissions)->contains(
                fn ($permission) => str_starts_with($permission, 'doctor.') && !str_starts_with($permission, 'doctor_limited.')
            );

            return !$hasFullDoctorChild;
        }

        return false;
    }

    public static function module_permission_check($mod_name)
    {
        if (!Session::get('user_id')) {
            return false;
        }

        if (self::isFullAdmin()) {
            return true;
        }

        $adminRole = Admin::where('user_id', Session::get('user_id'))
            ->join('admin_roles', 'admin_user.admin_role_id', 'admin_roles.id')
            ->first();

        if (!$adminRole || (int) $adminRole->status !== 1) {
            return false;
        }

        return self::isPermissionGranted($mod_name);
    }

    private static function isFullAdmin(): bool
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return false;
        }

        $admin = Admin::where('user_id', $userId)->first();

        return $admin && (int) $admin->admin_role_id === 1;
    }

    public static function get_role()
    {
        $user_id = Session::get('user_id');
        if ($user_id) {
            $admin_role = Admin::where('user_id', $user_id)->join('admin_roles', 'admin_user.admin_role_id', 'admin_roles.id')->first();
            if ($admin_role->name == 'admin') {
                return false;
            }
            return true;
        }
        return true;
    }

    public static function isUserRole()
    {
        $user_id = Session::get('user_id');
        if ($user_id) {
            $admin_role = Admin::where('user_id', $user_id)
                ->join('admin_roles', 'admin_user.admin_role_id', 'admin_roles.id')
                ->first();

            return $admin_role && $admin_role->name === 'user';
        }

        return false;
    }

    public static function translate($text, $targetLang = 'hi', $sourceLang = 'en')
    {
        return $targetLang;
        $response = Http::post('https://libretranslate.com/translate', [
            'q' => $text,
            'source' => $sourceLang,
            'target' => $targetLang,
            'format' => 'text',
        ]);

        return $response->json()['translatedText'] ?? $text;
    }

    public static function conversionRate()
    {
        $host = request()->getHost();
        $conversionRate = 1;

        if ($host === 'india.mulkmed.com') {
            $conversionRate = 24.77;
        }

        return [
            'host' => $host,
            'conversionRate' => $conversionRate,
        ];
    }
}
