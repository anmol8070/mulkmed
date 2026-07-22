@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/bidding/bidSubmitted.js') }}?v=1.2"></script>
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
            <h4>{{ __('Submitted Bid') }}</h4>

            <!-- <a data-toggle="modal" data-target="#addCatModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add New') }}</a> -->
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Submitted Bid') }}<span
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
                                    <th>{{ __('Service') }}</th>
                                    <th>{{ __('Budget') }}</th>
                                    <th>{{ __('Other Service') }}</th>
                                    <th>{{ __('Country') }}</th>
                                    <th>{{ __('City') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Comments') }}</th>
                                  

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
                    <h5>{{ __('View Submitted Bid') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editCatForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editCatId">
                    
                        <div class="form-group">
                            <label> {{ __('Service') }}</label>
                            <input id="service" type="text" name="service" class="form-control">
                          
                        </div>

                        <div class="form-group">
                            <label> {{ __('Budget') }}</label>
                            <input id="budget" type="text" name="budget" class="form-control">
                            
                        </div>

                        <div class="form-group">
                            <label>{{ __('Documents') }}</label>
                            <div id="medical_docs" class="d-flex flex-wrap gap-2"></div>
                        </div>



                        <div class="form-group" id="otherServiceEdit">
                            <label> {{ __('Other Service') }}</label>
                            <input id="other_service" type="text" name="other_service" class="form-control">
                          
                        </div>

                         <div class="form-group" id="otherServiceEdit">
                            <label> {{ __('Country') }}</label>
                            <input id="country" type="text" name="country" class="form-control">
                          
                        </div>

                         <div class="form-group" id="otherServiceEdit">
                            <label> {{ __('City') }}</label>
                            <input id="city" type="text" name="city" class="form-control">
                          
                        </div>

                         <div class="form-group" id="otherServiceEdit">
                            <label> {{ __('Date') }}</label>
                            <input id="date" type="text" name="date" class="form-control">
                          
                        </div>

                         <div class="form-group" id="otherServiceEdit">
                            <label> {{ __('Comments') }}</label>
                            <input id="comments" type="text" name="comments" class="form-control">
                          
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
                    <h5>{{ __('Add Submitted Bid') }}</h5>

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
