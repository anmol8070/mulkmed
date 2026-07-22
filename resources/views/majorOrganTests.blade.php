@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/majorOrganTests.js') }}?v=1.1"></script>
@endsection

@section('content')
    <style>
        #organTestsTable table.dataTable td {
            white-space: normal !important;
        }

        .biomarker-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .biomarker-row .form-control {
            flex: 1;
        }

        .organ-test-preview-card {
            border: 1px solid #e4e6fc;
            border-radius: 12px;
            margin-bottom: 12px;
            overflow: hidden;
            background: #fff;
        }

        .organ-test-preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            cursor: pointer;
            background: #f8f9ff;
        }

        .organ-test-preview-header:hover {
            background: #f1f3ff;
        }

        .organ-test-preview-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 16px;
        }

        .organ-test-preview-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            background: #e9ecef;
        }

        .organ-test-preview-meta {
            text-align: right;
        }

        .organ-test-preview-price {
            font-weight: 700;
            color: #6777ef;
        }

        .organ-test-preview-count {
            color: #6c757d;
            font-size: 13px;
        }

        .organ-test-preview-body {
            display: none;
            padding: 0 18px 16px 70px;
        }

        .organ-test-preview-body.show {
            display: block;
        }

        .organ-test-preview-body ul {
            margin: 0;
            padding-left: 18px;
        }

        .organ-test-preview-body li {
            margin-bottom: 4px;
        }
    </style>

    <div class="card mt-3">
        <div class="card-header">
            <h4>{{ __('Major Organ Tests') }}</h4>
            <a data-toggle="modal" data-target="#addOrganTestModal" href=""
                class="ml-auto btn btn-primary text-white">{{ __('Add Organ Test') }}</a>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3 ml-0">
                <li role="presentation" class="nav-item">
                    <a class="nav-link pointer active" href="#SectionManage" role="tab" data-toggle="tab">{{ __('Manage Tests') }}</a>
                </li>
                <li role="presentation" class="nav-item">
                    <a class="nav-link pointer" href="#SectionPackage" role="tab" data-toggle="tab">{{ __('Package') }}</a>
                </li>
                <li role="presentation" class="nav-item">
                    <a class="nav-link pointer" href="#SectionPreview" role="tab" data-toggle="tab">{{ __('Frontend Preview') }}</a>
                </li>
            </ul>

            <div class="tab-content tabs">
                <div role="tabpanel" class="row tab-pane active" id="SectionManage">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="organTestsTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Icon') }}</th>
                                    <th>{{ __('Organ Test Name') }}</th>
                                    <th>{{ __('Total Price') }}</th>
                                    <th>{{ __('Biomarkers') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Display Order') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div role="tabpanel" class="tab-pane" id="SectionPackage">
                    <div class="col-12 col-lg-8">
                        <p class="text-muted">{{ __('Only one package record is allowed. Saving will create or update that single package.') }}</p>
                        <form action="" method="post" enctype="multipart/form-data" id="packageForm" autocomplete="off">
                            @csrf
                            <input type="hidden" name="id" id="packageId">

                            <div class="form-group">
                                <label>{{ __('Package Title') }}</label>
                                <input type="text" id="packageTitle" name="title" class="form-control" required
                                    placeholder="e.g. Comprehensive Mulk Longevity">
                            </div>

                            <div class="form-group">
                                <label>{{ __('Badge') }}</label>
                                <input type="text" id="packageBadge" name="badge" class="form-control"
                                    placeholder="e.g. High Recommended">
                            </div>

                            <div class="form-group">
                                <label>{{ __('Description') }}</label>
                                <textarea id="packageDescription" name="description" class="form-control" rows="4"></textarea>
                            </div>

                            <div class="form-group">
                                <label>{{ __('Package Price') }}</label>
                                <input type="number" step="0.01" min="0" id="packagePrice" name="price" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>{{ __('Package Image') }} <small class="text-muted">({{ __('optional') }})</small></label>
                                <input id="packageImage" type="file" name="image" accept="image/*" class="form-control">
                                <div class="mt-3">
                                    <label>{{ __('Image Preview') }}</label>
                                    <img id="packagePreviewImage" src="http://placehold.jp/120x120.png" alt="Preview"
                                        style="width:120px;height:120px;display:block;border:1px solid #ccc;border-radius:6px;object-fit:cover;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>{{ __('Status') }}</label>
                                <select id="packageStatus" name="status" class="form-control" required>
                                    <option value="1">{{ __('Active') }}</option>
                                    <option value="0">{{ __('Inactive') }}</option>
                                </select>
                            </div>

                            <div class="form-group d-flex align-items-center">
                                <input id="packageSubmitBtn" class="btn btn-primary mr-2" type="submit" value="{{ __('Save Package') }}">
                                <div id="packageFormLoader" class="d-none">
                                    <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div role="tabpanel" class="tab-pane" id="SectionPreview">
                    <div class="col-12">
                        <p class="text-muted">{{ __('This preview shows how active organ tests will appear on the frontend.') }}</p>
                        <div id="frontendPreviewContainer"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editOrganTestModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Organ Test') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="post" enctype="multipart/form-data" id="editOrganTestForm" autocomplete="off">
                        @csrf
                        <input type="hidden" name="id" id="editOrganTestId">

                        <div class="form-group">
                            <label>{{ __('Organ Test Name') }}</label>
                            <input type="text" id="editName" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Icon') }}</label>
                            <input id="editIcon" type="file" name="icon" accept="image/*" class="form-control">
                            <div class="mt-3">
                                <label>{{ __('Image Preview') }}</label>
                                <img id="editPreviewIcon" src="http://placehold.jp/120x120.png" alt="Preview"
                                    style="width:120px;height:120px;display:block;border:1px solid #ccc;border-radius:6px;object-fit:cover;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Total Price') }}</label>
                            <input type="number" step="0.01" min="0" id="editPrice" name="price" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Biomarkers') }}</label>
                            <div id="editBiomarkersContainer"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-biomarker-btn" data-target="#editBiomarkersContainer">
                                + {{ __('Add Biomarker') }}
                            </button>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Status') }}</label>
                            <select id="editStatus" name="status" class="form-control" required>
                                <option value="1">{{ __('Active') }}</option>
                                <option value="0">{{ __('Inactive') }}</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Display Order') }}</label>
                            <input type="number" min="0" id="editDisplayOrder" name="display_order" class="form-control" value="0">
                        </div>

                        <div class="form-group d-flex align-items-center">
                            <input id="editSubmitBtn" class="btn btn-primary mr-2" type="submit" value="{{ __('Submit') }}">
                            <div id="editFormLoader" class="d-none">
                                <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addOrganTestModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Organ Test') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="post" enctype="multipart/form-data" id="addOrganTestForm" autocomplete="off">
                        @csrf

                        <div class="form-group">
                            <label>{{ __('Organ Test Name') }}</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Icon') }}</label>
                            <input id="addIcon" type="file" name="icon" accept="image/*" class="form-control">
                            <div class="mt-3">
                                <label>{{ __('Image Preview') }}</label>
                                <img id="addPreviewIcon" src="http://placehold.jp/120x120.png" alt="Preview"
                                    style="width:120px;height:120px;display:block;border:1px solid #ccc;border-radius:6px;object-fit:cover;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Total Price') }}</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Biomarkers') }}</label>
                            <div id="addBiomarkersContainer"></div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-biomarker-btn" data-target="#addBiomarkersContainer">
                                + {{ __('Add Biomarker') }}
                            </button>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Status') }}</label>
                            <select name="status" class="form-control" required>
                                <option value="1">{{ __('Active') }}</option>
                                <option value="0">{{ __('Inactive') }}</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Display Order') }}</label>
                            <input type="number" min="0" name="display_order" class="form-control" value="0">
                        </div>

                        <div class="form-group d-flex align-items-center">
                            <input id="addSubmitBtn" class="btn btn-primary mr-2" type="submit" value="{{ __('Submit') }}">
                            <div id="addFormLoader" class="d-none">
                                <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
