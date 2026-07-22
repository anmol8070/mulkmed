<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BestOffersController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LowestPriceFinderController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\OnlineConsultationController;
use App\Http\Controllers\OrderMedicineController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SMOController;
use App\Http\Controllers\BiddingController;
use App\Http\Controllers\PatientAppointmentController;
use App\Http\Controllers\HnHController;
use App\Http\Controllers\SeniorCardController;
use App\Http\Controllers\TouristCardController;
use App\Http\Controllers\TravelFlowBannerController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\TouristAppointmentController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\TouristImportController;
use App\Http\Controllers\SenoclockController;
use App\Http\Controllers\SenoclockTestController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\MajorOrganTestController;
use App\Http\Controllers\LongevityPlanController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/react/{any?}', function () {
    return file_get_contents(public_path('dist/index.html'));
})->where('any', '.*');

Route::get('/clear-config-cache', function () {
    Artisan::call('config:clear');
    return 'Config cache cleared!';
});

Route::get('/linkstorage', function () {
    Artisan::call('storage:link');
});

Route::get('/run-appointment-cron', function () {
    Artisan::call('appointments:markCompletedMissed');
    Artisan::call('touristAppointments:markCompletedMissed');

    return response()->json([
        'status'  => true,
        'message' => 'Appointment cron executed successfully',
        'output'  => Artisan::output(),
    ]);
});



Route::get('/', [LoginController::class, 'login'])->name('/');
Route::post('login', [LoginController::class, 'checklogin'])->middleware(['checkLogin'])->name('login');
Route::get('index', [SettingsController::class, 'index'])->middleware(['checkLogin'])->name('index');
Route::get('logout', [LoginController::class, 'logout'])->middleware(['checkLogin'])->name('logout');

// Users
Route::get('bulkUpdateExperties', [DoctorController::class, 'bulkUpdateExperties'])->middleware(['checkLogin'])->name('bulkUpdateExperties');
Route::get('users', [UsersController::class, 'users'])->middleware(['checkLogin'])->name('users');
Route::get('users', [UsersController::class, 'users'])->middleware(['checkLogin'])->name('users');
Route::post('fetchUsersList', [UsersController::class, 'fetchUsersList'])->middleware(['checkLogin'])->name('fetchUsersList');
Route::get('blockUserFromAdmin/{id}', [UsersController::class, 'blockUserFromAdmin'])->middleware(['checkLogin'])->name('blockUserFromAdmin');
Route::get('unblockUserFromAdmin/{id}', [UsersController::class, 'unblockUserFromAdmin'])->middleware(['checkLogin'])->name('unblockUserFromAdmin');

// Doctors
Route::get('doctors', [DoctorController::class, 'doctors'])->middleware(['checkLogin'])->name('doctors');
Route::post('fetchAllDoctorsList', [DoctorController::class, 'fetchAllDoctorsList'])->middleware(['checkLogin'])->name('fetchAllDoctorsList');
Route::post('fetchApprovedDoctorsList', [DoctorController::class, 'fetchApprovedDoctorsList'])->middleware(['checkLogin'])->name('fetchApprovedDoctorsList');
Route::post('fetchPendingDoctorsList', [DoctorController::class, 'fetchPendingDoctorsList'])->middleware(['checkLogin'])->name('fetchPendingDoctorsList');
Route::post('fetchBannedDoctorsList', [DoctorController::class, 'fetchBannedDoctorsList'])->middleware(['checkLogin'])->name('fetchBannedDoctorsList');
Route::get('deleteDoctor/{id}', [DoctorController::class, 'deleteDoctor'])->middleware(['checkLogin'])->name('deleteDoctor');

// Appointments
Route::get('appointments', [AppointmentController::class, 'appointments'])->middleware(['checkLogin'])->name('appointments');
Route::post('fetchAllAppointmentsList', [AppointmentController::class, 'fetchAllAppointmentsList'])->middleware(['checkLogin'])->name('fetchAllAppointmentsList');
Route::post('fetchPendingAppointmentsList', [AppointmentController::class, 'fetchPendingAppointmentsList'])->middleware(['checkLogin'])->name('fetchPendingAppointmentsList');
Route::post('fetchAcceptedAppointmentsList', [AppointmentController::class, 'fetchAcceptedAppointmentsList'])->middleware(['checkLogin'])->name('fetchAcceptedAppointmentsList');
Route::post('fetchCompletedAppointmentsList', [AppointmentController::class, 'fetchCompletedAppointmentsList'])->middleware(['checkLogin'])->name('fetchCompletedAppointmentsList');
Route::post('fetchCancelledAppointmentsList', [AppointmentController::class, 'fetchCancelledAppointmentsList'])->middleware(['checkLogin'])->name('fetchCancelledAppointmentsList');
Route::post('fetchDeclinedAppointmentsList', [AppointmentController::class, 'fetchDeclinedAppointmentsList'])->middleware(['checkLogin'])->name('fetchDeclinedAppointmentsList');
Route::post('fetchMissedAppointmentsList', [AppointmentController::class, 'fetchMissedAppointmentsList'])->middleware(['checkLogin'])->name('fetchMissedAppointmentsList');

// View Appointment
Route::get('viewAppointment/{id}', [AppointmentController::class, 'viewAppointment'])->middleware(['checkLogin'])->name('viewAppointment');

// Jitsi meeting 
Route::get('/meetings/join/{id}', [App\Http\Controllers\v1\AppointmentController::class, 'jitsiJoinMeeting']);

// View Doctor
Route::get('viewDoctorProfile/{id}', [DoctorController::class, 'viewDoctorProfile'])->middleware(['checkLogin'])->name('viewDoctorProfile');
Route::get('banDoctor/{id}', [DoctorController::class, 'banDoctor'])->middleware(['checkLogin'])->name('banDoctor');
Route::get('activateDoctor/{id}', [DoctorController::class, 'activateDoctor'])->middleware(['checkLogin'])->name('activateDoctor');
Route::post('updateDoctorDetails_Admin', [DoctorController::class, 'updateDoctorDetails_Admin'])->middleware(['checkLogin'])->name('updateDoctorDetails_Admin');
Route::post('fetchDoctorAppointmentsList', [DoctorController::class, 'fetchDoctorAppointmentsList'])->middleware(['checkLogin'])->name('fetchDoctorAppointmentsList');
Route::post('fetchDoctorReviewsList', [DoctorController::class, 'fetchDoctorReviewsList'])->middleware(['checkLogin'])->name('fetchDoctorReviewsList');
Route::post('fetchDoctorWalletStatement', [DoctorController::class, 'fetchDoctorWalletStatement'])->middleware(['checkLogin'])->name('fetchDoctorWalletStatement');
Route::post('fetchDoctorReels_Admin', [ReelController::class, 'fetchDoctorReels_Admin'])->middleware(['checkLogin'])->name('fetchDoctorReels_Admin');
Route::post('fetchDoctorPayoutRequestsList', [DoctorController::class, 'fetchDoctorPayoutRequestsList'])->middleware(['checkLogin'])->name('fetchDoctorPayoutRequestsList');
Route::post('fetchDoctorEarningsList', [DoctorController::class, 'fetchDoctorEarningsList'])->middleware(['checkLogin'])->name('fetchDoctorEarningsList');
Route::post('fetchDoctorServicesList', [DoctorController::class, 'fetchDoctorServicesList'])->middleware(['checkLogin'])->name('fetchDoctorServicesList');
Route::get('deleteService/{id}', [DoctorController::class, 'deleteService'])->middleware(['checkLogin'])->name('deleteService');
Route::post('fetchDoctorExpertiseList', [DoctorController::class, 'fetchDoctorExpertiseList'])->middleware(['checkLogin'])->name('fetchDoctorExpertiseList');
Route::get('getExpertise/{id}', [DoctorController::class, 'getExpertise'])->middleware(['checkLogin'])->name('getExpertise');
Route::post('/updateExpertise', [DoctorController::class, 'updateExpertise'])->middleware(['checkLogin'])->name('updateExpertise');

Route::get('/changeExpertise', [AppointmentController::class, 'changeExpertise'])->middleware(['checkLogin'])->name('changeExpertise');

Route::get('deleteExpertise/{id}', [DoctorController::class, 'deleteExpertise'])->middleware(['checkLogin'])->name('deleteExpertise');
Route::post('fetchDoctorServiceLocationList', [DoctorController::class, 'fetchDoctorServiceLocationList'])->middleware(['checkLogin'])->name('fetchDoctorServiceLocationList');
Route::get('deleteServiceLocation/{id}', [DoctorController::class, 'deleteServiceLocation'])->middleware(['checkLogin'])->name('deleteServiceLocation');
Route::post('fetchDoctorExperienceList', [DoctorController::class, 'fetchDoctorExperienceList'])->middleware(['checkLogin'])->name('fetchDoctorExperienceList');
Route::get('deleteExperience/{id}', [DoctorController::class, 'deleteExperience'])->middleware(['checkLogin'])->name('deleteExperience');
Route::post('fetchDoctorAwardsList', [DoctorController::class, 'fetchDoctorAwardsList'])->middleware(['checkLogin'])->name('fetchDoctorAwardsList');
Route::get('deleteAwards/{id}', [DoctorController::class, 'deleteAwards'])->middleware(['checkLogin'])->name('deleteAwards');
Route::post('fetchDoctorHolidaysList', [DoctorController::class, 'fetchDoctorHolidaysList'])->middleware(['checkLogin'])->name('fetchDoctorHolidaysList');
Route::get('deleteDoctorHoliday/{id}', [DoctorController::class, 'deleteDoctorHoliday'])->middleware(['checkLogin'])->name('deleteDoctorHoliday');

// View Appointment
Route::get('viewAppointment/{id}', [AppointmentController::class, 'viewAppointment'])->middleware(['checkLogin'])->name('viewAppointment');

