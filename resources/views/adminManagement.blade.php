@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/adminManagement.js') }}?v=3"></script>
@endsection

@section('content')
    <div class="card mt-3">
        <div class="card-header d-flex align-items-center">
            <h4 class="mb-0">{{ __('Admin Management') }}</h4>
            <div class="ml-auto">
                <a href="{{ route('adminRole.create') }}" class="btn btn-primary text-white mr-2">{{ __('Add Admin Role') }}</a>
                <a data-toggle="modal" data-target="#addUserModal" href=""
                    class="btn btn-success text-white">{{ __('Add Admin User') }}</a>
            </div>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills border-b mb-3 ml-0">
                <li role="presentation" class="nav-item">
                    <a class="nav-link pointer active" href="#RolesSection" role="tab" data-toggle="tab">{{ __('Admin Roles') }}</a>
                </li>
                <li role="presentation" class="nav-item">
                    <a class="nav-link pointer" href="#UsersSection" role="tab" data-toggle="tab">{{ __('Admin Users') }}</a>
                </li>
            </ul>

            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="RolesSection">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="adminRolesTable">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Role Name') }}</th>
                                    <th>{{ __('Sidebar Access') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div role="tabpanel" class="tab-pane" id="UsersSection">
                    <div class="table-responsive col-12">
                        <table class="table table-striped w-100" id="adminUsersTable">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Username') }}</th>
                                    <th>{{ __('Admin Role') }}</th>
                                    <th>{{ __('User Type') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add User Modal --}}
    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Add Admin User') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" autocomplete="off">
                        @csrf
                        <div class="form-group">
                            <label>{{ __('Username') }}</label>
                            <input type="text" name="user_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Password') }}</label>
                            <input type="password" name="user_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Admin Role') }}</label>
                            <select name="admin_role_id" class="form-control" required>
                                <option value="">{{ __('Select Role') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{ __('User Type') }}</label>
                            <select name="user_type" class="form-control" required>
                                <option value="1">{{ __('Admin') }}</option>
                                <option value="0">{{ __('Tester') }}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit User Modal --}}
    <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{ __('Edit Admin User') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" autocomplete="off">
                        @csrf
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="form-group">
                            <label>{{ __('Username') }}</label>
                            <input type="text" name="user_name" id="editUserName" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Password') }} <small class="text-muted">({{ __('leave blank to keep current') }})</small></label>
                            <input type="password" name="user_password" id="editUserPassword" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{ __('Admin Role') }}</label>
                            <select name="admin_role_id" id="editUserRole" class="form-control" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{ __('User Type') }}</label>
                            <select name="user_type" id="editUserType" class="form-control" required>
                                <option value="1">{{ __('Admin') }}</option>
                                <option value="0">{{ __('Tester') }}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input class="btn btn-primary" type="submit" value="{{ __('Submit') }}">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
