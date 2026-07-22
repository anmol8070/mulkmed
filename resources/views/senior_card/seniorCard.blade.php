@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/senior_cards/SeniorCards.js') }}?v=1.1"></script>
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
            <h4>{{ __('Senior Cards') }}</h4>

            {{-- <a data-toggle="modal" data-target="#addCatModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add Top Hospitals') }}</a> --}}
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Senior Cards') }}<span
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
                                    <th class="w-25">{{ __('Card Number') }}</th>
                                    <th>{{ __('Name') }}</th>    
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Points') }}</th>
                                    <th>{{ __('Phone Number') }}</th>
                                    <th>{{ __('Payment Status') }}</th>
                                    <th class="">{{ __('Action') }}</th>
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
                    <h5>{{ __('Senior Card Details') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editCatForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editCatId">
                    
                        
                
                        <div class="form-group">
                            <label> {{ __('Card Number') }}</label>
                            <input id="cardNumberView" type="text" name="rating" class="form-control" required>
                        </div>  
                        
                        <div class="form-group">
                            <label> {{ __('User Name') }}</label>
                            <input id="usernameView" type="text" name="priority" class="form-control" required>
                        </div> 

                        <div class="form-group">
                            <label> {{ __('Phone Number') }}</label>
                            <input id="phoneNumberView" type="text" name="priority" class="form-control" required>
                        </div> 

                         <div class="form-group">
                            <label> {{ __('Email') }}</label>
                            <input id="emailView" type="text" name="priority" class="form-control" required>
                        </div> 

                         <div class="form-group">
                            <label> {{ __('Date OF Birth') }}</label>
                            <input id="dateOfBirthView" type="text" name="priority" class="form-control" required>
                        </div> 

                        
                        <div class="form-group">
                            <label> {{ __('Gender') }}</label>
                            <input id="genderView" type="text" name="priority" class="form-control" required>
                        </div> 
                        
                        <div class="form-group">
                           <label> {{ __('Address') }}</label>
                           <input id="addressView" type="text" name="priority" class="form-control" required>
                       </div> 

                        <div class="form-group">
                           <label> {{ __('Points') }}</label>
                           <input id="pointsView" type="text" name="priority" class="form-control" required>
                       </div> 

                        <div class="form-group">
                           <label> {{ __('Emirates ID') }}</label>
                           <input id="emiratesidView" type="text" name="priority" class="form-control" required>
                       </div> 

                        <div class="form-group">
                           <label> {{ __('Payment Status') }}</label>
                           <input id="paymentStatusView" type="text" name="priority" class="form-control" required>
                       </div> 

                        <div class="form-group">
                           <label> {{ __('Payment Amount') }}</label>
                           <input id="paymentAmountView" type="text" name="priority" class="form-control" required>
                       </div> 
                       
                       
                        {{-- <div class="form-group">
                            <input class="btn btn-primary mr-1" type="submit" value=" {{ __('Submit') }}">
                        </div> --}}

                    </form>
                </div>

            </div>
        </div>
    </div>

@endsection
