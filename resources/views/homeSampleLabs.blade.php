@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/homeSampleLabs.js') }}?v=1.0"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Sample Collection Labs') }}</h4>
            <a href="#" id="addLabBtn" class="ml-auto btn btn-primary text-white">{{ __('Add Lab') }}</a>
        </div>
        <div class="card-body">
            <div class="table-responsive col-12">
                <table class="table table-striped w-100" id="labsTable">
                    <thead>
                        <tr>
                            <th>{{ __('Lab Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Bookings') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="labModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="labFormTitle">{{ __('Add Lab') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="labForm" autocomplete="off">
                        <input type="hidden" name="id" id="labId">

                        <div class="form-group">
                            <label>{{ __('Lab Name') }}</label>
                            <input type="text" id="labName" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Email') }}</label>
                            <input type="email" id="labEmail" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Status') }}</label>
                            <select id="labStatus" name="status" class="form-control">
                                <option value="1">{{ __('Active') }}</option>
                                <option value="0">{{ __('Inactive') }}</option>
                            </select>
                        </div>

                        <input class="btn btn-primary" type="submit" value="{{ __('Save Lab') }}">
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
