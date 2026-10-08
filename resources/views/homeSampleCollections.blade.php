@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/homeSampleCollections.js') }}?v=1.3"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Home Sample Collections') }}</h4>
            <div class="ml-auto d-flex">
                <select id="statusFilter" class="form-control">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="card-body">

            <div class="tab-content tabs">
                <div role="tabpanel" class="row tab-pane active" id="SectionBookings">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="homeSampleCollectionsTable">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('User') }}</th>
                                    <th>{{ __('Package / Order') }}</th>
                                    <th>{{ __('Lab') }}</th>
                                    <th>{{ __('Address') }}</th>
                                    <th>{{ __('Mobile') }}</th>
                                    <th>{{ __('Preferred Time') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Booked At') }}</th>
                                    <th>{{ __('Change Status') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
