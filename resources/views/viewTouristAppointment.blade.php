@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/viewAppointment.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('asset/style/viewAppointment.css') }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"
        integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endsection

@php
    use App\Models\Constants as Constants;
    use App\Models\GlobalFunction as GlobalFunction;
@endphp

<style>
    .medicine-item {
        background-color: whitesmoke;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 20px;
        border-bottom: 1px solid #c2c2c2;
    }

    .invoice-item {
        background-color: whitesmoke;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 20px;
        border-bottom: 1px solid #c2c2c2;
    }

    .medicine-item:last-child {
        border-bottom: none;
    }

    .pres-medi-note {
        font-size: 12px;
    }

    .invoice-item:last-child {
        border-bottom: none;
        background-color: #363636;
        color: white;
    }

    .invoice-item p,
    .medicine-item p {
        margin: 0;
    }

    .coupon-text {
        padding: 0px 5px;
        border-radius: 5px;
    }
</style>

@section('content')
    <input type="hidden" value="{{ $appointment->id }}" id="appointmentId">
    <input type="hidden" value="{{ $appointment->appointment_number }}" id="appointmentNumber">

    <div class="row">

        <div class="card col-12">
            <div class="card-header">
                <h4 class="d-inline">
                    {{ $appointment->appointment_number }}
                </h4>

                {{--  Status --}}
                @if ($appointment->status == Constants::orderPlacedPending)
                    <span class="badge bg-warning text-white ">{{ __('Waiting For Confirmation') }} </span>
                @elseif($appointment->status == Constants::orderAccepted)
                    <span class="badge bg-info text-white ">{{ __('Accepted') }} </span>
                @elseif($appointment->status == Constants::orderCompleted)
                    <span class="badge bg-success text-white ">{{ __('Completed') }} </span>
                @elseif($appointment->status == Constants::orderDeclined)
                    <span class="badge bg-danger text-white ">{{ __('Declined') }} </span>
                @elseif($appointment->status == Constants::orderCancelled)
                    <span class="badge bg-danger text-white ">{{ __('Cancelled') }} </span>
                @elseif($appointment->status == Constants::orderMissed)
                    <span class="badge bg-danger text-white ">{{ __('Missed') }} </span>
                @endif


            </div>
            <div class="card-body">
                <div class="form-row">
                    {{-- Doctor --}}
                    <div class="col-md-4">
                        <label class="mb-1 text-grey d-block " for="">{{ __('Doctor') }}</label>
                        <div class="d-flex align-items-center card-profile">
                            <img class="rounded-circle owner-img-border mr-2" width="80" height="80"
                                src="{{ env('FILES_BASE_URL') }}{{ $appointment->doctor->image }}" alt="">
                            <div>
                                <p class="mt-0 mb-0 p-data">{{ $appointment->doctor->name }}</p>
                                <p class="mt-0 mb-0">{{ $appointment->doctor->designation }}</p>
                                <span class="mt-0 mb-0">{{ $appointment->doctor->degrees }}</span>
                            </div>
                        </div>
                    </div>
                    {{-- Tourist --}}
                    <div class="col-md-4">
                        <label class="mb-1 text-grey d-block" for="">{{ __('Tourist') }}</label>
                        <div class="d-flex align-items-center card-profile">
                            <div>
                                <p class="mt-0 mb-0 p-data">{{ $appointment->tourist->first_name }}  {{ $appointment->tourist->last_name }}</p>
                                
                            </div>
                        </div>


                    </div>

                    {{-- Patient --}}
                    <div class="col-md-4">
                        <label class="mb-1 text-grey d-block" for="">{{ __('Patient') }}</label>
                        <div class="card-profile align-items-center">
                            <div style="height: 80px ">
                                @if ($appointment->patient == null)
                                    <p class="mt-0 mb-0 p-data">{{ __('Self') }}</p>
                                @else
                                    <p class="mt-0 mb-0 p-data">{{ $appointment->patient->fullname }}</p>
                                    <span
                                        class="mt-0 mb-0">{{ $appointment->patient->gender == 1 ? __('Male') : __('Female') }}
                                        :
                                        {{ $appointment->patient->age }}{{ __(' Years') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row flex-column flex-xl-row mt-2">

        <div class="card col mr-2">
            <div class="card-header">
                <h4 class="d-inline">
                    {{ __('Details') }}
                </h4>
            </div>
            <div class="card-body">
                {{-- Appointment Time/Date/Type --}}
                <div>
                    <h6 class="mb-1 text-dark d-block" for="">{{ __('Date/Time/Type') }}</h6>
                    <div class="card-profile align-items-center">
                        <div>
                            <span class="mt-0 mb-0">{{ __('Date') }}: {{ $appointment->date }}</span><br>
                            <span class="mt-0 mb-0">{{ __('Time') }}:
                                {{ GlobalFunction::formateTimeString($appointment->time) }}</span><br>
                            @if ($appointment->type == 0)
                                <span class="mt-0 mb-0">{{ __('Type') }}: {{ __('Online') }}</span>
                            @else
                                <span class="mt-0 mb-0">{{ __('Type') }}: {{ __('Offline') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                {{-- Problem --}}
                <div class="mt-3">
                    <h6 class="mb-1 text-dark d-block" for="">{{ __('Problem') }}</h6>
                    <div class="card-profile align-items-center">
                        <div>
                            <span class="mt-0 mb-0">{{ $appointment->problem }}</span><br>
                        </div>
                    </div>
                </div>
                {{-- Attachments --}}
                <div class="mt-3">
                    <h6 class="mb-1 text-dark d-block" for="">{{ __('Attachments') }}</h6>
                    <div class="card-profile align-items-center">
                        <div>
                            @if ($appointment->documents->count() > 0)
                                @foreach ($appointment->documents as $document)
                                    <img class="rounded shadow border-grey mr-2 appointment-doc" width="80"
                                        height="80" src="{{ env('FILES_BASE_URL') }}{{ $document->image }}"
                                        alt="">
                                @endforeach
                            @else
                                <span class="text-grey p-1">{{ __('No Attachments') }}</span>
                            @endif
                        </div>
                    </div>

                </div>
                {{-- Diagnosed with --}}
                <div class="mt-3">
                    <h6 class="mb-1 text-dark d-block" for="">{{ __('Diagnosed With') }}</h6>
                    <div class="card-profile align-items-center">
                        <div>
                            <span class="mt-0 mb-0">{{ $appointment->diagnosed_with }}</span><br>
                        </div>
                    </div>
                </div>
                {{-- Feedback --}}
                <div class="mt-3">
                    <h6 class="mb-1 text-dark d-block" for="">{{ __('Feedback') }}</h6>
                    <div class="card-profile align-items-center">
                        <div>
                            @if ($appointment->rating != null)
                                {!! $ratingBar !!}
                                <br>
                                <span class="mt-0 mb-0">{{ $appointment->rating->comment }}</span><br>
                            @else
                                <span class="mt-0 mb-0">{{ __('No Feedback') }}</span><br>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>        
    </div>

    <div class="row flex-column flex-xl-row mt-2">
        @if ($prescription != null)
            <div class="card col-6 mr-2">
                <div class="card-header">
                    <h4 class="d-inline">
                        {{ __('Prescription') }}
                    </h4>
                </div>
                <div class="card-body">
                    @foreach ($prescription['addMedicine'] as $medicine)
                        <div class="medicine-item">
                            <div class="">
                                <span class="font-weight-bold text-dark">{{ $medicine['title'] }} -</span>
                                <span>{{ $medicine['mealTime'] == 0 ? __('Before Meal') : __('After Meal') }}</span><br>
                                <span>{{ $medicine['dosage'] }}</span><br>
                                <span class="pres-medi-note">{{ $medicine['notes'] }}</span>
                            </div>
                            <h5 class="text-dark">{{ $medicine['quantity'] }}</h5>
                        </div>
                    @endforeach

                </div>
            </div>
        @endif

    </div>
@endsection
