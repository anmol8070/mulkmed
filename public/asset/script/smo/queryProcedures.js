$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".QueryProceduresSideA").addClass("activeLi");

    // // Fetch Sound Categories
    // var url = `${domainUrl}getFaqCats`;
    // var faqCategories;
    // $.getJSON(url).done(function (data) {
    //     faqCategories = data.data;
    // });

    $("#THP").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}smo/fetchQueryProcedures`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    
    $("#THP").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}smo/deleteQueryProcedures` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#THP")
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
        var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
        $submitBtn.prop('disabled', true);
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#addCatForm")[0]);
            $.ajax({
                url: `${domainUrl}smo/addQueryProcedures`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $submitBtn.prop('disabled', false);
                    $(".loader").hide();
                    $("#addCatModal").modal("hide");
                    $("#addCatForm").trigger("reset");
                    $("#THP").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $submitBtn.prop('disabled', false);
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $submitBtn.prop('disabled', false);
            $(".loader").hide();
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });
    $("#editCatForm").on("submit", function (event) {
        event.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
        $submitBtn.prop('disabled', true);
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editCatForm")[0]);
            $.ajax({
                url: `${domainUrl}smo/editQueryProcedures`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $submitBtn.prop('disabled', false);
                    $(".loader").hide();
                    $("#editCatModal").modal("hide");
                    $("#editCatForm").trigger("reset");
                    $("#THP").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $submitBtn.prop('disabled', false);
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $submitBtn.prop('disabled', false);
            $(".loader").hide();
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });

    $("#THP").on("click", ".edit", function (event) {
        event.preventDefault();

        var id = $(this).attr("rel");
        var procedure = $(this).data("procedure");

        $("#editCatId").val(id);
        $("#editCatProcedure").val(procedure);


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
});
