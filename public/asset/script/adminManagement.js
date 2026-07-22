$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".adminManagementSideA").addClass("activeLi");

    const rolesTable = $("#adminRolesTable").DataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "asc"]],
        columnDefs: [{ targets: "_all", orderable: false }],
        ajax: {
            url: `${domainUrl}fetchAdminRolesList`,
            error: (error) => console.log(error),
        },
    });

    const usersTable = $("#adminUsersTable").DataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [{ targets: "_all", orderable: false }],
        ajax: {
            url: `${domainUrl}fetchAdminUsersList`,
            error: (error) => console.log(error),
        },
    });

    function submitForm(formId, url, modalId, tables) {
        $(formId).on("submit", function (event) {
            event.preventDefault();
            $(".loader").show();

            if (user_type != "1") {
                $(".loader").hide();
                iziToast.error({
                    title: strings.error,
                    message: strings.youAreTester,
                    position: "topRight",
                });
                return;
            }

            $.ajax({
                url: `${domainUrl}${url}`,
                type: "POST",
                data: new FormData($(formId)[0]),
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    if (response.status) {
                        $(modalId).modal("hide");
                        $(formId).trigger("reset");
                        tables.forEach((table) => table.ajax.reload(null, false));
                        iziToast.success({
                            title: strings.success,
                            message: response.message || strings.operationSuccessful,
                            position: "topRight",
                        });
                    } else {
                        iziToast.error({
                            title: strings.error,
                            message: response.message || strings.error,
                            position: "topRight",
                        });
                    }
                },
                error: () => {
                    $(".loader").hide();
                    iziToast.error({
                        title: strings.error,
                        message: strings.error,
                        position: "topRight",
                    });
                },
            });
        });
    }

    submitForm("#addUserForm", "addAdminUser", "#addUserModal", [usersTable]);
    submitForm("#editUserForm", "editAdminUser", "#editUserModal", [usersTable]);

    $("#adminRolesTable").on("change", ".roleStatusToggle", function () {
        if (user_type != "1") {
            $(this).prop("checked", !$(this).prop("checked"));
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
            return;
        }

        const id = $(this).attr("rel");
        const checkbox = $(this);

        $.getJSON(`${domainUrl}toggleAdminRoleStatus/${id}`)
            .done(function (response) {
                if (!response.status) {
                    checkbox.prop("checked", !checkbox.prop("checked"));
                    iziToast.error({
                        title: strings.error,
                        message: response.message,
                        position: "topRight",
                    });
                } else {
                    iziToast.success({
                        title: strings.success,
                        message: response.message || strings.operationSuccessful,
                        position: "topRight",
                    });
                }
            })
            .fail(function () {
                checkbox.prop("checked", !checkbox.prop("checked"));
            });
    });

    $("#adminRolesTable").on("click", ".deleteRole", function (event) {
        event.preventDefault();
        confirmDelete(`${domainUrl}deleteAdminRole/${$(this).attr("rel")}`, [rolesTable]);
    });

    $("#adminUsersTable").on("click", ".editUser", function (event) {
        event.preventDefault();

        $("#editUserId").val($(this).attr("rel"));
        $("#editUserName").val($(this).data("username"));
        $("#editUserRole").val($(this).data("role"));
        $("#editUserType").val($(this).data("usertype"));
        $("#editUserPassword").val("");
        $("#editUserModal").modal("show");
    });

    $("#adminUsersTable").on("click", ".deleteUser", function (event) {
        event.preventDefault();
        confirmDelete(`${domainUrl}deleteAdminUser/${$(this).attr("rel")}`, [usersTable]);
    });

    function confirmDelete(url, tables) {
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (!isConfirm) return;

            if (user_type != "1") {
                iziToast.error({
                    title: strings.error,
                    message: strings.youAreTester,
                    position: "topRight",
                });
                return;
            }

            $.getJSON(url).done(function (response) {
                if (response.status) {
                    tables.forEach((table) => table.ajax.reload(null, false));
                    iziToast.success({
                        title: strings.success,
                        message: response.message || strings.operationSuccessful,
                        position: "topRight",
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: response.message,
                        position: "topRight",
                    });
                }
            });
        });
    }
});
