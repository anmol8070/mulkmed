<?php

namespace App\Models;

use Illuminate\Validation\Rules\Enum;

final class Constants
{
    // Doctor Sorting
    const sortTypePriceLow = 1;
    const sortTypePriceHigh = 2;
    const sortTypeRating = 3;
    // Gender
    const genderMale = 1;
    const genderFemale = 0;

    // doctor Status
    const statusDoctorPending = 0;
    const statusDoctorApproved = 1;
    const statusDoctorBanned = 2;

    // Doctor profile complete status
    const profileCompleted = 1;

    const profileNotCompleted = 0;

    // Doctor vacation
    const doctorNotOnVacation = 0;
    const doctorOnVacation = 1;

    // Tax Type
    const taxPercent = 0;
    const taxFixed = 1;

    // Device Type
    const deviceAndroid = 1;
    const deviceIOS = 2;

    // Payment Gateways
    const addedByAdmin = 2;
    const stripe = 1;
    const razorPay = 3;
    const payStack = 4;
    const payPal = 5;
    const flutterWave = 6;
    const sslCommerze = 7;

    // Credit/Debit
    const credit = 1;
    const debit = 0;

    //User Statement Entries
    const deposit = 0;
    const purchase = 1;
    const withdraw = 2;
    const refund = 3;

    // Notification Type
    const notifyAppointment = 0;
    const notifyReel = 1;

    // Prefixes
    const prefixDoctorNumber = "DR";
    const prefixPlatformEarningHistory = "PLEAR";
    const prefixDoctorEarningHistory = "DREAR";
    const prefixUserWithDrawRequestNumber = "URWTH";
    const prefixDoctorWithDrawRequestNumber = "DRWTH";
    const prefixAppointmentNumber = "APT";
    const prefixDoctorTransactionId = "DRTRID";
    const prefixUserTransactionId = "URTRID";

    // Appointment status
    const orderPlacedPending = 0;
    const orderAccepted = 1;
    const orderCompleted = 2;
    const orderDeclined = 3;
    const orderCancelled = 4;
    const orderMissed = 5;

    // Payment status 
    const pendingPaymentStatus = 0;
    const successPaymentStatus = 1;
    const failurePaymentStatus = 2;
    const abortedPaymentStatus = 3;


    // Doctor Statement Entries
    const doctorWalletEarning = 0;
    const doctorWalletCommission = 1;
    const doctorWalletWithdraw = 2;
    const doctorWalletOrderRefund = 3;
    const doctorWalletPayoutReject = 4;


    // Withdrawals Status
    const statusWithdrawalPending = 0;
    const statusWithdrawalCompleted = 1;
    const statusWithdrawalRejected = 2;

    // appointment payment status
    const appointmentPaymentPendingStatus = 0;
    const appointmentPaymentSuccessStatus = 1;
    const appointmentPaymentFailureStatus = 2;
    const appointmentPaymentAbortedStatus = 3;

    const HnHPaymentPendingStatus = 0;
    const HnHPaymentSuccessStatus = 1;
    const HnHPaymentFailureStatus = 2;
    const HnHPaymentAbortedStatus = 3;

    const AIVitalsPaymentPendingStatus = 0;
    const AIVitalsPaymentSuccessStatus = 1;
    const AIVitalsPaymentFailureStatus = 2;
    const AIVitalsPaymentAbortedStatus = 3;

    // User plan Status
    const statusUserPlanActive = 'active';
    const statusUserPlanInactive = 'inactive';

    
    // ccavenue payment type
    const SeniorCardPaymentType = "senior_card_payment";
    const TouristCardPaymentType = "tourist_card_payment";
    const HnHPaymentType = "hnh_payment";
    const appointmentPaymentType = "appointment_payment";

    const AIVitalScanPaymentType = 'ai_vital_scan_payment';

    const AIVitalScanPaymentBeforeType = 'ai_vital_scan_before_payment';

    const LongevityPaymentType = 'longevity_payment';
    

    // marchant payment type 
    const CCAvenueSeniorCardPaymentType = 'seniorcardpayment';

    const CCAvenueTouristCardPaymentType = 'touristcardpayment';
    const CCAvenueHnHPaymentType = 'hnhpayment';

    const CCAvenueAIVitalScanPaymentType = 'aivitalscanpayment';

    const CCAvenueAIVitalScanBeforePaymentType = 'aivitalscanbeforepayment';

    const CCAvenueMesaBeforeChatPayment = 'mesabeforechatpayment';

    const CCAvenueLongevityPaymentType = 'longevitypayment';

    const meetingDurationInMinutes = 60;
 
    // best offers payment type
    const CCAvenueBestOffersPaymentPaymentType = 'bestofferspayment';
}