// View User
Route::get('viewUserProfile/{id}', [UsersController::class, 'viewUserProfile'])->middleware(['checkLogin'])->name('viewUserProfile');
Route::post('fetchUserAppointmentsList', [UsersController::class, 'fetchUserAppointmentsList'])->middleware(['checkLogin'])->name('fetchUserAppointmentsList');
Route::post('fetchUserWalletStatementList', [UsersController::class, 'fetchUserWalletStatementList'])->middleware(['checkLogin'])->name('fetchUserWalletStatementList');
Route::post('fetchUserWithdrawRequestsList', [UsersController::class, 'fetchUserWithdrawRequestsList'])->middleware(['checkLogin'])->name('fetchUserWithdrawRequestsList');
Route::post('fetchUserWalletRechargeLogsList', [UsersController::class, 'fetchUserWalletRechargeLogsList'])->middleware(['checkLogin'])->name('fetchUserWalletRechargeLogsList');
Route::post('fetchUserPatientsList', [UsersController::class, 'fetchUserPatientsList'])->middleware(['checkLogin'])->name('fetchUserPatientsList');
Route::post('rechargeWalletFromAdmin', [UsersController::class, 'addMoneyToUserWallet'])->middleware(['checkLogin'])->name('rechargeWalletFromAdmin');


// Admin Management
Route::get('adminManagement', [AdminManagementController::class, 'index'])->middleware(['checkLogin'])->name('adminManagement');
Route::get('adminManagement/role/create', [AdminManagementController::class, 'createRole'])->middleware(['checkLogin'])->name('adminRole.create');
Route::get('adminManagement/role/edit/{id}', [AdminManagementController::class, 'editRoleForm'])->middleware(['checkLogin'])->name('adminRole.edit');
Route::post('saveAdminRole', [AdminManagementController::class, 'saveAdminRole'])->middleware(['checkLogin'])->name('saveAdminRole');
Route::post('fetchAdminRolesList', [AdminManagementController::class, 'fetchAdminRolesList'])->middleware(['checkLogin'])->name('fetchAdminRolesList');
Route::get('toggleAdminRoleStatus/{id}', [AdminManagementController::class, 'toggleAdminRoleStatus'])->middleware(['checkLogin'])->name('toggleAdminRoleStatus');
Route::get('deleteAdminRole/{id}', [AdminManagementController::class, 'deleteAdminRole'])->middleware(['checkLogin'])->name('deleteAdminRole');
Route::post('fetchAdminUsersList', [AdminManagementController::class, 'fetchAdminUsersList'])->middleware(['checkLogin'])->name('fetchAdminUsersList');
Route::post('addAdminUser', [AdminManagementController::class, 'addAdminUser'])->middleware(['checkLogin'])->name('addAdminUser');
Route::post('editAdminUser', [AdminManagementController::class, 'editAdminUser'])->middleware(['checkLogin'])->name('editAdminUser');
Route::get('deleteAdminUser/{id}', [AdminManagementController::class, 'deleteAdminUser'])->middleware(['checkLogin'])->name('deleteAdminUser');

// Coupons
Route::get('coupons', [SettingsController::class, 'coupons'])->middleware(['checkLogin'])->name('coupons');
Route::post('fetchAllCouponsList', [SettingsController::class, 'fetchAllCouponsList'])->middleware(['checkLogin'])->name('fetchAllCouponsList');
Route::post('addCouponItem', [SettingsController::class, 'addCouponItem'])->middleware(['checkLogin'])->name('addCouponItem');
Route::post('editCouponItem', [SettingsController::class, 'editCouponItem'])->middleware(['checkLogin'])->name('editCouponItem');
Route::get('deleteCoupon/{id}', [SettingsController::class, 'deleteCoupon'])->middleware(['checkLogin'])->name('deleteCoupon');

// Reels
Route::get('reels', [ReelController::class, 'reels'])->middleware(['checkLogin'])->name('reels');
Route::post('fetchAllReelsList', [ReelController::class, 'fetchAllReelsList'])->middleware(['checkLogin'])->name('fetchAllReelsList');
Route::get('deleteReelAdmin/{id}', [ReelController::class, 'deleteReelAdmin'])->middleware(['checkLogin'])->name('deleteReelAdmin');

// Reel Reports
Route::get('reports', [ReelController::class, 'reports'])->middleware(['checkLogin'])->name('reports');
Route::post('fetchAllReelsReportList', [ReelController::class, 'fetchAllReelsReportList'])->middleware(['checkLogin'])->name('fetchAllReelsReportList');
Route::get('deleteReelReport/{id}', [ReelController::class, 'deleteReelReport'])->middleware(['checkLogin'])->name('deleteReelReport');

// Reviews
Route::get('reviews', [SettingsController::class, 'reviews'])->middleware(['checkLogin'])->name('reviews');
Route::post('fetchAllReviewsList', [SettingsController::class, 'fetchAllReviewsList'])->middleware(['checkLogin'])->name('fetchAllReviewsList');
Route::get('deleteReview/{id}', [SettingsController::class, 'deleteReview'])->middleware(['checkLogin'])->name('deleteReview');

// Faqs
Route::get('faqs', [SettingsController::class, 'faqs'])->middleware(['checkLogin'])->name('faqs');
Route::post('fetchFaqCatsList', [SettingsController::class, 'fetchFaqCatsList'])->middleware(['checkLogin'])->name('fetchFaqCatsList');
Route::post('addFaqCategory', [SettingsController::class, 'addFaqCategory'])->middleware(['checkLogin'])->name('addFaqCategory');
Route::post('editFaqCategory', [SettingsController::class, 'editFaqCategory'])->middleware(['checkLogin'])->name('editFaqCategory');
Route::get('deleteFaqCat/{id}', [SettingsController::class, 'deleteFaqCat'])->middleware(['checkLogin'])->name('deleteFaqCat');
Route::post('addFaq', [SettingsController::class, 'addFaq'])->middleware(['checkLogin'])->name('addFaq');
Route::post('fetchFaqList', [SettingsController::class, 'fetchFaqList'])->middleware(['checkLogin'])->name('fetchFaqList');
Route::get('deleteFaq/{id}', [SettingsController::class, 'deleteFaq'])->middleware(['checkLogin'])->name('deleteFaq');
Route::get('getFaqCats', [SettingsController::class, 'getFaqCats'])->middleware(['checkLogin'])->name('getFaqCats');
Route::post('editFaq', [SettingsController::class, 'editFaq'])->middleware(['checkLogin'])->name('editFaq');

// Platform Earning History
Route::get('platformEarnings', [SettingsController::class, 'platformEarnings'])->middleware(['checkLogin'])->name('platformEarnings');
Route::post('fetchPlatformEarningsList', [SettingsController::class, 'fetchPlatformEarningsList'])->middleware(['checkLogin'])->name('fetchPlatformEarningsList');
Route::get('deletePlatformEarningItem/{id}', [SettingsController::class, 'deletePlatformEarningItem'])->middleware(['checkLogin'])->name('deletePlatformEarningItem');

// Booking and Payment
Route::get('bookingAndPayment', [SettingsController::class, 'bookingAndPayment'])->middleware(['checkLogin'])->name('bookingAndPayment');
Route::post('fetchBookingAndPaymentList', [SettingsController::class, 'fetchBookingAndPaymentList'])->middleware(['checkLogin'])->name('fetchBookingAndPaymentList');

// Wallet recharge (user)
Route::get('userWalletRecharge', [SettingsController::class, 'userWalletRecharge'])->middleware(['checkLogin'])->name('userWalletRecharge');
Route::post('fetchWalletRechargeList', [SettingsController::class, 'fetchWalletRechargeList'])->middleware(['checkLogin'])->name('fetchWalletRechargeList');

// Notifications
Route::get('notifications', [SettingsController::class, 'notifications'])->middleware(['checkLogin'])->name('notifications');
Route::post('fetchUserNotificationList', [SettingsController::class, 'fetchUserNotificationList'])->middleware(['checkLogin'])->name('fetchUserNotificationList');
Route::get('deleteUserNotification/{id}', [SettingsController::class, 'deleteUserNotification'])->middleware(['checkLogin'])->name('deleteUserNotification');
Route::post('addUserNotification', [SettingsController::class, 'addUserNotification'])->middleware(['checkLogin'])->name('addUserNotification');
Route::post('editUserNotification', [SettingsController::class, 'editUserNotification'])->middleware(['checkLogin'])->name('editUserNotification');

Route::post('addDoctorNotification', [SettingsController::class, 'addDoctorNotification'])->middleware(['checkLogin'])->name('addDoctorNotification');
Route::post('fetchDoctorNotificationList', [SettingsController::class, 'fetchDoctorNotificationList'])->middleware(['checkLogin'])->name('fetchDoctorNotificationList');
Route::get('deleteDoctorNotification/{id}', [SettingsController::class, 'deleteDoctorNotification'])->middleware(['checkLogin'])->name('deleteDoctorNotification');
Route::post('editDoctorNotification', [SettingsController::class, 'editDoctorNotification'])->middleware(['checkLogin'])->name('editDoctorNotification');

// User Withdrawals
Route::get('userWithdraws', [UsersController::class, 'userWithdraws'])->middleware(['checkLogin'])->name('userWithdraws');
Route::post('fetchUserPendingWithdrawalsList', [UsersController::class, 'fetchUserPendingWithdrawalsList'])->middleware(['checkLogin'])->name('fetchUserPendingWithdrawalsList');
Route::post('fetchUserCompletedWithdrawalsList', [UsersController::class, 'fetchUserCompletedWithdrawalsList'])->middleware(['checkLogin'])->name('fetchUserCompletedWithdrawalsList');
Route::post('fetchUserRejectedWithdrawalsList', [UsersController::class, 'fetchUserRejectedWithdrawalsList'])->middleware(['checkLogin'])->name('fetchUserRejectedWithdrawalsList');
Route::post('completeUserWithdrawal', [UsersController::class, 'completeUserWithdrawal'])->middleware(['checkLogin'])->name('completeUserWithdrawal');
Route::post('rejectUserWithdrawal', [UsersController::class, 'rejectUserWithdrawal'])->middleware(['checkLogin'])->name('rejectUserWithdrawal');

