@extends('include.app')

@section('header')
    <script src="{{ asset('asset/script/lowestPriceFinder/set_value.js') }}?v=1.0"></script>
@endsection

@section('content')
<style>
    #Section2 table.dataTable td {
        white-space: normal !important;
    }
    .w-30 {
        width: 30% !important;
    }
</style>

<div class="card mt-3">
    <div class="card-header">
        <h4>{{ __('Lowest Price') }}</h4>

        <a data-toggle="modal" data-target="#addDiseaseModel" href=""
           class="ml-auto btn btn-primary text-white">{{ __('Add Price') }}</a>
    </div>

    <div class="card-body">
        <ul class="nav nav-pills border-b mb-3 ml-0">
            <li role="presentation" class="nav-item">
                <a class="nav-link pointer active" href="#Section1" role="tab" data-toggle="tab">
                    {{ __('Price') }}
                </a>
            </li>
        </ul>

        <div class="tab-content tabs">
            <div role="tabpanel" class="row tab-pane active" id="Section1">
                <div class="table-responsive col-12">

                        <table class="table table-striped w-100" id="THP">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Hospital Name') }}</th>
                                    <th>{{ __('Procedure') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================= EDIT MODAL ================= --}}
<div class="modal fade" id="editDiseaseModel" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5>{{ __('Edit Lowest Price') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="editDiseaseForm" method="POST" autocomplete="off">
                    @csrf

                    <input type="hidden" name="id" id="editDiseaseId">

                    <div class="form-group">
                        <label>{{ __('Hospital') }}</label>
                        <select id="editHospital" name="hospital_id" class="form-control" required></select>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Procedure Name') }}</label>
                        <select id="editProcedure" name="procedure_id" class="form-control" required></select>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Price') }}</label>
                        <input type="number" id="editPrice" name="price" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <input class="btn btn-primary mr-1" type="submit" value="{{ __('Submit') }}">
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

{{-- ================= ADD MODAL ================= --}}
<div class="modal fade" id="addDiseaseModel" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5>{{ __('Add Lowest Price') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="addDiseaseForm" method="POST" autocomplete="off">
                    @csrf

                    <div class="form-group">
                        <label>{{ __('Hospital') }}</label>
                        <select id="addHospital" name="hospital_id" class="form-control" required></select>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Procedure Name') }}</label>
                        <select id="addProcedure" name="procedure_id" class="form-control" required></select>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Price') }}</label>
                        <input type="number" id="addPrice" name="price" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <input class="btn btn-primary mr-1" type="submit" value="{{ __('Submit') }}">
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

@endsection
 