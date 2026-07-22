<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnforceApiTokenAuth
{
    /**
     * Relative paths (without api prefix) allowed without Bearer token.
     * Expanded to api, api/v1, api/v2, and api/v3 in isPublicRoute().
     *
     * @var array<int, string>
     */
    private array $publicRelativePaths = [
        // Auth & registration
        'user/checkAvailability',
        'user/registerUser',
        'user/loginUser',
        'user/send_otp',
        'user/otp_verify',
        'user/sendUsernameReminder',
        'user/forgetUsernameUsingMobileNumber',
        'user/send_otp_for_registered_user',
        'user/forgetpasswordUsingEmail',
        'user/forgetpasswordUsingMobileNumber',
        'doctorRegistration',
        'doctorLogin',
        'checkMobileNumberExists',
        'tourist/touristLogin',
        // Static / bootstrap (read-only)
        'getBaseUrl',
        'user/terms_conditions',
        'user/privacy_policy',
        'user/help_center',
        // Payments & gateways (server / browser callbacks)
        'payment-response',
        'payment-cancel',
        'payment/ccavenue/webhook',
        'user/ccavenue/appointmentSuccess',
        'user/ccavenue/successAIVitalScan',
        'user/traveler-payment-response',
        'user/paymentSuccess',
        'user/traveler-payment-cancel',
        'bestOffers/paymentSuccess',
        // Cron
        'sendScheduledReminders_Cron',
        // Jitsi & meeting links (email / browser)
        'get-jitsi-meeting',
        'jitsi-complete-meeting',
        'join_jitsi_meeting',
        'join_meeting_mail',
        'tourist/join_tourist_jitsi_meeting',
        'tourist/join_tourist_jitsi_meeting_v2',
        'tourist/join_tourist_meeting_mail',
        // Public file streaming
        'storage/*',
        // Email / share links (reports without app session)
        'user/vitalReportPdf',
        'user/aiVitalMesaReportPdf',
        'user/isabelTriageReport/*',
        'user/report/*',
        'tourist/vitalReportPdf',
        'tourist/touristAiVitalMesaReportPdf',
        'tourist/isabelTriageReport/*',
        'tourist/report/*',
        'download-certificate',
        // AI Vitals (Background/Server tasks)
        'user/AIVitals',
        'user/AIVitalsLongevity',
        'user/longevityAIVitals',
        'user/AIVitalsMisa',
        // Shenai Care
        'newshenai-care/downloadLatestLongevityReportPdf',
        'newshenai-care/uploadLabReport',
        'newshenai-care/reviewAndBuy',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->noContent();
        }

        if ($this->isPublicRoute($request)) {
            return $next($request);
        }

        $plainTextToken = $request->bearerToken();
        if (!$plainTextToken) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Unauthorized. Bearer token is required.',
            ], 401);
        }

        $token = PersonalAccessToken::findToken($plainTextToken);
        if (!$token || !$token->tokenable) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Unauthorized. Invalid token.',
            ], 401);
        }

        $expirationMinutes = config('sanctum.expiration');
        if ($expirationMinutes !== null) {
            $expiresAt = Carbon::parse($token->created_at)->addMinutes((int) $expirationMinutes);
            if ($expiresAt->isPast()) {
                $token->delete();

                return new JsonResponse([
                    'status' => false,
                    'message' => 'Unauthorized. Token expired.',
                ], 401);
            }
        }

        $tokenable = $token->tokenable;
        if (method_exists($tokenable, 'withAccessToken')) {
            $tokenable = $tokenable->withAccessToken($token);
        }
        $request->setUserResolver(static fn () => $tokenable);
        $request->attributes->set('auth_user', $tokenable);
        $request->attributes->set('actor_type', class_basename($token->tokenable_type));
        $request->attributes->set('actor_id', $token->tokenable_id);

        $ownershipError = $this->validateActorOwnership($request);
        if ($ownershipError !== null) {
            return $ownershipError;
        }

        return $next($request);
    }

    private function isPublicRoute(Request $request): bool
    {
        $patterns = [];
        foreach (['api', 'api/v1', 'api/v2', 'api/v3'] as $prefix) {
            foreach ($this->publicRelativePaths as $path) {
                $patterns[] = $prefix . '/' . $path;
            }
        }

        return $request->is($patterns);
    }

    private function validateActorOwnership(Request $request): ?JsonResponse
    {
        $actorType = (string) $request->attributes->get('actor_type', '');
        $actorId = (int) $request->attributes->get('actor_id', 0);
        if ($actorId <= 0) {
            return null;
        }

        $requestedUserId = $request->input('user_id');
        if ($requestedUserId !== null && $actorType === 'User' && (int) $requestedUserId !== $actorId) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Forbidden. You can only access your own user resources.',
            ], 403);
        }

        $requestedDoctorId = $request->input('doctor_id');
        if ($requestedDoctorId !== null && in_array($actorType, ['Doctor', 'Doctors'], true) && (int) $requestedDoctorId !== $actorId) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Forbidden. You can only access your own doctor resources.',
            ], 403);
        }

        $requestedTouristId = $request->input('tourist_id');
        if ($requestedTouristId !== null && $actorType === 'TouristList' && (int) $requestedTouristId !== $actorId) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Forbidden. You can only access your own tourist resources.',
            ], 403);
        }

        return null;
    }
}
