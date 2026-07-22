<?php

use App\Http\Controllers\v2\AppointmentController;
use App\Http\Controllers\v2\DoctorController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\v2\SettingsController;
use App\Http\Controllers\v2\UsersController;
use App\Http\Controllers\v2\CcavenueController;
use App\Http\Controllers\v2\IsabelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Controllers\v2\TouristController;
use App\Http\Controllers\v2\TravelerController;

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
        Route::post('fetchDoctorPlansAndSlots', [DoctorController::class, 'fetchDoctorPlansAndSlots'])->middleware('checkHeader');
        Route::post('logOut', [UsersController::class, 'logOut'])->middleware('checkHeader');

        // mobile number verification
        Route::post('send_otp', [UsersController::class, 'send_otp'])->middleware('checkHeader');
        Route::post('otp_verify', [UsersController::class, 'otp_verify'])->middleware('checkHeader');

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
        Route::get('isabelQuestionsAnswers', [IsabelController::class, 'isabelQuestionsAnswers'])->name('isabelQuestionsAnswers');
        Route::post('submit_answers', [IsabelController::class, 'submit_answers'])->name('isabelQuestionsAnswers');


        // Appointments
        Route::post('fetchAcceptedPendingAppointmentsOfDoctorByDate', [AppointmentController::class, 'fetchAcceptedPendingAppointmentsOfDoctorByDate'])->middleware('checkHeader');
        Route::post('fetchCoupons', [AppointmentController::class, 'fetchCoupons'])->middleware('checkHeader');
        Route::post('addAppointment', [AppointmentController::class, 'addAppointment'])->middleware('checkHeader');
        Route::post('addAppointmentDocs', [AppointmentController::class, 'addAppointmentDocs'])->middleware('checkHeader');
        Route::post('deleteAppointmentDocs', [AppointmentController::class, 'deleteAppointmentDocs'])->middleware('checkHeader');
        Route::post('rescheduleAppointment', [AppointmentController::class, 'rescheduleAppointment'])->middleware('checkHeader');
        Route::post('cancelAppointment', [AppointmentController::class, 'cancelAppointment'])->middleware('checkHeader');
        Route::post('fetchAppointmentDetails', [AppointmentController::class, 'fetchAppointmentDetails'])->middleware('checkHeader');
        Route::post('addRating', [AppointmentController::class, 'addRating'])->middleware('checkHeader');
        Route::post('fetchMyPrescriptions', [AppointmentController::class, 'fetchMyPrescriptions'])->middleware('checkHeader');
        Route::post('fetchMyAppointments', [AppointmentController::class, 'fetchMyAppointments'])->middleware('checkHeader');
        Route::post('scheduleAppointmentReminders', [AppointmentController::class, 'scheduleAppointmentReminders'])->middleware('checkHeader');
        Route::post('/ccavenue/initiate', [CcavenueController::class, 'initiateAppointmentPayment'])->middleware('checkHeader');
        Route::post('date_wise_slot', [DoctorController::class, 'date_wise_slot'])->name('date_wise_slot');

        // HnH card creation
        Route::post('/ccavenue/initiate-hnh', [CcavenueController::class, 'initiatePaymentHnH']);

        // AI Vital Scan
        Route::post('/ccavenue/initiate-AI-vital-scan', [CcavenueController::class, 'initiatePaymentAIVitalScan']);

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

        // AI Vitals
        Route::post('AIVitals', [UsersController::class, 'AIVitals'])->name('AIVitals');

        // AI Vitals using scan

        // AI Vitals Misa
        Route::post('AIVitalsMisa', [UsersController::class, 'AIVitalsMisa'])->name('AIVitalsMisa');

        
        // Order medicine
        Route::post('fetchOrderMedicinePageData', [UsersController::class, 'fetchOrderMedicinePageData'])->middleware('checkHeader');

        Route::post('AIPrescriptionRead', [UsersController::class, 'AIPrescriptionRead'])->name('AIPrescriptionRead')->middleware('checkHeader');


          Route::post('initiateTravelerPayment', [TravelerController::class, 'initiateTravelerPayment']);
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
    Route::post('addHoliday', [DoctorController::class, 'addHoliday'])->middleware('checkHeader');
    Route::post('deleteHoliday', [DoctorController::class, 'deleteHoliday'])->middleware('checkHeader');
    Route::post('fetchFaqCats', [DoctorController::class, 'fetchFaqCats'])->middleware('checkHeader');
    Route::post('fetchUserDetails', [DoctorController::class, 'fetchUserDetails'])->middleware('checkHeader');
    Route::post('checkMobileNumberExists', [DoctorController::class, 'checkMobileNumberExists'])->middleware('checkHeader');
    Route::post('changeOnlineStatus', [DoctorController::class, 'changeOnlineStatus'])->middleware('checkHeader');

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

     Route::prefix('tourist')->group(function () {

      Route::post('touristLogin', [TouristController::class, 'touristLogin']);
     });
