@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/online_consultation/commonHealthProblems.js') }}?v=1.1"></script>

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
            <h4>{{ __('Common Health Problems') }}</h4>

            <a data-toggle="modal" data-target="#addProblemModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add Problem') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Problems') }}<span
                            class="badge badge-transparent "></span></a>
                </li>

                
            </ul>

            <div class="tab-content tabs" id="home">
                {{-- Section 1 --}}
                <div role="tabpanel" class="row tab-pane active" id="Section1">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="ProblemTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Image') }}</th>
                                    <th>{{ __('Problem') }}</th>
                                    <th>{{ __('Speciality') }}</th>
                                    <th>{{ __('Priority') }}</th>
                                    <th>{{ __('Info') }}</th>
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

    {{-- Edit Problem Modal --}}
    <div class="modal fade" id="editProblemModel" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Problem') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editProblemForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editProblemId">

                        <div class="form-group">
                            <label> {{ __('Problem') }}</label>
                            <input id="editProblem" type="text" name="problem" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Image') }}</label>
                            <input id="editImage" type="file" name="image" class="form-control">
                            <div id="EditpreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="editpreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>

                         <div class="form-group">
                            <label> {{ __('Speciality') }}</label>
                            <select id="editSpeciality" name="speciality" class="form-control" required>
                                
                            </select>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Priority') }}</label>
                            <input id="editPriority" name="priority" class="form-control" required>
                                
                        </div>
                        <div class="form-group">
                            <label> {{ __('Info') }}</label>
                            <input id="editInfo" name="info" class="form-control">
                                
                        </div>
                    
                        <div class="form-group">
                            <input class="btn btn-primary mr-1" type="submit" value=" {{ __('Submit') }}">
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
    {{-- Add Problem Modal --}}
    <div class="modal fade" id="addProblemModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Problem') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="addPlanForm" autocomplete="off">
                        @csrf

                        <div class="form-group">
                            <label> {{ __('Problem') }}</label>
                            <input id="addProblem" type="text" name="problem" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Image') }}</label>
                            <input id="addImage" type="file" name="image" class="form-control" required>
                            <div id="addPreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="addPreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>

                         <div class="form-group">
                            <label> {{ __('Speciality') }}</label>
                            <select id="addSpeciality" name="speciality" class="form-control" required>
                                
                            </select>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Priority') }}</label>
                            <input id="addPriority" name="priority" class="form-control" required>
                             
                        </div>
                        <div class="form-group">
                            <label> {{ __('Info') }}</label>
                            <input id="addInfo" name="info" class="form-control">
                             
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
