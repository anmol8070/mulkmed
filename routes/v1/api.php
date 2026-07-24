<?php

use App\Http\Controllers\v1\BestOffersController;
use App\Http\Controllers\v1\MajorOrganTestController;
use App\Http\Controllers\v1\AppointmentController;
use App\Http\Controllers\v1\DoctorController;
use App\Http\Controllers\v1\DoctorEmrController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\v1\LowestPriceFinderController;
use App\Http\Controllers\v1\SettingsController;
use App\Http\Controllers\v1\UsersController;
use App\Http\Controllers\v1\CcavenueController;
use App\Http\Controllers\v1\IsabelController;
use App\Http\Controllers\v1\SMOController;
use App\Http\Controllers\v1\BiddingController;
use App\Http\Controllers\v1\TouristController;
use App\Http\Controllers\v1\TravelerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Controllers\ConsultController;
use App\Http\Controllers\v1\PatientEmrReportController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('storage/{path}', function ($path) {
        // normalize & guard
        $path = ltrim($path, '/');
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        // stream the file with correct mime
        $mime = Storage::disk('public')->mimeType($path) ?? 'application/octet-stream';
        return new StreamedResponse(function () use ($path) {
            $stream = Storage::disk('public')->readStream($path);
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, ['Content-Type' => $mime, 'Cache-Control' => 'public, max-age=31536000']);
    })->where('path', '.*');

    Route::get('getBaseUrl', [SettingsController::class, 'getBaseUrl'])->name('getBaseUrl');


    //******************/ Users
    Route::prefix('user')->group(function () {
        // Common
        Route::post('checkAvailability', [UsersController::class, 'checkAvailability'])->middleware('checkHeader');
        Route::post('registerUser', [UsersController::class, 'registerUser'])->middleware('checkHeader');
        Route::post('loginUser', [UsersController::class, 'loginUser'])->middleware('checkHeader');
        Route::post('updateUserDetails', [UsersController::class, 'updateUserDetails'])->middleware('checkHeader');
        Route::post('deleteUserAccount', [UsersController::class, 'deleteUserAccount'])->middleware('checkHeader');
        Route::post('fetchMyUserDetails', [UsersController::class, 'fetchMyUserDetails'])->middleware('checkHeader');
        Route::post('addPatient', [UsersController::class, 'addPatient'])->middleware('checkHeader');
        Route::post('editPatient', [UsersController::class, 'editPatient'])->middleware('checkHeader');
        Route::post('deletePatient', [UsersController::class, 'deletePatient'])->middleware('checkHeader');
        Route::post('fetchPatients', [UsersController::class, 'fetchPatients'])->middleware('checkHeader');
        Route::post('fetchFavoriteDoctors', [UsersController::class, 'fetchFavoriteDoctors'])->middleware('checkHeader');
        Route::post('fetchHomePageData', [UsersController::class, 'fetchHomePageData'])->middleware('checkHeader');
        Route::get('dashboard', [UsersController::class, 'dashboard'])->middleware('checkHeader');
        Route::get('translate', [UsersController::class, 'translate'])->middleware('checkHeader');
        Route::post('fetchDetails', [UsersController::class, 'fetchDetails'])->middleware('checkHeader');
        Route::post('searchDoctor', [DoctorController::class, 'searchDoctor'])->middleware('checkHeader');
        Route::post('fetchDoctorProfile', [DoctorController::class, 'fetchDoctorProfile'])->middleware('checkHeader');
        Route::post('fetchDoctorReviews', [DoctorController::class, 'fetchDoctorReviews'])->middleware('checkHeader');
        Route::post('submitDoctorReviews', [DoctorController::class, 'submitDoctorReviews'])->middleware('checkHeader');
        Route::post('fetchDoctorPlansAndSlots', [DoctorController::class, 'fetchDoctorPlansAndSlots'])->middleware('checkHeader');
        Route::post('logOut', [UsersController::class, 'logOut'])->middleware('checkHeader');
        // mobile number verification
        Route::post('send_otp', [UsersController::class, 'send_otp'])->middleware('checkHeader');
        Route::post('otp_verify', [UsersController::class, 'otp_verify'])->middleware('checkHeader');

        //update-user-fcm
        Route::post('update-user-fcm', [UsersController::class, 'updateUserFCM']);

        // forget_username
        Route::post('sendUsernameReminder', [UsersController::class, 'sendUsernameReminder'])->middleware('checkHeader');
        Route::post('forgetUsernameUsingMobileNumber', [UsersController::class, 'forgetUsernameUsingMobileNumber'])->middleware('checkHeader');

        // forget_password
        Route::post('send_otp_for_registered_user', [UsersController::class, 'send_otp_for_registered_user'])->middleware('checkHeader');
        Route::post('forgetpasswordUsingEmail', [UsersController::class, 'forgetpasswordUsingEmail'])->middleware('checkHeader');
        Route::post('forgetpasswordUsingMobileNumber', [UsersController::class, 'forgetpasswordUsingMobileNumber'])->middleware('checkHeader');

        // Wallet
        Route::post('addMoneyToUserWallet', [UsersController::class, 'addMoneyToUserWallet'])->middleware('checkHeader');
        Route::post('fetchWalletStatement', [UsersController::class, 'fetchWalletStatement'])->middleware('checkHeader');
        Route::post('submitUserWithdrawRequest', [UsersController::class, 'submitUserWithdrawRequest'])->middleware('checkHeader');
        Route::post('fetchUserWithdrawRequests', [UsersController::class, 'fetchUserWithdrawRequests'])->middleware('checkHeader');

        // Isabel
        Route::get('getIsabelInfoOptions', [IsabelController::class, 'getIsabelInfoOptions']);
        Route::get('storeIsabelInfoOptions', [IsabelController::class, 'storeIsabelInfoOptions']);
        Route::get('getIsabelOptions', [IsabelController::class, 'getIsabelOptions']);
        Route::get('isabelQuestionsAnswers', [IsabelController::class, 'isabelQuestionsAnswers'])->name('isabelQuestionsAnswers');
        Route::get('isabelPredictiveText', [IsabelController::class, 'isabelPredictiveText'])->name('isabelPredictiveText');
        Route::get('getPredictiveText', [IsabelController::class, 'getPredictiveText'])->name('getPredictiveText');
        Route::get('isabelTriageReport/{id}', [IsabelController::class, 'isabelTriageReport'])->name('isabelTriageReport');
        Route::get('report/{id}', [IsabelController::class, 'isabelTriageReport'])->name('isabelTriageReportArabic'); 
        Route::post('submit_answers', [IsabelController::class, 'submit_answers'])->name('isabelQuestionsAnswers');
        Route::post('ranked_differential_diagnoses', [IsabelController::class, 'ranked_differential_diagnoses'])->name('ranked_differential_diagnoses');
        Route::get('knowledge_window_urls', [IsabelController::class, 'knowledge_window_urls'])->name('knowledge_window_urls');

        // Appointments
        Route::post('fetchAcceptedPendingAppointmentsOfDoctorByDate', [AppointmentController::class, 'fetchAcceptedPendingAppointmentsOfDoctorByDate'])->middleware('checkHeader');
        Route::post('fetchCoupons', [AppointmentController::class, 'fetchCoupons'])->middleware('checkHeader');
        Route::post('markMissedAppointmentFromSheduler', [AppointmentController::class, 'markMissedAppointmentFromSheduler'])->middleware('checkHeader');
        Route::post('addAppointment', [AppointmentController::class, 'addAppointment'])->middleware('checkHeader');
        Route::post('addAppointmentDocs', [AppointmentController::class, 'addAppointmentDocs'])->middleware('checkHeader');
        Route::post('deleteAppointmentDocs', [AppointmentController::class, 'deleteAppointmentDocs'])->middleware('checkHeader');
        Route::post('rescheduleAppointment', [AppointmentController::class, 'rescheduleAppointment'])->middleware('checkHeader');
        Route::post('cancelAppointment', [AppointmentController::class, 'cancelAppointment'])->middleware('checkHeader');
        Route::post('fetchAppointmentDetails', [AppointmentController::class, 'fetchAppointmentDetails'])->middleware('checkHeader');
        Route::post('addRating', [AppointmentController::class, 'addRating'])->middleware('checkHeader');
        Route::post('fetchMyPrescriptions', [AppointmentController::class, 'fetchMyPrescriptions'])->middleware('checkHeader');
        Route::post('downloadPrescriptions', [AppointmentController::class, 'downloadPrescriptions'])->middleware('checkHeader');
        Route::post('fetchMyAppointments', [AppointmentController::class, 'fetchMyAppointments'])->middleware('checkHeader');
        Route::post('scheduleAppointmentReminders', [AppointmentController::class, 'scheduleAppointmentReminders'])->middleware('checkHeader');
        Route::post('/ccavenue/initiate', [CcavenueController::class, 'initiateAppointmentPayment'])->middleware('checkHeader');     
        Route::post('/addAppointmentWithCoupon', [CcavenueController::class, 'addAppointmentWithCoupon'])->middleware('checkHeader');     
        Route::post('date_wise_slot', [DoctorController::class, 'date_wise_slot'])->name('date_wise_slot');
        Route::get('vitalReportPdf', [AppointmentController::class, 'vitalReportPdf'])->name('vitalReportPdf');
        Route::get('aiVitalMesaReportPdf', [AppointmentController::class, 'aiVitalMesaReportPdf'])->name('aiVitalMesaReportPdf');

                Route::get('/ccavenue/appointmentSuccess', [CcavenueController::class, 'appointmentSuccess']);


        // HnH card creation
        Route::post('/ccavenue/initiate-hnh', [CcavenueController::class, 'initiatePaymentHnH']);

        // Tourist card creation
        Route::post('/ccavenue/initiate-tourist-card', [CcavenueController::class, 'initiatePaymentTouristCard']);

        // Senior card creation
        Route::post('/ccavenue/initiate-senior-card', [CcavenueController::class, 'initiatePaymentSeniorCard']);

        // // check senior status 
        Route::post('/check-senior', [UsersController::class, 'checkSenior']);
  
        // AI Vital Scan
        Route::post('/ccavenue/initiate-AI-vital-scan', [CcavenueController::class, 'initiatePaymentAIVitalScan']);
        Route::post('/ccavenue/initiate-AI-vital-scan-before', [CcavenueController::class, 'initiatePaymentAIVitalScanBefore']);
        Route::post('/ccavenue/initiate-Mesa-before-chat', [CcavenueController::class, 'initiatePaymentMesaBeforeChat']);
        Route::get('/ccavenue/successAIVitalScan', [CcavenueController::class, 'successAIVitalScan']);

        // Notification
        Route::post('fetchNotification', [UsersController::class, 'fetchNotification'])->middleware('checkHeader');
        Route::get('TEST_sendNotificationToUser', [UsersController::class, 'TEST_sendNotificationToUser']);

        // Reels
        Route::post('fetchReelsPatientApp', [ReelController::class, 'fetchReelsPatientApp'])->middleware('checkHeader');
        Route::post('addCommentOnReelPatientApp', [ReelController::class, 'addCommentOnReelPatientApp'])->middleware('checkHeader');
        Route::post('likeReelPatientApp', [ReelController::class, 'likeReelPatientApp'])->middleware('checkHeader');
        Route::post('fetchReelByIdPatient', [ReelController::class, 'fetchReelByIdPatient'])->middleware('checkHeader');
        Route::post('fetchDoctorReels', [ReelController::class, 'fetchDoctorReels'])->middleware('checkHeader');

        // Terms-conditions and privacy-policy
        Route::get('terms_conditions', [SettingsController::class, 'terms_conditions']);
        Route::get('privacy_policy', [SettingsController::class, 'privacy_policy']);
        Route::get('help_center', [SettingsController::class, 'help_center']);

        // AI Vitals
        Route::post('AIVitals', [UsersController::class, 'AIVitals'])->name('AIVitals');
        Route::post('AIVitalsLongevity', [UsersController::class, 'AIVitalsLongevity'])->name('AIVitalsLongevity');
        Route::post('longevityAIVitals', [UsersController::class, 'AIVitalsLongevity'])->name('longevityAIVitals');

        // AI Vitals using scan

        // AI Vitals Misa  
        Route::post('AIVitalsMisa', [UsersController::class, 'AIVitalsMisa'])->name('AIVitalsMisa');

        // Card Save
        Route::post('SaveCardImage', [UsersController::class, 'SaveCardImage'])->name('SaveCardImage');

        // show card
        Route::post('showCardImage', [UsersController::class, 'showCardImage'])->name('showCardImage');

        // show my cards
        Route::post('showMyCards', [UsersController::class, 'showMyCards'])->name('showMyCards');

        // traveler flow
        Route::get('getServiceTypes', [TravelerController::class, 'getServiceTypes']);
        Route::post('initiateTravelerPayment', [TravelerController::class, 'initiateTravelerPayment']);
        Route::post('traveler-payment-response', [TravelerController::class, 'paymentResponse']);
        Route::get('paymentSuccess', [TravelerController::class, 'paymentSuccess']);
        Route::post('traveler-payment-cancel', [CcavenueController::class, 'ccavenue_payment_cancel']);
        
        // resend patient consult notification to doctor
        // Route::get('/send-patient-request/{appointmentId}', [ConsultController::class, 'sendPatientRequest']);
    });

    // CCAvenue payment response
    Route::post('/payment-response', [CcavenueController::class, 'ccavenue_payment_response']);
    Route::post('/payment-cancel', [CcavenueController::class, 'ccavenue_payment_cancel']);
    Route::post('/payment/ccavenue/webhook', [CcavenueController::class, 'ccavenue_payment_webhook']);


    // jitsilink

    Route::get('get-jitsi-meeting', [AppointmentController::class, 'getJitsiMeeting'])->name('getJitsiMeeting');
    // Webhook for Jitsi meeting end
    // Route::post('/jitsi/webhook', [AppointmentController::class, 'handleJitsiWebhook']);
    Route::post('jitsi-complete-meeting', [AppointmentController::class, 'jitsiCompleteMeeting'])->name('jitsiCompleteMeeting');
    Route::get('join_jitsi_meeting', [CcavenueController::class, 'join_jitsi_meeting'])->name('join_jitsi_meeting');
    Route::get('join_meeting_mail', [CcavenueController::class, 'join_jitsi_meeting_mail'])->name('join_jitsi_meeting_mail');


    //******************/ Doctor
    Route::post('doctorRegistration', [DoctorController::class, 'doctorRegistration'])->middleware('checkHeader');
    Route::post('updateDoctorDetails', [DoctorController::class, 'updateDoctorDetails'])->middleware('checkHeader');
    Route::post('doctorLogin', [DoctorController::class, 'doctorLogin'])->middleware('checkHeader');
    Route::post('deleteDoctorAccount', [DoctorController::class, 'deleteDoctorAccount'])->middleware('checkHeader');
    Route::post('logOutDoctor', [DoctorController::class, 'logOutDoctor'])->middleware('checkHeader');
    Route::post('fetchDoctorCategories', [DoctorController::class, 'fetchDoctorCategories'])->middleware('checkHeader');
    Route::post('fetchDoctorReviews', [DoctorController::class, 'fetchDoctorReviews'])->middleware('checkHeader');
    Route::post('suggestDoctorCategory', [DoctorController::class, 'suggestDoctorCategory'])->middleware('checkHeader');
    Route::post('fetchDoctorNotifications', [DoctorController::class, 'fetchDoctorNotifications'])->middleware('checkHeader');
    Route::post('fetchMyDoctorProfile', [DoctorController::class, 'fetchMyDoctorProfile'])->middleware('checkHeader');
    Route::post('addEditService', [DoctorController::class, 'addEditService'])->middleware('checkHeader');
    Route::post('addEditAwards', [DoctorController::class, 'addEditAwards'])->middleware('checkHeader');
    Route::post('addEditExpertise', [DoctorController::class, 'addEditExpertise'])->middleware('checkHeader');
    Route::post('addEditExperience', [DoctorController::class, 'addEditExperience'])->middleware('checkHeader');
    Route::post('addEditServiceLocations', [DoctorController::class, 'addEditServiceLocations'])->middleware('checkHeader');
    Route::post('addAppointmentSlots', [DoctorController::class, 'addAppointmentSlots'])->middleware('checkHeader');
    Route::post('manageDrBankAccount', [DoctorController::class, 'manageDrBankAccount'])->middleware('checkHeader');
    Route::post('deleteAppointmentSlot', [DoctorController::class, 'deleteAppointmentSlot'])->middleware('checkHeader');
    Route::post('changeSmoStatus', [DoctorController::class, 'changeSmoStatus']);
    Route::post('changeMulkmedStatus', [DoctorController::class, 'changeMulkmedStatus']);

    //update fcm token
      Route::post('update-doctor-fcm', [DoctorController::class, 'updateDoctorFcm'])->middleware('checkHeader');
    // 
     Route::post('changeTravelVisibleStatus', [DoctorController::class, 'changeTravelVisibleStatus']);
    // 

    Route::post('addHoliday', [DoctorController::class, 'addHoliday'])->middleware('checkHeader');
    Route::post('deleteHoliday', [DoctorController::class, 'deleteHoliday'])->middleware('checkHeader');
    Route::post('fetchFaqCats', [DoctorController::class, 'fetchFaqCats'])->middleware('checkHeader');
    Route::post('fetchUserDetails', [DoctorController::class, 'fetchUserDetails'])->middleware('checkHeader');
    Route::post('checkMobileNumberExists', [DoctorController::class, 'checkMobileNumberExists'])->middleware('checkHeader');
    Route::post('changeOnlineStatus', [DoctorController::class, 'changeOnlineStatus'])->middleware('checkHeader');
    Route::post('addAppointmentEmrs', [DoctorController::class, 'addAppointmentEmrs'])->middleware('checkHeader'); 
    Route::post('deleteAppointmentEmrs', [AppointmentController::class, 'deleteAppointmentEmrs'])->middleware('checkHeader');
    Route::post('smoDoctorsWithFilter', [DoctorController::class, 'smoDoctorsWithFilter'])->middleware('checkHeader');


    // Appointments
    Route::post('fetchAppointmentRequests', [AppointmentController::class, 'fetchAppointmentRequests'])->middleware('checkHeader');
    Route::post('fetchAppointmentDetails', [AppointmentController::class, 'fetchAppointmentDetails'])->middleware('checkHeader');
    Route::post('fetchAcceptedAppointsByDate', [AppointmentController::class, 'fetchAcceptedAppointsByDate'])->middleware('checkHeader');
    Route::post('acceptAppointment', [AppointmentController::class, 'acceptAppointment'])->middleware('checkHeader');
    Route::post('declineAppointment', [AppointmentController::class, 'declineAppointment'])->middleware('checkHeader');
    Route::post('addPrescription', [AppointmentController::class, 'addPrescription'])->middleware('checkHeader');
    Route::post('editPrescription', [AppointmentController::class, 'editPrescription'])->middleware('checkHeader');
    Route::post('completeAppointment', [AppointmentController::class, 'completeAppointment'])->middleware('checkHeader');
    Route::post('fetchAppointmentHistory', [AppointmentController::class, 'fetchAppointmentHistory'])->middleware('checkHeader');

    // Wallet
    Route::post('fetchDoctorWalletStatement', [AppointmentController::class, 'fetchDoctorWalletStatement'])->middleware('checkHeader');
    Route::post('fetchDoctorEarningHistory', [AppointmentController::class, 'fetchDoctorEarningHistory'])->middleware('checkHeader');
    Route::post('submitDoctorWithdrawRequest', [AppointmentController::class, 'submitDoctorWithdrawRequest'])->middleware('checkHeader');
    Route::post('fetchDoctorPayoutHistory', [AppointmentController::class, 'fetchDoctorPayoutHistory'])->middleware('checkHeader');

    // Settings
    Route::post('fetchGlobalSettings', [SettingsController::class, 'fetchGlobalSettings'])->middleware('checkHeader');
    Route::post('pushNotificationToSingleUser', [SettingsController::class, 'pushNotificationToSingleUser'])->middleware('checkHeader');
    Route::post('uploadFileGivePath', [SettingsController::class, 'uploadFileGivePath'])->middleware('checkHeader');
    Route::post('generateAgoraToken', [SettingsController::class, 'generateAgoraToken'])->middleware('checkHeader');

    // Reels
    Route::post('uploadReelByDoctor', [ReelController::class, 'uploadReelByDoctor'])->middleware('checkHeader');
    Route::post('fetchReelsDoctorApp', [ReelController::class, 'fetchReelsDoctorApp'])->middleware('checkHeader');
    Route::post('addCommentOnReelDoctorApp', [ReelController::class, 'addCommentOnReelDoctorApp'])->middleware('checkHeader');
    Route::post('likeReelDoctorApp', [ReelController::class, 'likeReelDoctorApp'])->middleware('checkHeader');
    Route::post('fetchReelByIdDoctor', [ReelController::class, 'fetchReelByIdDoctor'])->middleware('checkHeader');
    Route::post('deleteReel', [ReelController::class, 'deleteReel'])->middleware('checkHeader');
    Route::post('fetchMyReels_DoctorApp', [ReelController::class, 'fetchMyReels_DoctorApp'])->middleware('checkHeader');


    Route::post('reportReel', [ReelController::class, 'reportReel'])->middleware('checkHeader');
    Route::post('fetchSavedReels', [ReelController::class, 'fetchSavedReels'])->middleware('checkHeader');
    Route::post('fetchReelComments', [ReelController::class, 'fetchReelComments'])->middleware('checkHeader');
    Route::post('increaseReelViewCount', [ReelController::class, 'increaseReelViewCount'])->middleware('checkHeader');

    // Cron
    Route::get('sendScheduledReminders_Cron', [SettingsController::class, 'sendScheduledReminders_Cron']);

    Route::prefix('smo')->name('.smo')->group(function () {
        Route::get('dashboard', [SMOController::class, 'dashboard']);
        Route::get('getHospitalsAndDoctors', [SMOController::class, 'getHospitalsAndDoctors'])->name('getHospitalsAndDoctors');
        Route::get('getHospitalDatails/{id}', [SMOController::class, 'getHospitalDatails'])->name('getHospitalDatails');
        Route::get('getCategoryDatails/{id}', [SMOController::class, 'getCategoryDatails'])->name('getCategoryDatails');
        Route::get('getProcedures', [SMOController::class, 'getProcedures'])->name('getProcedures');
        Route::get('getSpeciality', [SMOController::class, 'getSpeciality'])->name('getSpeciality');
        Route::get('getCountries', [SMOController::class, 'getCountries'])->name('getCountries');
        Route::get('getHospitals', [SMOController::class, 'getHospitals'])->name('getHospitals');
        Route::get('getQueryProcedures', [SMOController::class, 'getQueryProcedures'])->name('getQueryProcedures');
        Route::post('submitSMOQuery', [SMOController::class, 'submitSMOQuery'])->name('submitSMOQuery');
        Route::post('submitSMOEnquiry', [SMOController::class, 'submitSMOEnquiry'])->name('submitSMOEnquiry');
        Route::post('submitDocuments', [SMOController::class, 'submitDocuments'])->name('submitDocuments');
        Route::get('autoTranslateHospitals', [SMOController::class, 'autoTranslateHospitals']);
    });
 
    // bidding
    Route::prefix('bidding')->name('.bidding')->group(function () {
    Route::get('getServices', [BiddingController::class, 'getServices'])->name('getServices');
    Route::post('submit-bid', [BiddingController::class, 'submitBid'])->name('submitBid');
    });    

    // Lowest Price Finder
    Route::prefix('lowestPriceFinder')->name('.lowestPriceFinder')->group(function () {
        Route::get('getProceduresAndPrice', [LowestPriceFinderController::class, 'getProceduresAndPrice'])->name('getProceduresAndPrice');
        Route::post('/', [LowestPriceFinderController::class, 'lowestPriceFinder'])->name('lowestPriceFinder');
        Route::get('/cities', [LowestPriceFinderController::class, 'cities']);
    });  

    // Major Organ Tests
    Route::prefix('majorOrganTests')->name('.majorOrganTests')->group(function () {
        Route::get('list', [MajorOrganTestController::class, 'list'])->middleware('checkHeader')->name('list');
        Route::get('planDetails', [MajorOrganTestController::class, 'planDetails'])->middleware('checkHeader')->name('planDetails');
        Route::post('analyzeReport', [MajorOrganTestController::class, 'analyzeReport'])->middleware('checkHeader')->name('analyzeReport');
        Route::post('saveSelection', [MajorOrganTestController::class, 'saveSelection'])->middleware('checkHeader')->name('saveSelection');
        Route::get('getSelection', [MajorOrganTestController::class, 'getSelection'])->middleware('checkHeader')->name('getSelection');
 
  
    });

    // best offers
    Route::prefix('bestOffers')->name('.bestOffers')->group(function () {
        Route::get('details/{id}', [BestOffersController::class, 'details'])->name('details');    
        Route::post('addToCart', [BestOffersController::class, 'addToCart'])->name('addToCart');
        Route::post('removeFromCart', [BestOffersController::class, 'removeFromCart'])->name('removeFromCart');
        Route::post('getCartData', [BestOffersController::class, 'getCartData'])->name('getCartData');
        Route::post('/initiate', [CcavenueController::class, 'initiatePaymentBestOffers'])->middleware('checkHeader');  
        Route::post('/myPackages', [BestOffersController::class, 'myPackages'])->middleware('checkHeader');  
        Route::post('/myPackageDetails', [BestOffersController::class, 'myPackageDetails'])->middleware('checkHeader');  
    });

    // Tourist Banner
    Route::prefix('tourist')->group(function () {
        Route::get('banner', [TouristController::class, 'getTouristBanners']);
        Route::post('touristLogin', [TouristController::class, 'touristLogin']);
        Route::get('homePage', [TouristController::class, 'homePage']);
        Route::post('fetchDoctorDetails', [TouristController::class, 'fetchDoctorDetails']);
        Route::post('addAppointment', [TouristController::class, 'addAppointment']);
        Route::get('join_tourist_jitsi_meeting', [TouristController::class, 'join_tourist_jitsi_meeting']);
        Route::get('join_tourist_jitsi_meeting_v2', [TouristController::class, 'join_tourist_jitsi_meeting_v2']);

        Route::get('join_tourist_meeting_mail', [TouristController::class, 'join_tourist_meeting_mail']);
        Route::post('fetchMyAppointments', [TouristController::class, 'fetchMyAppointments']);
        Route::post('AIVitals', [TouristController::class, 'AIVitals'])->name('AIVitals');
        Route::post('AIVitalScan', [TouristController::class, 'initiatePaymentAIVitalScan']);
        Route::get('touristAiVitalMesaReportPdf', [TouristController::class, 'touristAiVitalMesaReportPdf'])->name('touristAiVitalMesaReportPdf');
        Route::get('vitalReportPdf', [TouristController::class, 'vitalReportPdf'])->name('vitalReportPdf');
        Route::post('fetchAcceptedAppointsByDate', [TouristController::class, 'fetchAcceptedAppointsByDate']);
        Route::post('fetchAppointmentDetails', [TouristController::class, 'fetchAppointmentDetails']);
        Route::post('addAppointmentDocs', [TouristController::class, 'addAppointmentDocs']);
        Route::post('deleteAppointmentDocs', [TouristController::class, 'deleteAppointmentDocs']);
        Route::post('completeAppointment', [TouristController::class, 'completeAppointment']);
        Route::post('addPrescription', [TouristController::class, 'addPrescription']);
        Route::post('editPrescription', [TouristController::class, 'editPrescription']);
        Route::post('downloadPrescriptions', [TouristController::class, 'downloadPrescriptions']);
        Route::post('addAppointmentEmrs', [TouristController::class, 'addAppointmentEmrs']); 
        Route::post('mesaBeforeChat', [TouristController::class, 'mesaBeforeChat']);
        Route::get('getIsabelInfoOptions', [TouristController::class, 'getIsabelInfoOptions']);
        Route::get('isabelQuestionsAnswers', [TouristController::class, 'isabelQuestionsAnswers']);
        Route::get('getPredictiveText', [TouristController::class, 'getPredictiveText']);
        Route::post('ranked_differential_diagnoses', [TouristController::class, 'ranked_differential_diagnoses']);
        Route::get('knowledge_window_urls', [TouristController::class, 'knowledge_window_urls']);
        Route::post('submit_answers', [TouristController::class, 'submit_answers']);
        Route::get('isabelTriageReport/{id}', [TouristController::class, 'isabelTriageReport'])->name('isabelTouristTriageReport');
        Route::get('report/{id}', [TouristController::class, 'isabelTriageReport'])->name('isabelTouristTriageReportArabic'); 
        Route::post('submitDoctorReviews', [TouristController::class, 'submitDoctorReviews']);
        Route::post('fetchAppointmentHistory', [TouristController::class, 'fetchAppointmentHistory']);
        Route::post('rescheduleAppointment', [TouristController::class, 'rescheduleAppointment']);
        Route::post('markMissedAppointmentFromSheduler', [TouristController::class, 'markMissedAppointmentFromSheduler']);
        Route::post('update-tourist-fcm', [TouristController::class, 'updateTouristFCM']);
    
        // send requst to doctor
        Route::get('/send-request/{id}', [ConsultController::class, 'sendRequest']);
        Route::post('/cancel-request/{consultId}', [ConsultController::class, 'cancelRequestByConsultId']);
        // doctor accept request
         Route::post('/accept-request', [ConsultController::class, 'accept']);
        Route::get('/joined-link/{id}', [ConsultController::class, 'customerJoined']);

        Route::get('/check-doctor-accepted/{id}', [ConsultController::class, 'checkDoctorAccepted']);

    });
     
    // Route::prefix('emr')->middleware('mergeRawJson')->group(function () {
    //     Route::post('fetch', [TouristDoctorEmrController::class, 'fetch']);
    //     Route::post('save-draft', [TouristDoctorEmrController::class, 'saveDraft']);
    //     Route::post('save-final', [TouristDoctorEmrController::class, 'saveFinal']);
    //     Route::post('dhpo-prescription/add-item', [TouristDoctorEmrController::class, 'addDhpoPrescriptionItem']);
    //     Route::post('dhpo-prescription/edit-item', [TouristDoctorEmrController::class, 'editDhpoPrescriptionItem']);
    //     Route::post('dhpo-prescription/delete-item', [TouristDoctorEmrController::class, 'deleteDhpoPrescriptionItem']);

    //     Route::get('dropdown/symptoms', [TouristDoctorEmrController::class, 'getSymptomDropdown']);
    //     Route::get('dropdown/chief-complaints', [TouristDoctorEmrController::class, 'getChiefComplaintDropdown']);
    //     Route::get('dropdown/allergies', [TouristDoctorEmrController::class, 'getAllergyDropdown']);
    //     Route::get('dropdown/diagnosis-types', [TouristDoctorEmrController::class, 'getDiagnosisTypeDropdown']);
    //     Route::get('dropdown/diagnosis', [TouristDoctorEmrController::class, 'getDiagnosisDropdown']);
    //     Route::get('dropdown/lab-orders', [TouristDoctorEmrController::class, 'getLabOrderDropdown']);
    //     Route::get('dropdown/radiology-orders', [TouristDoctorEmrController::class, 'getRadiologyOrderDropdown']);
    // });

     Route::prefix('emr')->middleware('mergeRawJson')->group(function () {
        Route::get('table-data', [PatientEmrReportController::class, 'getEmrTableData']);
        Route::get('view-data', [PatientEmrReportController::class, 'getEmrViewData']);
        Route::get('edit-data', [PatientEmrReportController::class, 'getEmrEditData']);
        Route::post('edit-data', [PatientEmrReportController::class, 'updateEmrEditData']);
        Route::post('fetch', [PatientEmrReportController::class, 'fetch']);
        Route::get('save-draft', [PatientEmrReportController::class, 'saveDraftGet']);
        Route::get('save-draftdetail', [PatientEmrReportController::class, 'getSaveDraftDetail']);
        Route::post('save-draft', [PatientEmrReportController::class, 'saveDraft']);
        Route::post('save-final', [PatientEmrReportController::class, 'saveFinal']);
        Route::post('dhpo-prescription/add-item', [PatientEmrReportController::class, 'addDhpoPrescriptionItem']);
        Route::post('dhpo-prescription/edit-item', [PatientEmrReportController::class, 'editDhpoPrescriptionItem']);
        Route::post('dhpo-prescription/delete-item', [PatientEmrReportController::class, 'deleteDhpoPrescriptionItem']);

        Route::get('dropdown/symptoms', [PatientEmrReportController::class, 'getSymptomDropdown']);
        Route::get('dropdown/chief-complaints', [PatientEmrReportController::class, 'getChiefComplaintDropdown']);
        Route::get('dropdown/allergies', [PatientEmrReportController::class, 'getAllergyDropdown']);
        Route::get('dropdown/drug-names', [PatientEmrReportController::class, 'getDrugNameDropdown']);
        Route::get('dropdown/diagnosis-types', [PatientEmrReportController::class, 'getDiagnosisTypeDropdown']);
        Route::get('dropdown/diagnosis', [PatientEmrReportController::class, 'getDiagnosisDropdown']);
        Route::get('dropdown/lab-orders', [PatientEmrReportController::class, 'getLabOrderDropdown']);
        Route::get('dropdown/radiology-orders', [PatientEmrReportController::class, 'getRadiologyOrderDropdown']);
         // EMR PDF Download
        Route::get('download-pdf', [PatientEmrReportController::class, 'downloadEmrReport'])->name('emr.download-pdf');
        Route::get('download-prescription-pdf', [PatientEmrReportController::class, 'downloadPrescriptionPdf'])->name('emr.download-prescription-pdf');
    });

    Route::prefix('doctor')->group(function () {

        // send requst to doctor
        Route::get('/rejoin-Consult/{id}', [ConsultController::class, 'rejoinConsult']);

        // doctorcall back to patient
        // Route::get('/rejoin-patient-consult/{id}', [ConsultController::class, 'rejoinPatientConsult']);
        Route::post('appointment-completed/{appointmentId}', [ConsultController::class, 'appointmentCompleted']);
          Route::get('/check-patient-accepted/{id}', [ConsultController::class, 'checkPatientAccepted']);
    });
    
    //  download certificate
    Route::get('/download-certificate', [TravelerController::class, 'downloadCertificate']);

    //  // New Shenai Care routes
    // Route::prefix('newshenai-care')->group(function () {
    //     Route::post('login', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'login']);
    //     Route::post('scan', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'scan']);
    //     Route::post('store', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'scan']);
    //     Route::post('trigger-classification', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'triggerClassification']);
    //     Route::get('latest-longevity-report', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'getLatestLongevityReport']);
    //     Route::post('latest-longevity-report', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'getLatestLongevityReport']);
    //     Route::get('latestLongevityReport', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'getLatestLongevityReport']);
    //     Route::post('latestLongevityReport', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'getLatestLongevityReport']);
    //     Route::get('vitals/{id?}', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'getVital']);
    //     Route::get('longevityReportPdf', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'longevityReportPdf'])->name('newshenai.longevityReportPdf');
    //     Route::get('downloadLatestLongevityReportPdf', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'downloadLatestLongevityReportPdf']);
    //     Route::post('uploadLabReport', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'uploadLabReport']);
    //     Route::get('reviewAndBuy', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'reviewAndBuy']);
    //     });

     // New Shenai Care routes
    Route::prefix('newshenai-care')->group(function () {
        Route::post('login', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'login']);
        Route::post('scan', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'scan']);
        Route::post('trigger-classification', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'triggerClassification']);
        Route::get('latestLongevityReport', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'getLatestLongevityReport']);
        Route::get('vitals/{id?}', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'getVital']);
        Route::get('longevityReportPdf', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'longevityReportPdf'])->name('newshenai.longevityReportPdf');
        Route::get('downloadLatestLongevityReportPdf', [\App\Http\Controllers\v1\NewShenaiCareController::class, 'downloadLatestLongevityReportPdf']);
    });
         