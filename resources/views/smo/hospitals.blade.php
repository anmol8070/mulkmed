@extends('include.app')
@section('header')

    <!-- Load Google Maps JS (replace YOUR_API_KEY with your actual key) -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBtNweJE_bDxUtdLyBbfDsJB7P7ap3OsCQ&libraries=places"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    
    <script src="{{ asset('asset/script/smo/hospitals.js') }}?v=1.3"></script>
@endsection

@section('content')
    <style>
        #Section2 table.dataTable td {
            white-space: normal !important;
        }

        .w-30 {
            width: 30% !important;
        }
        /* Keep Google’s suggestions above modals/navbars */
        .pac-container { z-index: 2000 !important; }


        /* Default: no flex when options are selected */
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: contents; /* back to normal */
        }

        .select2-search__field {
             margin-top: 10px !important;
        }

        .chip-container {
            min-height: 45px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 5px;
        }
        .chip-input {
            border: none; 
            outline: none; 
            flex: 1;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
            margin-left: 13px !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #0080cb;   /* Bootstrap primary */
            border: 1px solid #0080cb;

        }


    /* Hover / highlight */
    .select2-container--default .select2-results__option--highlighted {
        background: #0080cb !important;  /* Bootstrap primary */
        color: #fff !important;
    }

    .badge{
        white-space: normal !important;
    }

    .chip-container{
        height: max-content !important;
    }

    </style>
    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Hospitals') }}</h4>

            <a data-toggle="modal" data-target="#addCatModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add Hospital') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3  ml-0">

                <li role="presentation" class="nav-item"><a class="nav-link pointer active" href="#Section1"
                        aria-controls="home" role="tab" data-toggle="tab">{{ __('Hospitals') }}<span
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
                                    <th>{{ __('Image') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Rating') }}</th>
                                    <th>{{ __('Rating Count') }}</th>
                                    <th>{{ __('Country') }}</th>
                                    <th class="w-30">{{ __('Address') }}</th>
                                    <th>{{ __('Website') }}</th>
                                    <th>{{ __('Contact Number') }}</th>
                                   
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
                    <h5>{{ __('Edit Hospital Procedures') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="editCatForm" autocomplete="off">
                        @csrf

                        <input type="hidden" name="id" id="editCatId">

                        <div class="form-group">
                            <label> {{ __('Image') }}</label>
                            <input id="editImage" accept="image/png, image/jpeg" type="file" name="image" class="form-control">
                            <div id="previewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="editPreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>
                    
                        <div class="form-group">
                            <label> {{ __('Name') }}</label>
                            <input id="editCatName" type="text" name="name" class="form-control" required>
                        </div> 

                        <div class="form-group">
                            <label> {{ __('Rating') }}</label>
                            <input id="editCatRating" type="text" name="rating" class="form-control" required>
                        </div> 

                        <div class="form-group">
                            <label> {{ __('Rating Count') }}</label>
                            <input type="text" id="rating_count_edit" name="rating_count" class="form-control" required>
                        </div> 

                        <div class="form-group">
                            <label> {{ __('Country') }}</label>
                            <input id="editCatCountry" type="text" name="country" class="form-control" required>
                        </div> 

                        <div class="form-group">
                            <label> {{ __('Address') }}</label>
                            <input id="editCatAddress" type="text" name="address" class="form-control" required>
                        </div> 

                        <div class="form-group">
                            <label> {{ __('Location') }}</label>
                            <input type="text" id="locationInputEdit" class="form-control" placeholder="start typing to get suggestion" required>
                            <input type="hidden" id="lat_edit" name="latitude">
                            <input type="hidden" id="lng_edit" name="longitude">
                        </div>

                        <div class="form-group">
                            <label> {{ __('Website') }}</label>
                            <input id="editWebsite" type="text" name="website" class="form-control" required>
                        </div>

                         <div class="form-group">
                            <label> {{ __('Contact Number') }}</label>
                            <input id="editContact" type="number" name="contact_number" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Category') }}</label>
                           <select name="category[]" id="categorySelectEdit"  class="form-control"
                                    multiple data-live-search="true" title="Select categories" required>
                            </select>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Clinic Timing') }}</label>
                            <input type="text" id="clinicTimingEdit" name="clinic_timing" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Services Offered') }}</label>
                            <div id="chip-container-services-edit" class="form-control chip-container">
                                <input type="text" id="chip-input-services-edit" placeholder="Enter Services Offered" class="chip-input">
                            </div>
                            <input type="hidden" name="services_offered" id="services-offered-hidden-edit">
                        </div>

                        <div class="form-group">
                            <label>{{ __('Exclusive MulkMed Benefits') }}</label>
                            <div id="chip-container-benefits-edit" class="form-control chip-container">
                                <input type="text" id="chip-input-benefits-edit" placeholder="Enter Exclusive MulkMed Benefits" class="chip-input">
                            </div>
                            <input type="hidden" name="exclusive_mulkmed_benefits" id="exclusive-mulkmed-hidden-edit">
                        </div>

                        <div class="form-group">
                            <label> {{ __('Procedures') }}</label>
                            <select name="procedure_ids[]" id="procedureSelectEdit"  class="form-control"
                                    multiple data-live-search="true" title="Select Procedures">
                            </select>
                        </div>

                     
                        <div class="mt-3">
                            <label class="form-label">Images</label>
                            <div id="editImagesWrapper" class="d-grid gap-3"></div>
                            <button type="button" class="btn btn-primary btn-sm mt-2" id="editAddMore">+ Add image</button>
                        </div>

                      
                      

                        <div class="form-group mt-4">
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
                    <h5>{{ __('Add Hospitals') }}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="" method="post" enctype="multipart/form-data" id="addCatForm" autocomplete="off">
                        @csrf

                        <div class="form-group">

                        <div class="form-group">
                            <label> {{ __('Image') }}</label>
                            <input id="addImage" accept="image/png, image/jpeg" type="file" name="image" class="form-control">
                            <div id="previewContainer">
                            
                                <label class="mt-3"> {{ __('Image Preview') }}</label>
                                <img id="addPreviewImage" 
                                    src=""
                                    alt="Preview" 
                                style="width:120px; height:120px; display:block;border:1px solid #ccc; border-radius:6px;object-fit: cover;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label> {{ __('Name') }}</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Rating') }}</label>
                            <input type="text" name="rating" class="form-control" required>
                        </div>

                        
                        <div class="form-group">
                            <label> {{ __('Rating Count') }}</label>
                            <input type="text" name="rating_count" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Country') }}</label>
                            <input type="text" name="country" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Address') }}</label>
                            <input type="text" name="address" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Location') }}</label>
                            <input type="text" id="locationInputAdd" class="form-control" placeholder="start typing to get suggestion" required>
                            <input type="hidden" id="lat_add" name="latitude">
                            <input type="hidden" id="lng_add" name="longitude">
                        </div>

                        <div class="form-group">
                            <label> {{ __('Website') }}</label>
                            <input type="text" name="website" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Contact Number') }}</label>
                            <input type="number" name="contact_number" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Category') }}</label>
                           <select name="category[]" id="categorySelectAdd"  class="form-control"
                                    multiple data-live-search="true" title="Select categories" required>
                            </select>
                        </div>

                        <div class="form-group">
                            <label> {{ __('Clinic Timing') }}</label>
                            <input type="text" name="clinic_timing" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Services Offered') }}</label>
                            <div id="chip-container-services" class="form-control chip-container">
                                <input type="text" id="chip-input-services" placeholder="Enter Services Offered" class="chip-input">
                            </div>
                            <input type="hidden" name="services_offered" id="services-offered-hidden">
                        </div>

                        <div class="form-group">
                            <label>{{ __('Exclusive MulkMed Benefits') }}</label>
                            <div id="chip-container-benefits" class="form-control chip-container">
                                <input type="text" id="chip-input-benefits" placeholder="Enter Exclusive MulkMed Benefits" class="chip-input">
                            </div>
                            <input type="hidden" name="exclusive_mulkmed_benefits" id="exclusive-mulkmed-hidden">
                        </div>

                        <div class="form-group">
                            <label> {{ __('Procedures') }}</label>
                            <select name="procedure_ids[]" id="procedureSelectAdd"  class="form-control"
                                    multiple data-live-search="true" title="Select Procedures">
                            </select>
                        </div>
                        <div class="form-group">
                        <label class="form-label">{{ __('Photos') }}</label>

                        <div id="uploadWrapper" class="d-grid gap-3">
                            <!-- starter card -->
                            <div class="card shadow-sm border-0 upload-item">
                            <div class="card-body text-center">
                                <input type="file" accept="image/png,image/jpeg" name="photos[]" class="form-control file-input mb-3">

                                <img src="http://placehold.jp/150x150.png"
                                    class="preview"
                                    style="width:120px;height:120px;display:block;border:1px solid #ccc;border-radius:6px;object-fit:cover;margin:0 auto;">

                                <button type="button" class="btn btn-outline-danger btn-sm mt-3 remove-btn d-none">Remove</button>
                            </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-primary mt-3" id="addMore">+ Add another</button>
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
