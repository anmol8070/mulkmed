$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".doctorCategoriesSideA").addClass("activeLi");

    function lockSubmit($form) {
        var $btn = $form.find('input[type="submit"], button[type="submit"]');
        if ($btn.data("busy")) {
            return false;
        }
        $btn.data("busy", true).prop("disabled", true);
        $btn.data("original-val", $btn.val());
        $btn.val("Please wait...");
        return true;
    }

    function unlockSubmit($form) {
        var $btn = $form.find('input[type="submit"], button[type="submit"]');
        $btn.data("busy", false).prop("disabled", false);
        if ($btn.data("original-val")) {
            $btn.val($btn.data("original-val"));
        }
    }

    function showErrorToast(message) {
        iziToast.error({
            title: strings.error,
            message: message || "Something went wrong. Please try again.",
            position: "topRight",
        });
    }

    $("#categoriesTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorCatsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#suggestionsTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorCatSuggestionsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#suggestionsTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url =
                        `${domainUrl}deleteDoctorCatSuggestion` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#suggestionsTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    $("#categoriesTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteDoctorCat` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#categoriesTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });

    $("#addCatForm").on("submit", function (event) {
        event.preventDefault();
        var $form = $(this);
        if (!lockSubmit($form)) {
            return;
        }
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#addCatForm")[0]);
            $.ajax({
                url: `${domainUrl}addDoctorCat`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    unlockSubmit($form);
                    $("#addCatModal").modal("hide");
                    $("#addCatForm").trigger("reset");
                    $("#addPreviewImage").attr("src", "http://placehold.jp/150x150.png");
                    $("#categoriesTable").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    unlockSubmit($form);
                    console.log(JSON.stringify(error));
                    showErrorToast();
                },
            });
        } else {
            $(".loader").hide();
            unlockSubmit($form);
            showErrorToast(strings.youAreTester);
        }
    });
    $("#editCatForm").on("submit", function (event) {
        event.preventDefault();
        var $form = $(this);
        if (!lockSubmit($form)) {
            return;
        }
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editCatForm")[0]);
            $.ajax({
                url: `${domainUrl}editDoctorCat`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    unlockSubmit($form);
                    $("#editCatModal").modal("hide");
                    $("#editCatForm").trigger("reset");
                    $("#categoriesTable").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    unlockSubmit($form);
                    console.log(JSON.stringify(error));
                    showErrorToast();
                },
            });
        } else {
            $(".loader").hide();
            unlockSubmit($form);
            showErrorToast(strings.youAreTester);
        }
    });

    $("#categoriesTable").on("click", ".edit", function (event) {
        event.preventDefault();

        var title = $(this).data("title");
        var keywords = $(this).data("keywords");
        var info = $(this).data("info");
        var id = $(this).attr("rel");

        $("#editCatId").val(id);
        $("#editCatTitle").val(title);
        $("#editCatKeywords").val(keywords);
        $("#editCatInfo").val(info);

         const previewImageEdit = document.getElementById('previewImage');
        previewImageEdit.src = $(this).data("icon");

        $("#editCatModal").modal("show");
    });

    $("#faqTable").on("click", ".edit", function (event) {
        event.preventDefault();

        var question = $(this).data("question");
        var answer = $(this).data("answer");
        var catId = $(this).data("cat");
        var id = $(this).attr("rel");

        $("#editFaqId").val(id);
        $("#editFaqQuestion").val(question);
        $("#editFaqAnswer").val(answer);

        $("#editFaqCategory").empty();

        $.each(faqCategories, function (indexInArray, category) {
            $("#editFaqCategory").append(`
                    <option ${
                        category.id == catId ? "selected" : ""
                    } value="${category.id}">${category.title}</option>
                `);
        });

        $("#editFaqModal").modal("show");
    });

    const fileInputEdit = document.getElementById('editImage');
    const previewImageEdit = document.getElementById('previewImage');

    if (fileInputEdit && previewImageEdit) {
        fileInputEdit.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImageEdit.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                previewImageEdit.src = "http://placehold.jp/150x150.png";
            }
        });
    }

    const fileInput = document.getElementById('addImage');
    const previewImage = document.getElementById('addPreviewImage');
    if (previewImage) {
        previewImage.src = "http://placehold.jp/150x150.png";
    }

    if (fileInput && previewImage) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                previewImage.src = "http://placehold.jp/150x150.png";
            }
        });
    }

    $('[data-target="#addCatModal"]').on("click", function () {
        const previewImageAdd = document.getElementById('addPreviewImage');
        if (previewImageAdd) {
            previewImageAdd.src = "http://placehold.jp/150x150.png";
        }
    });
});