// Doctor Withdrawal
Route::get('doctorWithdraws', [DoctorController::class, 'doctorWithdraws'])->middleware(['checkLogin'])->name('doctorWithdraws');
Route::post('fetchDoctorPendingWithdrawalsList', [DoctorController::class, 'fetchDoctorPendingWithdrawalsList'])->middleware(['checkLogin'])->name('fetchDoctorPendingWithdrawalsList');
Route::post('fetchDoctorCompletedWithdrawalsList', [DoctorController::class, 'fetchDoctorCompletedWithdrawalsList'])->middleware(['checkLogin'])->name('fetchDoctorCompletedWithdrawalsList');
Route::post('fetchDoctorRejectedWithdrawalsList', [DoctorController::class, 'fetchDoctorRejectedWithdrawalsList'])->middleware(['checkLogin'])->name('fetchDoctorRejectedWithdrawalsList');
Route::post('completeDoctorWithdrawal', [DoctorController::class, 'completeDoctorWithdrawal'])->middleware(['checkLogin'])->name('completeDoctorWithdrawal');
Route::post('rejectDoctorWithdrawal', [DoctorController::class, 'rejectDoctorWithdrawal'])->middleware(['checkLogin'])->name('rejectDoctorWithdrawal');

// Doctor Categories
Route::get('doctorCategories', [SettingsController::class, 'doctorCategories'])->middleware(['checkLogin'])->name('doctorCategories');
Route::post('fetchDoctorCatsList', [SettingsController::class, 'fetchDoctorCatsList'])->middleware(['checkLogin'])->name('fetchDoctorCatsList');
Route::post('addDoctorCat', [SettingsController::class, 'addDoctorCat'])->middleware(['checkLogin'])->name('addDoctorCat');
Route::post('editDoctorCat', [SettingsController::class, 'editDoctorCat'])->middleware(['checkLogin'])->name('editDoctorCat');
Route::get('deleteDoctorCat/{id}', [SettingsController::class, 'deleteDoctorCat'])->middleware(['checkLogin'])->name('deleteDoctorCat');
Route::post('fetchDoctorCatSuggestionsList', [SettingsController::class, 'fetchDoctorCatSuggestionsList'])->middleware(['checkLogin'])->name('fetchDoctorCatSuggestionsList');
Route::get('deleteDoctorCatSuggestion/{id}', [SettingsController::class, 'deleteDoctorCatSuggestion'])->middleware(['checkLogin'])->name('deleteDoctorCatSuggestion');

// Lowest Price Finder
Route::get('/lowest-price', [LowestPriceFinderController::class, 'index'])
    ->name('lowestprice');

Route::post('/lowestprice/fetch', [LowestPriceFinderController::class, 'fetch'])
    ->name('lowestprice.fetch');

Route::get('/get-hospitals', [LowestPriceFinderController::class, 'getHospitals']);
Route::get('/get-procedure-by-hospital/{id}', [LowestPriceFinderController::class, 'getProcedureByHospital']);

Route::post('/lowestprice/store', [LowestPriceFinderController::class, 'store']);
Route::post('/lowestprice/update', [LowestPriceFinderController::class, 'update']);
Route::post('/lowestprice/delete/{id}', [LowestPriceFinderController::class, 'delete']);


// Doctor Plans
Route::get('doctorPlans', [OnlineConsultationController::class, 'doctorPlans'])->middleware(['checkLogin'])->name('doctorPlans');
Route::post('fetchDoctorPlansList', [OnlineConsultationController::class, 'fetchDoctorPlansList'])->middleware(['checkLogin'])->name('fetchDoctorPlansList');
Route::post('addDoctorPlan', [OnlineConsultationController::class, 'addDoctorPlan'])->middleware(['checkLogin'])->name('addDoctorPlan');
Route::post('editDoctorPlan', [OnlineConsultationController::class, 'editDoctorPlan'])->middleware(['checkLogin'])->name('editDoctorPlan');
Route::get('deleteDoctorPlan/{id}', [OnlineConsultationController::class, 'deleteDoctorPlan'])->middleware(['checkLogin'])->name('deleteDoctorPlan');
Route::get('doctorPlan/getDoctors', [OnlineConsultationController::class, 'getDoctors'])->middleware(['checkLogin'])->name('getDoctors');

// Section sequence
Route::get('sectionSequence', [DashboardController::class, 'sectionSequence'])->middleware(['checkLogin'])->name('sectionSequence');
Route::post('fetchSectionSequence', [DashboardController::class, 'fetchSectionSequence'])->middleware(['checkLogin'])->name('fetchSectionSequence');
Route::post('sequenceUpdate', [DashboardController::class, 'sequenceUpdate'])->middleware(['checkLogin'])->name('sequenceUpdate');
Route::post('sequenceStatusUpdate', [DashboardController::class, 'sequenceStatusUpdate'])->middleware(['checkLogin'])->name('sequenceStatusUpdate');
Route::get('deleteSection/{id}', [DashboardController::class, 'deleteSection'])->middleware(['checkLogin'])->name('deleteSection');

// Dashboard Banners
Route::get('dashboardBanners', [DashboardController::class, 'dashboardBanners'])->middleware(['checkLogin'])->name('dashboardBanners');
Route::post('fetchDashboardBanners', [DashboardController::class, 'fetchDashboardBanners'])->middleware(['checkLogin'])->name('fetchDashboardBanners');
Route::post('addDashboardBanners', [DashboardController::class, 'addDashboardBanners'])->middleware(['checkLogin'])->name('addDashboardBanners');
Route::post('editDashboardBanners', [DashboardController::class, 'editDashboardBanners'])->middleware(['checkLogin'])->name('editDashboardBanners');
Route::get('deleteDashboardBanners/{id}', [DashboardController::class, 'deleteDashboardBanners'])->middleware(['checkLogin'])->name('deleteDashboardBanners');

// Doctors By Symptoms
Route::get('doctorsBySymptoms', [DashboardController::class, 'doctorsBySymptoms'])->middleware(['checkLogin'])->name('doctorsBySymptoms');
Route::post('fetchDoctorsBySymptoms', [DashboardController::class, 'fetchDoctorsBySymptoms'])->middleware(['checkLogin'])->name('fetchDoctorsBySymptoms');
Route::post('addDoctorsBySymptoms', [DashboardController::class, 'addDoctorsBySymptoms'])->middleware(['checkLogin'])->name('addDoctorsBySymptoms');
Route::post('editDoctorsBySymptoms', [DashboardController::class, 'editDoctorsBySymptoms'])->middleware(['checkLogin'])->name('editDoctorsBySymptoms');
Route::get('deleteDoctorsBySymptoms/{id}', [DashboardController::class, 'deleteDoctorsBySymptoms'])->middleware(['checkLogin'])->name('deleteDoctorsBySymptoms');

// Common Health Problems
Route::get('commonHealthProblems', [OnlineConsultationController::class, 'commonHealthProblems'])->middleware(['checkLogin'])->name('commonHealthProblems');
Route::post('fetchCommonHealthProblems', [OnlineConsultationController::class, 'fetchCommonHealthProblems'])->middleware(['checkLogin'])->name('fetchCommonHealthProblems');
Route::post('addCommonHealthProblems', [OnlineConsultationController::class, 'addCommonHealthProblems'])->middleware(['checkLogin'])->name('addCommonHealthProblems');
Route::post('editCommonHealthProblems', [OnlineConsultationController::class, 'editCommonHealthProblems'])->middleware(['checkLogin'])->name('editCommonHealthProblems');
Route::get('deleteCommonHealthProblems/{id}', [OnlineConsultationController::class, 'deleteCommonHealthProblems'])->middleware(['checkLogin'])->name('deleteCommonHealthProblems');

// Order Medicine 
Route::get('orderMedicineCategories', [OrderMedicineController::class, 'orderMedicineCategories'])->middleware(['checkLogin'])->name('orderMedicineCategories');
Route::post('fetchOrderMedicineCategoriesList', [OrderMedicineController::class, 'fetchOrderMedicineCategoriesList'])->middleware(['checkLogin'])->name('fetchOrderMedicineCategoriesList');
Route::post('addOrderMedicineCat', [OrderMedicineController::class, 'addOrderMedicineCat'])->middleware(['checkLogin'])->name('addOrderMedicineCat');
Route::post('editOrderMedicineCat', [OrderMedicineController::class, 'editOrderMedicineCat'])->middleware(['checkLogin'])->name('editOrderMedicineCat');
Route::get('deleteOrderMedicineCat/{id}', [OrderMedicineController::class, 'deleteOrderMedicineCat'])->middleware(['checkLogin'])->name('deleteOrderMedicineCat');

// Speciality Wise Disease
Route::get('SpecialityWiseDisease', [OnlineConsultationController::class, 'SpecialityWiseDisease'])->middleware(['checkLogin'])->name('SpecialityWiseDisease');
Route::post('fetchSpecialityWiseDisease', [OnlineConsultationController::class, 'fetchSpecialityWiseDisease'])->middleware(['checkLogin'])->name('fetchSpecialityWiseDisease');
Route::post('addSpecialityWiseDisease', [OnlineConsultationController::class, 'addSpecialityWiseDisease'])->middleware(['checkLogin'])->name('addSpecialityWiseDisease');
Route::post('editSpecialityWiseDisease', [OnlineConsultationController::class, 'editSpecialityWiseDisease'])->middleware(['checkLogin'])->name('editSpecialityWiseDisease');
Route::get('deleteSpecialityWiseDisease/{id}', [OnlineConsultationController::class, 'deleteSpecialityWiseDisease'])->middleware(['checkLogin'])->name('deleteSpecialityWiseDisease');

// Banners
Route::get('getSpecialities', [OnlineConsultationController::class, 'getSpecialities'])->name('getSpecialities');
Route::get('getCommonHealthProblems', [OnlineConsultationController::class, 'getCommonHealthProblems'])->name('getCommonHealthProblems');
Route::get('getSpecialityWiseDisease', [OnlineConsultationController::class, 'getSpecialityWiseDisease'])->name('getSpecialityWiseDisease');
Route::get('banners', [OnlineConsultationController::class, 'banners'])->middleware(['checkLogin'])->name('banners');
Route::post('fetchBanners', [OnlineConsultationController::class, 'fetchBanners'])->middleware(['checkLogin'])->name('fetchBanners');
Route::post('addBanner', [OnlineConsultationController::class, 'addBanner'])->middleware(['checkLogin'])->name('addBanner');
Route::post('editBanner', [OnlineConsultationController::class, 'editBanner'])->middleware(['checkLogin'])->name('editBanner');
Route::get('deleteBanner/{id}', [OnlineConsultationController::class, 'deleteBanner'])->middleware(['checkLogin'])->name('deleteBanner');

