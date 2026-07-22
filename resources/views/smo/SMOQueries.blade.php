@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/smo/smoQuery.js') }}?v=1.2"></script>
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
            <h4>{{ __('SMO Queries') }}</h4>

            <!-- <a data-toggle="modal" data-target="#addCatModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add New') }}</a> -->
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('SMO Queries') }}<span
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
                                   <th>{{ __('Full Name') }}</th>
                                   <th>{{ __('Medical Report') }}</th>
                                   <th>{{ __('Contact Number') }}</th>
                                   <th>{{ __('Email') }}</th>
                                   <th>{{ __('Location') }}</th>
                                   <th>{{ __('Comment') }}</th>

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

    <div class="modal fade" id="editCatModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('View SMO Queries') }}</h5>

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
                            <input id="procedure" type="text" name="image" class="form-control">
                          
                        </div>

                        <div class="form-group">
                            <label> {{ __('Full Name') }}</label>
                            <input id="full_name" type="text" name="image" class="form-control">
                          
                        </div>

                        <div class="form-group">
                            <label>{{ __('Documents') }}</label>
                            <div id="medical_docs" class="d-flex flex-wrap gap-2"></div>
                        </div>



                        <div class="form-group">
                            <label> {{ __('Contact Number') }}</label>
                            <input id="contact_number" type="text" name="image" class="form-control">
                          
                        </div>

                        <div class="form-group">
                            <label> {{ __('Email') }}</label>
                            <input id="email" type="text" name="image" class="form-control">
                          
                        </div>

                        <div class="form-group">
                            <label> {{ __('Location') }}</label>
                            <input id="location" type="text" name="image" class="form-control">
                          
                        </div>

                        <div class="form-group">
                            <label> {{ __('Comment') }}</label>
                            <input id="comment" type="text" name="image" class="form-control">
                          
                        </div>


                        <!-- <div class="form-group">
                            <input class="btn btn-primary mr-1" type="submit" value=" {{ __('Submit') }}">
                        </div> -->

                    </form>
                </div>

            </div>
        </div>
    </div>
    <div class="modal fade" id="addCatModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add SMO Queries') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="addCatForm" autocomplete="off">
                        @csrf

                        <div class="form-group">
                            <label> {{ __('Image') }}</label>
                            <input accept="image/png, image/jpeg" id="addImage" type="file" name="image" class="form-control"
                                required>

                                <div id="previewContainer">
                            
                                    <label class="mt-3"> {{ __('Image Preview') }}</label>
                                    <img id="addPreviewImage" 
                                        src=""
                                        alt="Preview" 
                                    style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px; object-fit: cover;">
                                </div>
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
