@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/bookingAndPayment.js') }}?v=1.1"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Booking & Payment') }}</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive col-12">
                <table class="table table-striped w-100 word-wrap" id="platformEarningsTable">
                    <thead>
                        <tr>
                            <th>{{ __('Appointment ID') }}</th>
                            <th>{{ __('Pyament Transaction ID') }}</th>
                            <th>{{ __('Service Name') }}</th>
                            <th>{{ __('Patient') }}</th>
                            <th>{{ __('Amount Paid') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Plan Name') }}</th>
                            <th>{{ __('Payment Method') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@endsection