// partner network
Route::get('partnerNetwork', [UsersController::class, 'partnerNetwork'])->middleware(['checkLogin'])->name('partnerNetwork');
Route::post('fetchPartnerNetwork', [UsersController::class, 'fetchPartnerNetwork'])->middleware(['checkLogin'])->name('fetchPartnerNetwork');
Route::post('addPartnerNetwork', [UsersController::class, 'addPartnerNetwork'])->middleware(['checkLogin'])->name('addPartnerNetwork');
Route::post('editPartnerNetwork', [UsersController::class, 'editPartnerNetwork'])->middleware(['checkLogin'])->name('editPartnerNetwork');
Route::get('deletePartnerNetwork/{id}', [UsersController::class, 'deletePartnerNetwork'])->middleware(['checkLogin'])->name('deletePartnerNetwork');
Route::get('ViewPartner/{id}', [UsersController::class, 'ViewPartner'])->name('ViewPartner');

// Settings
Route::get('settings', [SettingsController::class, 'settings'])->middleware(['checkLogin'])->name('settings');
Route::get('emrMasterData', [SettingsController::class, 'emrMasterData'])->middleware(['checkLogin'])->name('emrMasterData');
Route::post('addEmrMasterData', [SettingsController::class, 'addEmrMasterData'])->middleware(['checkLogin'])->name('addEmrMasterData');
Route::post('deleteEmrMasterData/{id}', [SettingsController::class, 'deleteEmrMasterData'])->middleware(['checkLogin'])->name('deleteEmrMasterData');
Route::post('bulkUploadEmrMasterData', [SettingsController::class, 'bulkUploadEmrMasterData'])->middleware(['checkLogin'])->name('bulkUploadEmrMasterData');
Route::get('downloadEmrMasterTemplate', [SettingsController::class, 'downloadEmrMasterTemplate'])->middleware(['checkLogin'])->name('downloadEmrMasterTemplate');
Route::get('downloadLabOrderDummyExcel', [SettingsController::class, 'downloadLabOrderDummyExcel'])->middleware(['checkLogin'])->name('downloadLabOrderDummyExcel');
Route::post('updateGlobalSettings', [SettingsController::class, 'updateGlobalSettings'])->middleware(['checkLogin'])->name('updateGlobalSettings');
Route::post('changePassword', [SettingsController::class, 'changePassword'])->middleware(['checkLogin'])->name('changePassword');
Route::post('updatePaymentSettings', [SettingsController::class, 'updatePaymentSettings'])->middleware(['checkLogin'])->name('updatePaymentSettings');
Route::post('fetchAllTaxList', [SettingsController::class, 'fetchAllTaxList'])->middleware(['checkLogin'])->name('fetchAllTaxList');
Route::post('addTaxItem', [SettingsController::class, 'addTaxItem'])->middleware(['checkLogin'])->name('addTaxItem');
Route::post('editTaxItem', [SettingsController::class, 'editTaxItem'])->middleware(['checkLogin'])->name('editTaxItem');
Route::get('deleteTaxItem/{id}', [SettingsController::class, 'deleteTaxItem'])->middleware(['checkLogin'])->name('deleteTaxItem');
Route::get('changeTaxStatus/{id}/{value}', [SettingsController::class, 'changeTaxStatus'])->middleware(['checkLogin'])->name('changeTaxStatus');
Route::get('onOffChatBot/{value}', [SettingsController::class, 'onOffChatBot'])->middleware(['checkLogin'])->name('onOffChatBot');
Route::post('fetchAllRemindersList', [SettingsController::class, 'fetchAllRemindersList'])->middleware(['checkLogin'])->name('fetchAllRemindersList');
Route::post('addReminderItem', [SettingsController::class, 'addReminderItem'])->middleware(['checkLogin'])->name('addReminderItem');
Route::get('deleteRemindersItem/{id}', [SettingsController::class, 'deleteRemindersItem'])->middleware(['checkLogin'])->name('deleteRemindersItem');

// bulk upload doctor
Route::get('viewBulkUploadDoctors', [DoctorController::class, 'viewBulkUploadDoctors'])->middleware(['checkLogin'])->name('viewBulkUploadDoctors');
Route::post('bulkUploadDoctors', [DoctorController::class, 'bulkUploadDoctors'])->middleware(['checkLogin'])->name('bulkUploadDoctors');
Route::get('downloadBulkUploadDoctors', [DoctorController::class, 'downloadBulkUploadDoctors'])->middleware(['checkLogin'])->name('downloadBulkUploadDoctors');

Route::get('viewBulkUploadDHARegistrationAndSignature', [DoctorController::class, 'viewBulkUploadDHARegistrationAndSignature'])->middleware(['checkLogin'])->name('viewBulkUploadDHARegistrationAndSignature');
Route::post('bulkUploadDHARegistrationAndSignature', [DoctorController::class, 'bulkUploadDHARegistrationAndSignature'])->middleware(['checkLogin'])->name('bulkUploadDHARegistrationAndSignature');
Route::get('downloadDHARegistrationAndSignature', [DoctorController::class, 'downloadDHARegistrationAndSignature'])->middleware(['checkLogin'])->name('downloadDHARegistrationAndSignature');

Route::get('viewBulkUploadDoctorSlots', [DoctorController::class, 'viewBulkUploadDoctorSlots'])->middleware(['checkLogin'])->name('viewBulkUploadDoctorSlots');
Route::post('bulkUploadDoctorSlots', [DoctorController::class, 'bulkUploadDoctorSlots'])->middleware(['checkLogin'])->name('bulkUploadDoctorSlots');
Route::get('downloadDoctorSlotFormat', [DoctorController::class, 'downloadDoctorSlotFormat'])->middleware(['checkLogin'])->name('downloadDoctorSlotFormat');

Route::get('viewBulkUploadDoctorCategories', [DoctorController::class, 'viewBulkUploadDoctorCategories'])->middleware(['checkLogin'])->name('viewBulkUploadDoctorCategories');
Route::post('bulkUploadDoctorCategories', [DoctorController::class, 'bulkUploadDoctorCategories'])->middleware(['checkLogin'])->name('bulkUploadDoctorCategories');
Route::get('downloadDoctorCategoriesFormat', [DoctorController::class, 'downloadDoctorCategoriesFormat'])->middleware(['checkLogin'])->name('downloadDoctorCategoriesFormat');

Route::get('viewBulkUpdateDoctorCategories', [DoctorController::class, 'viewBulkUpdateDoctorCategories'])->middleware(['checkLogin'])->name('viewBulkUpdateDoctorCategories');
Route::get('viewBulkUpdateHospitalProcedures', [DoctorController::class, 'viewBulkUpdateHospitalProcedures'])->middleware(['checkLogin'])->name('viewBulkUpdateHospitalProcedures');

Route::post('bulkUpdateDoctorCategories', [DoctorController::class, 'bulkUpdateDoctorCategories'])->middleware(['checkLogin'])->name('bulkUpdateDoctorCategories');
Route::post('bulkUpdateHospitalProcedures', [DoctorController::class, 'bulkUpdateHospitalProcedures'])->middleware(['checkLogin'])->name('bulkUpdateHospitalProcedures');

Route::get('downloadDoctorUpdateCategoriesFormat', [DoctorController::class, 'downloadDoctorUpdateCategoriesFormat'])->middleware(['checkLogin'])->name('downloadDoctorUpdateCategoriesFormat');
Route::get('downloadHospitalUpdateProceduresFormat', [DoctorController::class, 'downloadHospitalUpdateProceduresFormat'])->middleware(['checkLogin'])->name('downloadHospitalUpdateProceduresFormat');


// Bulk update doctor mobile number
    Route::get('viewBulkUpdateDoctorMobile', [DoctorController::class, 'viewBulkUpdateDoctorMobile'])->middleware(['checkLogin'])->name('viewBulkUpdateDoctorMobile');
    Route::post('bulkUpdateDoctorMobile', [DoctorController::class, 'bulkUpdateDoctorMobile'])->middleware(['checkLogin'])->name('bulkUpdateDoctorMobile');
    Route::get('downloadBulkUpdateDoctorMobileFormat', [DoctorController::class, 'downloadBulkUpdateDoctorMobileFormat'])->middleware(['checkLogin'])->name('downloadBulkUpdateDoctorMobileFormat');

// Pages Routes
Route::get('viewPrivacy', [PagesController::class, 'viewPrivacy'])->middleware(['checkLogin'])->name('viewPrivacy');
Route::post('updatePrivacy', [PagesController::class, 'updatePrivacy'])->middleware(['checkLogin'])->name('updatePrivacy');
Route::get('viewTerms', [PagesController::class, 'viewTerms'])->middleware(['checkLogin'])->name('viewTerms');
Route::post('updateTerms', [PagesController::class, 'updateTerms'])->middleware(['checkLogin'])->name('updateTerms');
Route::get('privacypolicy', [PagesController::class, 'privacypolicy'])->name('privacypolicy');
Route::get('termsOfUse', [PagesController::class, 'termsOfUse'])->name('termsOfUse');

Route::get('viewMidasDescription', [PagesController::class, 'viewMidasDescription'])->middleware(['checkLogin'])->name('viewMidasDescription');
Route::post('updateMidasDescription', [PagesController::class, 'updateMidasDescription'])->middleware(['checkLogin'])->name('updateMidasDescription');
Route::get('appViewMidasDescription', [PagesController::class, 'appViewMidasDescription'])->name('appViewMidasDescription');

Route::get('viewHealthcheckDescription', [PagesController::class, 'viewHealthcheckDescription'])->middleware(['checkLogin'])->name('viewHealthcheckDescription');
Route::post('updateHealthcheckDescription', [PagesController::class, 'updateHealthcheckDescription'])->middleware(['checkLogin'])->name('updateHealthcheckDescription');
Route::get('appViewHealthcheckDescription', [PagesController::class, 'appViewHealthcheckDescription'])->name('appViewHealthcheckDescription');

