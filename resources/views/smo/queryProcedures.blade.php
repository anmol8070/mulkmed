@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/smo/queryProcedures.js') }}?v=1.1"></script>
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
            <h4>{{ __('Query Procedures') }}</h4>

            <a data-toggle="modal" data-target="#addCatModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add Query Procedures') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Query Procedures') }}<span
                            class="badge badge-transparent "></span></a>
                </li>

            </ul>

            <div class="tab-content tabs" id="home">
                {{-- Section 1 --}}
                <div role="tabpanel" class="row tab-pane active" id="Section1">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="THP">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Procedure') }}</th>
                                    <th class="w-30">{{ __('Action') }}</th>
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

    {{-- Edit Category Modal --}}
    <div class="modal fade" id="editCatModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Query Procedures') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editCatForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editCatId">
                    
                         <div class="form-group">
                            <label> {{ __('Procedure') }}</label>
                            <input id="editCatProcedure" type="text" name="procedure" class="form-control" required>
                        </div> 

                        <div class="form-group">
                            <input class="btn btn-primary mr-1" type="submit" value=" {{ __('Submit') }}">
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
    {{-- Add Category Modal --}}
    <div class="modal fade" id="addCatModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Query Procedures') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="addCatForm" autocomplete="off">
                        @csrf

                        <div class="form-group">
                        
                        <div class="form-group">
                            <label> {{ __('Procedure') }}</label>
                            <input type="text" name="procedure" class="form-control" required>
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
