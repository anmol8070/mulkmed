@extends('include.app')
@section('header')

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">

    <script src="{{ asset('asset/script/online_consultation/doctorPlans.js') }}?v=1.1"></script>

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
            <h4>{{ __('Doctor Plans') }}</h4>

            <a data-toggle="modal" data-target="#addCatModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add Plan') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Plans') }}<span
                            class="badge badge-transparent "></span></a>
                </li>

                
            </ul>

            <div class="tab-content tabs" id="home">
                {{-- Section 1 --}}
                <div role="tabpanel" class="row tab-pane active" id="Section1">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="PlanTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Plan Name') }}</th>
                                    <th>{{ __('Original Price') }}</th>
                                    <th>{{ __('Discount') }}</th>
                                    <th>{{ __('Discount Type') }}</th>
                                    <th>{{ __('H & H Price') }}</th>
                                    <th>{{ __('Number Of Consultations') }}</th>
                                    <th>{{ __('Number of Days') }}</th>
                                    <th>{{ __('Consultation Text') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- Section 2 --}}
                <div role="tabpanel" class="row tab-pane" id="Section2">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="suggestionsTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Title') }}</th>
                                    <th class="w-30">{{ __('About') }}</th>
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

    {{-- Edit Plan Modal --}}
    <div class="modal fade" id="editCatModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Plan') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editPlanForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editCatId">

                        <div class="form-group">
                            <label> {{ __('Plan Name') }}</label>
                            <input id="editPlanName" type="text" name="plan_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Original Price') }}</label>
                            <input id="editOriginalPrice" type="number" step="0.01" name="original_price" class="form-control" required>
                        </div>

                         <div class="form-group">
                            <label> {{ __('Discount') }}</label>
                            <input id="editDiscount" type="number" step="0.01" name="discount" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="editDiscountType">{{ __('Discount Type') }}</label>
                            <select id="editDiscountType" name="discount_type" class="form-control" required>
                                <option value="" disabled selected>Select Discount Type</option>
                                <option value="flat">Flat</option>
                                <option value="percent">Percent</option>
                            </select>
                        </div>

                         <div class="form-group">
                            <label> {{ __('H & H Price') }}</label>
                            <input id="editHHPrice" type="number"  step="0.01" name="hh_price" class="form-control" required>
                        </div>


                         <div class="form-group">
                            <label> {{ __('Number Of Consultations') }}</label>
                            <input id="editNumberOfConsultations" type="number" name="number_of_consultations" class="form-control" required>
                        </div>

                         <div class="form-group">
                            <label> {{ __('Number of Days') }}</label>
                            <input id="editNumberOfDays" type="text" name="number_of_days" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Consultation Text') }}</label>
                            <input id="editConsulationText" type="text" name="consultation_text" class="form-control" required>
                        </div>

                        <div class="form-group">
                        <label for="edit_doctors" class="form-label">Doctors</label>
                        <select id="edit_doctors" name="doctor_ids[]" class="form-control" data-placeholder="Please select an option" multiple required>
                             <option ></option>
                        </select>
                        </div>

                    
                        <div class="form-group">
                            <input class="btn btn-primary mr-1" type="submit" value=" {{ __('Submit') }}">
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
    {{-- Add Plan Modal --}}
    <div class="modal fade" id="addCatModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Plan') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="addPlanForm" autocomplete="off">
                        @csrf

                        <div class="form-group">
                            <label> {{ __('Plan Name') }}</label>
                            <input id="editPlanName" type="text" name="plan_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Original Price') }}</label>
                            <input id="editOriginalPrice" type="number" step="0.01" name="original_price" class="form-control" required>
                        </div>

                         <div class="form-group">
                            <label> {{ __('Discount') }}</label>
                            <input id="editDiscount" type="number" step="0.01" name="discount" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="editDiscountType">{{ __('Discount Type') }}</label>
                            <select id="editDiscountType" name="discount_type" class="form-control" required>
                                <option value="" disabled selected>Select Discount Type</option>
                                <option value="flat">Flat</option>
                                <option value="percent">Percent</option>
                            </select>
                        </div>


                         <div class="form-group">
                            <label> {{ __('H & H Price') }}</label>
                            <input id="editHHPrice" type="number" step="0.01" name="hh_price" class="form-control" required>
                        </div>


                         <div class="form-group">
                            <label> {{ __('Number Of Consultations') }}</label>
                            <input id="editNumberOfConsultations" type="number" name="number_of_consultations" class="form-control" required>
                        </div>

                         <div class="form-group">
                            <label> {{ __('Number of Days') }}</label>
                            <input id="editNumberOfDays" type="text" name="number_of_days" class="form-control" required>
                        </div>

                          <div class="form-group">
                            <label> {{ __('Consultation Text') }}</label>
                            <input id="editConsultationText" type="text" name="consultation_text" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="add_doctors" class="form-label">Doctors</label>
                            <select id="add_doctors" name="doctor_ids[]" class="form-control" multiple required>
                                 <option></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <input class="btn btn-primary mr-1" type="submit" value=" {{ __('Submit') }}">
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
@endsection