Route::get('appviewTerms', [PagesController::class, 'appviewTerms'])->name('appviewTerms');
Route::get('appPrivacyView', [PagesController::class, 'appPrivacyView'])->name('appPrivacyView');

Route::get('viewHelpCenter', [PagesController::class, 'viewHelpCenter'])->name('viewHelpCenter');
Route::post('updateHelpCenter', [PagesController::class, 'updateHelpCenter'])->middleware(['checkLogin'])->name('updateHelpCenter');
Route::get('HelpCenter', [PagesController::class, 'HelpCenter'])->name('HelpCenter');
Route::get('appHelpCenterView', [PagesController::class, 'appHelpCenterView'])->name('appHelpCenterView');

// Cleanup Routes
Route::get('cleanDatabase', [SettingsController::class, 'cleanDatabase'])->name('cleanDatabase');

Route::get('viewVitalReport', [UsersController::class, 'viewVitalReport'])->name('viewVitalReport');

// SMO
Route::prefix('smo')->name('smo.')->group(function () {
    // SMO Section sequence
    Route::get('sectionSequence', [SMOController::class, 'sectionSequence'])->middleware(['checkLogin'])->name('sectionSequence');
    Route::post('fetchSectionSequence', [SMOController::class, 'fetchSectionSequence'])->middleware(['checkLogin'])->name('fetchSectionSequence');
    Route::post('sequenceUpdate', [SMOController::class, 'sequenceUpdate'])->middleware(['checkLogin'])->name('sequenceUpdate');
    Route::post('sequenceStatusUpdate', [SMOController::class, 'sequenceStatusUpdate'])->middleware(['checkLogin'])->name('sequenceStatusUpdate');
    Route::get('deleteSection/{id}', [SMOController::class, 'deleteSection'])->middleware(['checkLogin'])->name('deleteSection');

    // Top Hospitals
    Route::get('topHospitals', [SMOController::class, 'topHospitals'])->middleware(['checkLogin'])->name('topHospitals');
    Route::post('fetchTopHospitals', [SMOController::class, 'fetchTopHospitals'])->middleware(['checkLogin'])->name('fetchTopHospitals');
    Route::post('addTopHospitals', [SMOController::class, 'addTopHospitals'])->middleware(['checkLogin'])->name('addTopHospitals');
    Route::post('editTopHospitals', [SMOController::class, 'editTopHospitals'])->middleware(['checkLogin'])->name('editTopHospitals');
    Route::get('deleteTopHospitals/{id}', [SMOController::class, 'deleteTopHospitals'])->middleware(['checkLogin'])->name('deleteTopHospitals');

    // Hospital Categories
    Route::get('hospitalCategories', [SMOController::class, 'hospitalCategories'])->middleware(['checkLogin'])->name('hospitalCategories');
    Route::post('fetchHospitalCategories', [SMOController::class, 'fetchHospitalCategories'])->middleware(['checkLogin'])->name('fetchHospitalCategories');
    Route::post('addHospitalCategories', [SMOController::class, 'addHospitalCategories'])->middleware(['checkLogin'])->name('addHospitalCategories');
    Route::post('editHospitalCategories', [SMOController::class, 'editHospitalCategories'])->middleware(['checkLogin'])->name('editHospitalCategories');
    Route::get('deleteHospitalCategories/{id}', [SMOController::class, 'deleteHospitalCategories'])->middleware(['checkLogin'])->name('deleteHospitalCategories');

    // Hospital Procedures
    Route::get('gethospitalProcedures', [SMOController::class, 'gethospitalProcedures'])->middleware(['checkLogin'])->name('gethospitalProcedures');
    Route::get('hospitalProcedures', [SMOController::class, 'hospitalProcedures'])->middleware(['checkLogin'])->name('hospitalProcedures');
    Route::post('fetchHospitalProcedures', [SMOController::class, 'fetchHospitalProcedures'])->middleware(['checkLogin'])->name('fetchHospitalProcedures');
    Route::post('addHospitalProcedures', [SMOController::class, 'addHospitalProcedures'])->middleware(['checkLogin'])->name('addHospitalProcedures');
    Route::post('editHospitalProcedures', [SMOController::class, 'editHospitalProcedures'])->middleware(['checkLogin'])->name('editHospitalProcedures');
    Route::get('deleteHospitalProcedures/{id}', [SMOController::class, 'deleteHospitalProcedures'])->middleware(['checkLogin'])->name('deleteHospitalProcedures');

    // Query Procedures
    Route::get('queryProcedures', [SMOController::class, 'queryProcedures'])->middleware(['checkLogin'])->name('queryProcedures');
    Route::post('fetchQueryProcedures', [SMOController::class, 'fetchQueryProcedures'])->middleware(['checkLogin'])->name('fetchQueryProcedures');
    Route::post('addQueryProcedures', [SMOController::class, 'addQueryProcedures'])->middleware(['checkLogin'])->name('addQueryProcedures');
    Route::post('editQueryProcedures', [SMOController::class, 'editQueryProcedures'])->middleware(['checkLogin'])->name('editQueryProcedures');
    Route::get('deleteQueryProcedures/{id}', [SMOController::class, 'deleteQueryProcedures'])->middleware(['checkLogin'])->name('deleteQueryProcedures');

    // Trusted Healthcare Partners
    Route::get('trustedHealthcarePartners', [SMOController::class, 'trustedHealthcarePartners'])->middleware(['checkLogin'])->name('trustedHealthcarePartners');
    Route::post('fetchTrustedHealthcarePartners', [SMOController::class, 'fetchTrustedHealthcarePartners'])->middleware(['checkLogin'])->name('fetchTrustedHealthcarePartners');
    Route::post('addTrustedHealthcarePartners', [SMOController::class, 'addTrustedHealthcarePartners'])->middleware(['checkLogin'])->name('addTrustedHealthcarePartners');
    Route::post('editTrustedHealthcarePartners', [SMOController::class, 'editTrustedHealthcarePartners'])->middleware(['checkLogin'])->name('editTrustedHealthcarePartners');
    Route::get('deleteTrustedHealthcarePartners/{id}', [SMOController::class, 'deleteTrustedHealthcarePartners'])->middleware(['checkLogin'])->name('deleteTrustedHealthcarePartners');

    // Why second openion matters
    Route::get('whySecondOpinionMatters', [SMOController::class, 'whySecondOpinionMatters'])->middleware(['checkLogin'])->name('whySecondOpinionMatters');
    Route::post('fetchWhySecondOpinionMatters', [SMOController::class, 'fetchWhySecondOpinionMatters'])->middleware(['checkLogin'])->name('fetchWhySecondOpinionMatters');
    Route::post('addWhySecondOpinionMatters', [SMOController::class, 'addWhySecondOpinionMatters'])->middleware(['checkLogin'])->name('addWhySecondOpinionMatters');
    Route::post('editWhySecondOpinionMatters', [SMOController::class, 'editWhySecondOpinionMatters'])->middleware(['checkLogin'])->name('editWhySecondOpinionMatters');
    Route::get('deleteWhySecondOpinionMatters/{id}', [SMOController::class, 'deleteWhySecondOpinionMatters'])->middleware(['checkLogin'])->name('deleteWhySecondOpinionMatters');

    // Top Procedures
    Route::get('topProcedures', [SMOController::class, 'topProcedures'])->middleware(['checkLogin'])->name('topProcedures');
    Route::post('fetchTopProcedures', [SMOController::class, 'fetchTopProcedures'])->middleware(['checkLogin'])->name('fetchTopProcedures');
    Route::post('addTopProcedures', [SMOController::class, 'addTopProcedures'])->middleware(['checkLogin'])->name('addTopProcedures');
    Route::post('editTopProcedures', [SMOController::class, 'editTopProcedures'])->middleware(['checkLogin'])->name('editTopProcedures');
    Route::get('deleteTopProcedures/{id}', [SMOController::class, 'deleteTopProcedures'])->middleware(['checkLogin'])->name('deleteTopProcedures');
    Route::get('bulkUpdateProcedures', [SMOController::class, 'bulkUpdateProcedures'])->middleware(['checkLogin'])->name('bulkUpdateProcedures');

    // Top Procedures
    Route::get('mulkmedChoiceOfDoctors', [SMOController::class, 'mulkmedChoiceOfDoctors'])->middleware(['checkLogin'])->name('mulkmedChoiceOfDoctors');
    Route::post('fetchMulkmedChoiceOfDoctors', [SMOController::class, 'fetchMulkmedChoiceOfDoctors'])->middleware(['checkLogin'])->name('fetchMulkmedChoiceOfDoctors');
    Route::post('addMulkmedChoiceOfDoctors', [SMOController::class, 'addMulkmedChoiceOfDoctors'])->middleware(['checkLogin'])->name('addMulkmedChoiceOfDoctors');
    Route::post('editMulkmedChoiceOfDoctors', [SMOController::class, 'editMulkmedChoiceOfDoctors'])->middleware(['checkLogin'])->name('editMulkmedChoiceOfDoctors');
    Route::get('deleteMulkmedChoiceOfDoctors/{id}', [SMOController::class, 'deleteMulkmedChoiceOfDoctors'])->middleware(['checkLogin'])->name('deleteMulkmedChoiceOfDoctors');

    // Top unlock More Benefits Card
    Route::get('unlockMoreBenefitsCard', [SMOController::class, 'unlockMoreBenefitsCard'])->middleware(['checkLogin'])->name('unlockMoreBenefitsCard');
    Route::post('fetchUnlockMoreBenefitsCard', [SMOController::class, 'fetchUnlockMoreBenefitsCard'])->middleware(['checkLogin'])->name('fetchUnlockMoreBenefitsCard');
    Route::post('addUnlockMoreBenefitsCard', [SMOController::class, 'addUnlockMoreBenefitsCard'])->middleware(['checkLogin'])->name('addUnlockMoreBenefitsCard');
    Route::post('editUnlockMoreBenefitsCard', [SMOController::class, 'editUnlockMoreBenefitsCard'])->middleware(['checkLogin'])->name('editUnlockMoreBenefitsCard');
    Route::get('deleteUnlockMoreBenefitsCard/{id}', [SMOController::class, 'deleteUnlockMoreBenefitsCard'])->middleware(['checkLogin'])->name('deleteUnlockMoreBenefitsCard');

    // Submit By Query
    Route::get('submitYourQuery', [SMOController::class, 'submitYourQuery'])->middleware(['checkLogin'])->name('submitYourQuery');
    Route::post('fetchSubmitYourQuery', [SMOController::class, 'fetchSubmitYourQuery'])->middleware(['checkLogin'])->name('fetchSubmitYourQuery');
    Route::post('addSubmitYourQuery', [SMOController::class, 'addSubmitYourQuery'])->middleware(['checkLogin'])->name('addSubmitYourQuery');
    Route::post('editSubmitYourQuery', [SMOController::class, 'editSubmitYourQuery'])->middleware(['checkLogin'])->name('editSubmitYourQuery');
    Route::get('deleteSubmitYourQuery/{id}', [SMOController::class, 'deleteSubmitYourQuery'])->middleware(['checkLogin'])->name('deleteSubmitYourQuery');

     // SMO Query
    Route::get('SMOQueries', [SMOController::class, 'SMOQueries'])->middleware(['checkLogin'])->name('SMOQueries');
    Route::post('fetchSMOQueries', [SMOController::class, 'fetchSMOQueries'])->middleware(['checkLogin'])->name('fetchSMOQueries');
    Route::post('addSMOQueries', [SMOController::class, 'addSMOQueries'])->middleware(['checkLogin'])->name('addSMOQueries');
    Route::post('editSMOQueries', [SMOController::class, 'editSMOQueries'])->middleware(['checkLogin'])->name('editSMOQueries');
    Route::get('deleteSMOQueries/{id}', [SMOController::class, 'deleteSMOQueries'])->middleware(['checkLogin'])->name('deleteSMOQueries');

    // Hospitals
    Route::get('hospitals', [SMOController::class, 'hospitals'])->middleware(['checkLogin'])->name('hospitals');
    Route::post('fetchHospitals', [SMOController::class, 'fetchHospitals'])->middleware(['checkLogin'])->name('fetchHospitals');
    Route::post('addHospitals', [SMOController::class, 'addHospitals'])->middleware(['checkLogin'])->name('addHospitals');
    Route::post('editHospitals', [SMOController::class, 'editHospitals'])->middleware(['checkLogin'])->name('editHospitals');
    Route::get('deleteHospitals/{id}', [SMOController::class, 'deleteHospitals'])->middleware(['checkLogin'])->name('deleteHospitals');
    Route::get('getCategories', [SMOController::class, 'getCategories'])->middleware(['checkLogin'])->name('getCategories');
    Route::get('getProcedures', [SMOController::class, 'getProcedures'])->middleware(['checkLogin'])->name('getProcedures');
  
    Route::get('viewBulkUploadHospitals', [SMOController::class, 'viewBulkUploadHospitals'])->middleware(['checkLogin'])->name('viewBulkUploadHospitals');
    Route::post('bulkUploadHospitals', [SMOController::class, 'bulkUploadHospitals'])->middleware(['checkLogin'])->name('bulkUploadHospitals');
    Route::get('downloadHospitalFormat', [SMOController::class, 'downloadHospitalFormat'])->middleware(['checkLogin'])->name('downloadHospitalFormat'); 

    Route::get('viewBulkUploadHospitalProcedures', [SMOController::class, 'viewBulkUploadHospitalProcedures'])->middleware(['checkLogin'])->name('viewBulkUploadHospitalProcedures');
    Route::post('bulkUploadHospitalProcedures', [SMOController::class, 'bulkUploadHospitalProcedures'])->middleware(['checkLogin'])->name('bulkUploadHospitalProcedures');
    Route::get('downloadHospitalProceduresFormat', [SMOController::class, 'downloadHospitalProceduresFormat'])->middleware(['checkLogin'])->name('downloadHospitalProceduresFormat');
   
    Route::get('viewBulkUploadHospitalProcedurePrice', [SMOController::class, 'viewBulkUploadHospitalProcedurePrice'])->middleware(['checkLogin'])->name('viewBulkUploadHospitalProcedurePrice');
    Route::post('bulkUploadHospitalProcedurePrice', [SMOController::class, 'bulkUploadHospitalProcedurePrice'])->middleware(['checkLogin'])->name('bulkUploadHospitalProcedurePrice');
    Route::get('downloadHospitalProcedurePriceFormat', [SMOController::class, 'downloadHospitalProcedurePriceFormat'])->middleware(['checkLogin'])->name('downloadHospitalProcedurePriceFormat');

    Route::get('viewBulkUploadQueryProcedures', [SMOController::class, 'viewBulkUploadQueryProcedures'])->middleware(['checkLogin'])->name('viewBulkUploadQueryProcedures');
    Route::post('bulkUploadQueryProcedures', [SMOController::class, 'bulkUploadQueryProcedures'])->middleware(['checkLogin'])->name('bulkUploadQueryProcedures');
    Route::get('downloadQueryProceduresFormat', [SMOController::class, 'downloadQueryProceduresFormat'])->middleware(['checkLogin'])->name('downloadQueryProceduresFormat');

    Route::get('viewBulkUploadTopHospital', [SMOController::class, 'viewBulkUploadTopHospital'])->middleware(['checkLogin'])->name('viewBulkUploadTopHospital');
    Route::post('bulkUploadTopHospitals', [SMOController::class, 'bulkUploadTopHospitals'])->middleware(['checkLogin'])->name('bulkUploadTopHospitals');
    Route::get('downloadTopHospitalFormat', [SMOController::class, 'downloadTopHospitalFormat'])->middleware(['checkLogin'])->name('downloadTopHospitalFormat');
   
    Route::get('viewBulkUploadWhySecondOpinionMatters', [SMOController::class, 'viewBulkUploadWhySecondOpinionMatters'])->middleware(['checkLogin'])->name('viewBulkUploadWhySecondOpinionMatters');
    Route::post('bulkUploadWhySecondOpinionMatters', [SMOController::class, 'bulkUploadWhySecondOpinionMatters'])->middleware(['checkLogin'])->name('bulkUploadWhySecondOpinionMatters');
    Route::get('downloadWhySecondOpinionMattersFormat', [SMOController::class, 'downloadWhySecondOpinionMattersFormat'])->middleware(['checkLogin'])->name('downloadWhySecondOpinionMattersFormat');

    Route::get('viewBulkUploadTrustedHealthcarePartners', [SMOController::class, 'viewBulkUploadTrustedHealthcarePartners'])->middleware(['checkLogin'])->name('viewBulkUploadTrustedHealthcarePartners');
    Route::post('bulkUploadTrustedHealthcarePartners', [SMOController::class, 'bulkUploadTrustedHealthcarePartners'])->middleware(['checkLogin'])->name('bulkUploadTrustedHealthcarePartners');
    Route::get('downloadTrustedHealthcarePartnersFormat', [SMOController::class, 'downloadTrustedHealthcarePartnersFormat'])->middleware(['checkLogin'])->name('downloadTrustedHealthcarePartnersFormat');

    Route::get('viewBulkUploadHospitalCategories', [SMOController::class, 'viewBulkUploadHospitalCategories'])->middleware(['checkLogin'])->name('viewBulkUploadHospitalCategories');
    Route::post('bulkUploadHospitalCategories', [SMOController::class, 'bulkUploadHospitalCategories'])->middleware(['checkLogin'])->name('bulkUploadHospitalCategories');
    Route::get('downloadHospitalCategoriesFormat', [SMOController::class, 'downloadHospitalCategoriesFormat'])->middleware(['checkLogin'])->name('downloadHospitalCategoriesFormat');

    Route::get('viewBulkUploadTopProcedures', [SMOController::class, 'viewBulkUploadTopProcedures'])->middleware(['checkLogin'])->name('viewBulkUploadTopProcedures');
    Route::post('bulkUploadTopProcedures', [SMOController::class, 'bulkUploadTopProcedures'])->middleware(['checkLogin'])->name('bulkUploadTopProcedures');
    Route::get('downloadTopProceduresFormat', [SMOController::class, 'downloadTopProceduresFormat'])->middleware(['checkLogin'])->name('downloadTopProceduresFormat');

    Route::get('getHospitals', [SMOController::class, 'getHospitals'])->middleware(['checkLogin'])->name('getHospitals');
    Route::get('getDoctors', [SMOController::class, 'getDoctors'])->middleware(['checkLogin'])->name('getDoctorss');
    Route::get('getHospitalCategories', [SMOController::class, 'getHospitalCategories'])->middleware(['checkLogin'])->name('getHospitalCategories');
    Route::get('getHospitalProcedures', [SMOController::class, 'getHospitalProcedures'])->middleware(['checkLogin'])->name('getHospitalProcedures');

    // SMO Queries
    Route::get('SMOQueries', [SMOController::class, 'SMOQueries'])->middleware(['checkLogin'])->name('SMOQueries');
    Route::post('fetchSMOQueries', [SMOController::class, 'fetchSMOQueries'])->middleware(['checkLogin'])->name('fetchSMOQueries');
});

