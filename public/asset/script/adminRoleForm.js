$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".adminManagementSideA").addClass("activeLi");

    $("#roleSelector").on("change", function () {
        const selected = $(this).val();
        if (selected === "new") {
            window.location.href = `${domainUrl}adminManagement/role/create`;
            return;
        }
        window.location.href = `${domainUrl}adminManagement/role/edit/${selected}`;
    });

    $("#roleName").on("change blur", function () {
        const name = $(this).val().trim();
        if (!name || !window.existingRolesMap || !window.existingRolesMap[name]) {
            return;
        }
        window.location.href = `${domainUrl}adminManagement/role/edit/${window.existingRolesMap[name]}`;
    });

    $(".permission-accordion-header").on("click", function (event) {
        if ($(event.target).closest("label.switch").length) {
            return;
        }
        $(this).closest(".permission-accordion").toggleClass("open");
    });

    $("#expandAllGroups").on("click", function () {
        $(".permission-accordion").addClass("open");
    });

    $("#collapseAllGroups").on("click", function () {
        $(".permission-accordion").removeClass("open");
    });

    $(".group-toggle").on("change", function () {
        const group = $(this).closest(".permission-accordion");
        const checked = $(this).is(":checked");
        group.find(".child-toggle").prop("checked", checked);
        if (checked) {
            group.addClass("open");
        }
    });

    $(".child-toggle").on("change", function () {
        const groupKey = $(this).data("group");
        const group = $(this).closest(".permission-accordion");
        const children = group.find(".child-toggle");
        const checkedChildren = children.filter(":checked").length;

        group.find(`.group-toggle[value="${groupKey}"]`).prop("checked", checkedChildren === children.length);

        if (checkedChildren > 0) {
            group.addClass("open");
        }
    });

    $("#adminRoleForm").on("submit", function (event) {
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

        const newRoleName = $("#roleName").val()?.trim();
        if (newRoleName && window.existingRolesMap && window.existingRolesMap[newRoleName]) {
            $(".loader").hide();
            window.location.href = `${domainUrl}adminManagement/role/edit/${window.existingRolesMap[newRoleName]}`;
            return;
        }

        $.ajax({
            url: `${domainUrl}saveAdminRole`,
            type: "POST",
            data: new FormData($("#adminRoleForm")[0]),
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function (response) {
                $(".loader").hide();
                if (response.status) {
                    iziToast.success({
                        title: strings.success,
                        message: response.message || strings.operationSuccessful,
                        position: "topRight",
                    });
                    setTimeout(() => {
                        window.location.href = `${domainUrl}adminManagement`;
                    }, 800);
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
});
