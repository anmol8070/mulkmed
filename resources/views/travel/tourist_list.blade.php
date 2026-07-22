@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/tourist_list.js') }}?v=1.1"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Tourist List') }}</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="touristStartDate">{{ __('Start Date') }}</label>
                    <input type="date" id="touristStartDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="touristEndDate">{{ __('End Date') }}</label>
                    <input type="date" id="touristEndDate" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-primary mr-2" id="touristDateFilterBtn">{{ __('Filter') }}</button>
                    <button type="button" class="btn btn-secondary" id="touristDateResetBtn">{{ __('Reset') }}</button>
                </div>
            </div>
            <div class="table-responsive col-12">
                <table class="table table-striped w-100" id="TouristListTable">
                    <thead>
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Full Name') }}</th>
                            <th>{{ __('Phone Number') }}</th>
                            <th>{{ __('Check In') }}</th>
                            <th>{{ __('Check Out') }}</th>
                            <th>{{ __('Fly In') }}</th>
                            <th>{{ __('Fly Out') }}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