// Bidding
Route::prefix('bidding')->name('bidding.')->group(function () {
    // banners
    Route::get('biddingBanners', [BiddingController::class, 'biddingBanners'])->middleware(['checkLogin'])->name('biddingBanners');
    Route::post('fetchBiddingBanners', [BiddingController::class, 'fetchBiddingBanners'])->middleware(['checkLogin'])->name('fetchBiddingBanners');
    Route::post('addBiddingBanners', [BiddingController::class, 'addBiddingBanners'])->middleware(['checkLogin'])->name('addBiddingBanners');
    Route::post('editBiddingBanners', [BiddingController::class, 'editBiddingBanners'])->middleware(['checkLogin'])->name('editBiddingBanners');
    Route::get('deleteBiddingBanners/{id}', [BiddingController::class, 'deleteBiddingBanners'])->middleware(['checkLogin'])->name('deleteBiddingBanners');

    // Bidding Services
    Route::get('biddingServices', [BiddingController::class, 'biddingServices'])->middleware(['checkLogin'])->name('biddingServices');
    Route::post('fetchBiddingServices', [BiddingController::class, 'fetchBiddingServices'])->middleware(['checkLogin'])->name('fetchBiddingServices');
    Route::post('addBiddingServices', [BiddingController::class, 'addBiddingServices'])->middleware(['checkLogin'])->name('addBiddingServices');
    Route::post('editBiddingServices', [BiddingController::class, 'editBiddingServices'])->middleware(['checkLogin'])->name('editBiddingServices');
    Route::get('deleteBiddingServices/{id}', [BiddingController::class, 'deleteBiddingServices'])->middleware(['checkLogin'])->name('deleteBiddingServices');

    // Bidding Data
    Route::get('bidSubmitted', [BiddingController::class, 'bidSubmitted'])->middleware(['checkLogin'])->name('bidSubmitted');
    Route::post('fetchBidData', [BiddingController::class, 'fetchBidData'])->middleware(['checkLogin'])->name('fetchBidData');
    Route::post('addBidData', [BiddingController::class, 'addBidData'])->middleware(['checkLogin'])->name('addBidData');
    Route::post('editBidData', [BiddingController::class, 'editBidData'])->middleware(['checkLogin'])->name('editBidData');
    Route::get('deleteBidData/{id}', [BiddingController::class, 'deleteBidData'])->middleware(['checkLogin'])->name('deleteBidData');

    // Bidding Submit Banner
    Route::get('biddingSubmitBanners', [BiddingController::class, 'biddingSubmitBanners'])->middleware(['checkLogin'])->name('biddingSubmitBanners');
    Route::post('fetchBiddingSubmitBanners', [BiddingController::class, 'fetchBiddingSubmitBanners'])->middleware(['checkLogin'])->name('fetchBiddingSubmitBanners');
    Route::post('addBiddingSubmitBanners', [BiddingController::class, 'addBiddingSubmitBanners'])->middleware(['checkLogin'])->name('addBiddingSubmitBanners');
    Route::post('editBiddingSubmitBanners', [BiddingController::class, 'editBiddingSubmitBanners'])->middleware(['checkLogin'])->name('editBiddingSubmitBanners');
    Route::get('deleteBiddingSubmitBanners/{id}', [BiddingController::class, 'deleteBiddingSubmitBanners'])->middleware(['checkLogin'])->name('deleteBiddingSubmitBanners');

    // Bulk Upload Services
    Route::get('viewBulkUploadBiddingServices', [BiddingController::class, 'viewBulkUploadBiddingServices'])->middleware(['checkLogin'])->name('viewBulkUploadBiddingServices');
    Route::post('bulkUploadBiddingServices', [BiddingController::class, 'bulkUploadBiddingServices'])->middleware(['checkLogin'])->name('bulkUploadBiddingServices');
    Route::get('downloadBiddingServicesFormat', [BiddingController::class, 'downloadBiddingServicesFormat'])->middleware(['checkLogin'])->name('downloadBiddingServicesFormat');
});
  
