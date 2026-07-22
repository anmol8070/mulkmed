@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/online_consultation/banners.js') }}?v=1.1"></script>

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
            <h4>{{ __('Banners') }}</h4>

            <a data-toggle="modal" data-target="#addBannerModal" href="" id="addButton"
                class="ml-auto btn btn-primary text-white">{{ __('Add Banner') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Banners') }}<span
                            class="badge badge-transparent "></span></a>
                </li>

                
            </ul>

            <div class="tab-content tabs" id="home">
                {{-- Section 1 --}}
                <div role="tabpanel" class="row tab-pane active" id="Section1">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="bannerTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Image') }}</th>
                                    <th>{{ __('Section') }}</th>
                                    <th>{{ __('Sub Section') }}</th>
                                    <th>{{ __('Page') }}</th>
                                    <th>{{ __('Speciality/Problem') }}</th>
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

    {{-- Edit Banner Modal --}}
    <div class="modal fade" id="editBannerModel" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Banner') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editBannerForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editBannerId">

                        <div class="form-group">
                            <label> {{ __('Image') }}</label>
                            <input id="editImage" type="file" name="image" accept="image/*" class="form-control">
                            <div id="EditpreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="editpreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:100px; display:block;border:1px solid #ccc; border-radius:6px;">
                            </div>
                        </div>

                        <div class="form-group" id="editSectionContainer">
                            <label> {{ __('Section') }}</label>
                            <select id="editSection" name="section" class="form-control" required>
                                <option value>{{__('Select Section')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="editSubsectionContainer">
                            <label> {{ __('Sub Section') }}</label>
                            <select id="editSubsection" name="sub_section" class="form-control">
                                <option value>{{__('Select Subsection')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="editDynamicDropdownContainer">
                            <label id="editDynamicDropdownLabel"> {{ __('Select Option') }}</label>
                            <select id="editDynamicDropdown" name="section_id" class="form-control">
                                <option value>{{__('Select Subsection')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="editPageContainer">
                            <label> {{ __('Page') }}</label>
                            <select id="editPage" name="page" class="form-control">
                                <option value>{{__('Select Page')}}</option>                            
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
    {{-- Add Banner Modal --}}
    <div class="modal fade" id="addBannerModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Banner') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="addBannerForm" autocomplete="off">
                        @csrf

                       <div class="form-group">
                            <label> {{ __('Image') }}</label>
                            <input id="addImage" type="file" name="image" accept="image/*" class="form-control" required>
                            <div id="addPreviewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="addPreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:100px; display:block;border:1px solid #ccc; border-radius:6px;">
                            </div>
                        </div>

                        <div class="form-group" id="addSectionContainer">
                            <label> {{ __('Section') }}</label>
                            <select id="addSection" name="section" class="form-control" required>
                                <option value>{{__('Select Section')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="addSubsectionContainer">
                            <label> {{ __('Sub Section') }}</label>
                            <select id="addSubsection" name="sub_section" class="form-control">
                                <option value>{{__('Select Subsection')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="addDynamicDropdownContainer">
                            <label id="addDynamicDropdownLabel"> {{ __('Select Option') }}</label>
                            <select id="addDynamicDropdown" name="section_id" class="form-control">
                                <option value>{{__('Select Subsection')}}</option>
                            </select>
                        </div>

                        <div class="form-group d-none" id="addPageContainer">
                            <label> {{ __('Page') }}</label>
                            <select id="addPage" name="page" class="form-control">
                                <option value>{{__('Select Page')}}</option>                            
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