// Best Offers
Route::prefix('bestOffers')->name('bestOffers.')->group(function () {
    // banners
    Route::get('viewBestOffersPlans', [BestOffersController::class, 'viewBestOffersPlans'])->middleware(['checkLogin'])->name('viewBestOffersPlans');
    Route::post('fetchBestOffersPlans', [BestOffersController::class, 'fetchBestOffersPlans'])->middleware(['checkLogin'])->name('fetchBestOffersPlans');
    Route::post('addBestOffersPlans', [BestOffersController::class, 'addBestOffersPlans'])->middleware(['checkLogin'])->name('addBestOffersPlans');
    Route::post('editBestOffersPlans', [BestOffersController::class, 'editBestOffersPlans'])->middleware(['checkLogin'])->name('editBestOffersPlans');
    Route::get('deleteBestOffersPlans/{id}', [BestOffersController::class, 'deleteBestOffersPlans'])->middleware(['checkLogin'])->name('deleteBestOffersPlans');

    // users list
    Route::get('viewBestOffersPlanUsers', [BestOffersController::class, 'viewBestOffersPlanUsers'])->middleware(['checkLogin'])->name('viewBestOffersPlanUsers');
    Route::post('fetchBestOffersPlanUsers', [BestOffersController::class, 'fetchBestOffersPlanUsers'])->middleware(['checkLogin'])->name('fetchBestOffersPlanUsers');
    Route::get('viewUserPlans/{user_id}', [BestOffersController::class, 'viewUserPlans'])->middleware(['checkLogin'])->name('viewUserPlans');
    Route::post('fetchUserOrders/{user_id}', [BestOffersController::class, 'fetchUserOrders'])->middleware(['checkLogin'])->name('fetchUserOrders');
});

// Major Organ Tests
Route::prefix('majorOrganTests')->name('majorOrganTests.')->group(function () {
    Route::get('/', [MajorOrganTestController::class, 'index'])->middleware(['checkLogin'])->name('index');
    Route::post('fetch', [MajorOrganTestController::class, 'fetchOrganTests'])->middleware(['checkLogin'])->name('fetch');
    Route::post('add', [MajorOrganTestController::class, 'addOrganTest'])->middleware(['checkLogin'])->name('add');
    Route::post('edit', [MajorOrganTestController::class, 'editOrganTest'])->middleware(['checkLogin'])->name('edit');
    Route::get('delete/{id}', [MajorOrganTestController::class, 'deleteOrganTest'])->middleware(['checkLogin'])->name('delete');
    Route::get('preview', [MajorOrganTestController::class, 'previewOrganTests'])->middleware(['checkLogin'])->name('preview');
    Route::get('package', [MajorOrganTestController::class, 'getPackage'])->middleware(['checkLogin'])->name('package.get');
    Route::post('package', [MajorOrganTestController::class, 'savePackage'])->middleware(['checkLogin'])->name('package.save');
});

// Longevity Plans
Route::prefix('longevityPlans')->name('longevityPlans.')->group(function () {
    Route::get('/', [LongevityPlanController::class, 'index'])->middleware(['checkLogin'])->name('index');
    Route::post('fetch', [LongevityPlanController::class, 'fetch'])->middleware(['checkLogin'])->name('fetch');
    Route::post('add', [LongevityPlanController::class, 'add'])->middleware(['checkLogin'])->name('add');
    Route::post('edit', [LongevityPlanController::class, 'edit'])->middleware(['checkLogin'])->name('edit');
    Route::get('delete/{id}', [LongevityPlanController::class, 'delete'])->middleware(['checkLogin'])->name('delete');
});

// 
Route::get('HnHPointSetting', [HnHController::class, 'HnHPointSetting'])->middleware(['checkLogin'])->name('HnHPointSetting');
Route::post('fetchHnHPointSetting', [HnHController::class, 'fetchHnHPointSetting'])->middleware(['checkLogin'])->name('fetchHnHPointSetting');
Route::post('editHnHPointSetting', [HnHController::class, 'editHnHPointSetting'])->middleware(['checkLogin'])->name('editHnHPointSetting');

Route::prefix('HNH')->name('HNH.')->group(function () {
    // HNH
    Route::get('HnHCards', [HnHController::class, 'HnHCards'])->middleware(['checkLogin'])->name('HnHCards');
    Route::post('fetchHnHCards', [HnHController::class, 'fetchHnHCards'])->middleware(['checkLogin'])->name('fetchHnHCards');
});

Route::prefix('senior')->name('senior.')->group(function () {
    // Senior Cards
    Route::get('seniorCards', [SeniorCardController::class, 'seniorCards'])->middleware(['checkLogin'])->name('seniorCards');
    Route::post('fetchSeniorCards', [SeniorCardController::class, 'fetchSeniorCards'])->middleware(['checkLogin'])->name('fetchSeniorCards');
});

Route::prefix('tourist')->name('tourist.')->group(function () {
    Route::get('touristCards', [TouristCardController::class, 'touristCards'])->middleware(['checkLogin'])->name('touristCards');
    Route::post('fetchTouristCards', [TouristCardController::class, 'fetchTouristCards'])->middleware(['checkLogin'])->name('fetchTouristCards');
});

// Patient Registration
Route::prefix('patientAppointment')->name('patientAppointment.')->group(function () {
    Route::get('createRegistration', [PatientAppointmentController::class, 'createRegistration'])->middleware(['checkLogin'])->name('createRegistration');
    Route::post('storeRegistration', [PatientAppointmentController::class, 'storeRegistration'])->middleware(['checkLogin'])->name('storeRegistration');
    
    Route::get('createAppointment', [PatientAppointmentController::class, 'createAppointment'])->middleware(['checkLogin'])->name('createAppointment');
    Route::post('storeAppointment', [PatientAppointmentController::class, 'storeAppointment'])->middleware(['checkLogin'])->name('storeAppointment');
});

// Travel Flow Banner
Route::middleware('checkLogin')->group(function () {

    Route::get('touristList', [TravelFlowBannerController::class, 'touristList'])
        ->name('touristList');

    Route::get('fetchTouristList', [TravelFlowBannerController::class, 'fetchTouristList'])
        ->name('fetchTouristList');

    Route::get('travelFlowBanner', [TravelFlowBannerController::class, 'travelFlowBanner'])
        ->name('travelFlowBanner');

    Route::post('fetchTravelFlowBanner', [TravelFlowBannerController::class, 'fetchTravelFlowBanner'])
        ->name('fetchTravelFlowBanner');

    Route::post('addTravelFlowBanner', [TravelFlowBannerController::class, 'addTravelFlowBanner'])
        ->name('addTravelFlowBanner');

    Route::post('editTravelFlowBanner', [TravelFlowBannerController::class, 'editTravelFlowBanner'])
        ->name('editTravelFlowBanner');

    Route::get('deleteTravelFlowBanner/{id}', [TravelFlowBannerController::class, 'deleteTravelFlowBanner'])
        ->name('deleteTravelFlowBanner');

    Route::post('addAgencyType', [TravelFlowBannerController::class, 'addAgencyType'])
        ->name('addAgencyType');  

    Route::get('getAgencyType', [TravelFlowBannerController::class, 'getAgencyType'])
        ->name('getAgencyType');

    Route::post('addAgency', [TravelFlowBannerController::class, 'addAgency'])
        ->name('addAgency');  

    Route::get('getAgency', [TravelFlowBannerController::class, 'getAgency'])
        ->name('getAgency');
    
    Route::get('fetchAllAgencies', [TravelFlowBannerController::class, 'fetchAllAgencies'])
        ->name('fetchAllAgencies');

    Route::get('editAgency/{id}', [TravelFlowBannerController::class, 'editAgency'])
        ->name('editAgency');

    Route::post('updateAgency', [TravelFlowBannerController::class, 'updateAgency'])
        ->name('updateAgency'); 

    Route::get('deleteAgency/{id}', [TravelFlowBannerController::class, 'deleteAgency'])
        ->name('deleteAgency');

    Route::post('addProductPlan', [TravelFlowBannerController::class, 'addProductPlan'])
        ->name('addProductPlan');  

    Route::get('getProductPlan', [TravelFlowBannerController::class, 'getProductPlan'])
        ->name('getProductPlan');

    Route::get('editProductPlan/{id}', [TravelFlowBannerController::class, 'editProductPlan'])
        ->name('editProductPlan'); 

    Route::post('updateProductPlan', [TravelFlowBannerController::class, 'updateProductPlan'])
        ->name('updateProductPlan'); 

    Route::get('deleteProductPlan/{id}', [TravelFlowBannerController::class, 'deleteProductPlan'])
        ->name('deleteProductPlan'); 

    Route::post('addRiderAllocation', [TravelFlowBannerController::class, 'addRiderAllocation'])
        ->name('addRiderAllocation');  

    Route::get('getRiderAllocation', [TravelFlowBannerController::class, 'getRiderAllocation'])
        ->name('getRiderAllocation'); 

    Route::post('updateRiderAllocation', [TravelFlowBannerController::class, 'updateRiderAllocation'])
        ->name('updateRiderAllocation'); 
    
    Route::get('getTransactionHistory', [TravelFlowBannerController::class, 'getTransactionHistory'])
        ->name('getTransactionHistory'); 

    Route::get('getAgencyCount', [TravelFlowBannerController::class, 'getAgencyCount'])
        ->name('getAgencyCount'); 

    Route::post('getAgencyInfo', [TravelFlowBannerController::class, 'getAgencyInfo'])
        ->name('getAgencyInfo'); 

    Route::get('touristAppointments', [TouristAppointmentController::class, 'touristAppointments'])
        ->name('touristAppointments'); 

    Route::post('fetchAllTouristAppointmentsList', [TouristAppointmentController::class, 'fetchAllTouristAppointmentsList'])
        ->name('fetchAllTouristAppointmentsList');

    Route::post('fetchPendingTouristAppointmentsList', [TouristAppointmentController::class, 'fetchPendingTouristAppointmentsList'])
        ->name('fetchPendingTouristAppointmentsList');

    Route::post('fetchAcceptedTouristAppointmentsList', [TouristAppointmentController::class, 'fetchAcceptedTouristAppointmentsList'])
        ->name('fetchAcceptedTouristAppointmentsList');

    Route::post('fetchCompletedTouristAppointmentsList', [TouristAppointmentController::class, 'fetchCompletedTouristAppointmentsList'])
        ->name('fetchCompletedTouristAppointmentsList');

    Route::post('fetchCancelledTouristAppointmentsList', [TouristAppointmentController::class, 'fetchCancelledTouristAppointmentsList'])
        ->name('fetchCancelledTouristAppointmentsList');

    Route::post('fetchMissedTouristAppointmentsList', [TouristAppointmentController::class, 'fetchMissedTouristAppointmentsList'])
        ->name('fetchMissedTouristAppointmentsList');

    Route::post('fetchDeclinedTouristAppointmentsList', [TouristAppointmentController::class, 'fetchDeclinedTouristAppointmentsList'])
        ->name('fetchDeclinedTouristAppointmentsList');

    Route::get('viewTouristAppointment/{id}', [TouristAppointmentController::class, 'viewTouristAppointment'])->name('viewTouristAppointment');

});

// Tourist management
Route::prefix('rider-agency')->name('rideragency.')->group(function () {

    /* ================= DASHBOARD ================= */
    Route::get('/dashboard', function () {
        return view('rideragency.dashboard');
    })->name('dashboard');

    /* ================= AGENCIES ================= */
    Route::get('/agencies', function () {
        return view('rideragency.agencies');
    })->name('agencies');

    Route::get('/agency-report', function () {
        return view('rideragency.agency-report');
    })->name('agency.report');

      /* ================= MY PRODUCT PLAN ================= */
    Route::get('/product-plan', function () {
        return view('rideragency.my-product-plan');
    })->name('product.plan');

    /* ================= RIDER ALLOCATION ================= */

    // ✅ FIX: Parent route REDIRECTS (no view needed)
    Route::get('/rider-allocation', function () {
        return redirect()->route('rideragency.plan');
    })->name('allocation');

    // ✅ Plan Allocation
    Route::get('/rider-allocation/plan', function () {
        return view('rideragency.plan');
    })->name('plan');

    // ✅ Allocated Agencies
    Route::get('/rider-allocation/allocated-agencies', function () {
        return view('rideragency.allocated-agencies');
    })->name('allocation.list');

    /* ================= UPLOAD HISTORY ================= */
    Route::get('/upload-history', function () {
        return view('rideragency.upload-history');
    })->name('upload-history');

        /* ================= TRANSACTION SUMMARY (Unified) ================= */
    Route::get('/transaction-summary', function () {
        return view('rideragency.transaction-summary');
    })->name('transaction.summary');

    Route::get('/transaction-details', function () {
        return view('rideragency.transaction-summary');
    })->name('transaction-details');

    Route::get('/rider-usage', function () {
        return view('rideragency.transaction-summary');
    })->name('rider-usage');

});
Route::get('/partner/subscription', [PartnerController::class, 'subscription'])
        ->name('partner.subscription');
Route::prefix('partner')->group(function () {

    Route::get('/login', [PartnerController::class, 'showLogin'])->name('partner.login');

    Route::post('/login', [PartnerController::class, 'login'])->name('partner.login.submit');

    Route::post('/logout', [PartnerController::class, 'logout'])
        ->middleware(['checkPartnerLogin'])->name('partner.logout');

    Route::get('/dashboard', [PartnerController::class, 'dashboard'])
        ->middleware(['checkPartnerLogin'])->name('partner.dashboard');

    Route::get('/getSubscriptionInfo', [PartnerController::class, 'getSubscriptionInfo'])
        ->middleware(['checkPartnerLogin'])->name('partner.getSubscriptionInfo');

    Route::get('/getSubscriptionHistory', [PartnerController::class, 'getSubscriptionHistory'])
        ->middleware(['checkPartnerLogin'])->name('getSubscriptionHistory');  

    Route::post('/addAgencySubscriptionPlan', [PartnerController::class, 'addAgencySubscriptionPlan'])
        ->middleware(['checkPartnerLogin'])->name('addAgencySubscriptionPlan');

    Route::post('/paymentInitiateForPostpaid', [PartnerController::class, 'paymentInitiateForPostpaid'])
        ->middleware(['checkPartnerLogin'])->name('paymentInitiateForPostpaid'); 

    Route::get('/getTransactionHistory', [PartnerController::class, 'getTransactionHistory']); 

    // ===== EXCEL (TYPE FROM SESSION) =====
    Route::get('/excel', fn () =>
        view('partnerportal.excel', [
            'partnerType' => session('partner_type', 'hotel')
            // hotel | travel | visa
        ])
    )->name('partner.excel');

    // ===== UPLOAD HISTORY =====
    Route::get('/upload-history', fn () =>
        view('partnerportal.upload-history')
    )->name('partner.upload-history');

});

// Route::get('/addAgencySubscriptionPlan', [PartnerController::class, 'addAgencySubscriptionPlan']);
Route::get('/paymentSuccess', [PartnerController::class, 'paymentSuccess']); 
// Route::get('/plan-payment-response', [PartnerController::class, 'paymentResponse']); 
// Route::get('/plan-payment-cancel', [PartnerController::class, 'payment_cancel']); 

Route::post('/plan-payment-response', [PartnerController::class, 'paymentResponse'])->withoutMiddleware([
        'auth',
        'auth:sanctum',
        'web'
    ]);
Route::post('/plan-payment-cancel', [PartnerController::class, 'payment_cancel'])->withoutMiddleware([
        'auth',
        'auth:sanctum',
        'web'
    ]); 

Route::get('/tourist/import', [TouristImportController::class, 'index']);
Route::post('/tourist/import', [TouristImportController::class, 'store']);

Route::get('partner/import-logs', [TouristImportController::class, 'logsByAgent']);
Route::get('partner/import-log/{log_id}/tourists', [TouristImportController::class, 'touristsByLog']);
Route::get('partner/import-log/tourists-list', [TouristImportController::class, 'touristsList']);
Route::get('/downloadSubscriptionInvoice', [PartnerController::class, 'downloadSubscriptionInvoice']); 

Route::get('agencies-dropdown', [PartnerController::class, 'dropdown']);
Route::get('admin/import-logs', [TouristImportController::class, 'logsByAdmin']);
Route::get('admin/downloadTouristExcel', [TravelFlowBannerController::class, 'downloadTouristExcel']);

Route::get('/download-subscription-invoicebyadmin', [PartnerController::class, 'downloadSubscriptionInvoiceByAdmin']); 

Route::get('admin/import-log/{log_id}/tourists', [TouristImportController::class, 'touristsAdminByLog']);

Route::post('/updateDoctorPassword', [DoctorController::class, 'updateDoctorPassword'])->name('updateDoctorPassword');



//for testing classification 
Route::get('/senoclock/test-classification', [SenoclockTestController::class, 'index'])->name('senoclock.test.index');
Route::post('/senoclock/test/login', [SenoclockTestController::class, 'login'])->name('senoclock.test.login');
Route::post('/senoclock/test/classification', [SenoclockTestController::class, 'classification'])->name('senoclock.test.classification');


Route::get('test-classification', [SenoclockController::class, 'index'])->name('testClassification');
Route::post('test-classification/login', [SenoclockController::class, 'testLogin'])->name('testClassificationLogin');
Route::post('test-classification/trigger', [SenoclockController::class, 'triggerClassification'])->name('testClassificationTrigger');

