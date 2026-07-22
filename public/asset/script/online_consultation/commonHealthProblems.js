$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".commonHealthProblemsSideA").addClass("activeLi");


    $("#ProblemTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
                {
                    width: '300px', targets: 0 ,
                    targets: [0, 1, 2,3,4],
                    orderable: false,
                },
            ],
        ajax: {
            url: `${domainUrl}fetchCommonHealthProblems`,
            data: function (data) {
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

$('#ProblemTable').on('xhr.dt', function (e, settings, json) {
    populateSpecialityDropdown('#editSpeciality', json.specialities);
    populateSpecialityDropdown('#addSpeciality', json.specialities);
});

function populateSpecialityDropdown(dropdownId, items) {
    const dropdown = $(dropdownId);
    dropdown.empty().append(`<option value="">Select Speciality</option>`);

    items.forEach(item => {
        dropdown.append(
            $('<option></option>').val(item.id).text(item.title)
        );
    });
}
    
    $("#ProblemTable").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}deleteCommonHealthProblems` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#ProblemTable")
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

    $("#addPlanForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#addPlanForm")[0]);
            $.ajax({
                url: `${domainUrl}addCommonHealthProblems`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#addProblemModal").modal("hide");
                    $("#addPlanForm").trigger("reset");
                    $("#ProblemTable").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $(".loader").hide();
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });
    $("#editProblemForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editProblemForm")[0]);
            $.ajax({
                url: `${domainUrl}editCommonHealthProblems`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#editProblemModel").modal("hide");
                    $("#editProblemForm").trigger("reset");
                    $("#ProblemTable").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $(".loader").hide();
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });

    $("#ProblemTable").on("click", ".edit", function (event) {
        event.preventDefault();
        var problem = $(this).data("problem");
        var image = $(this).data("image");
         var speciality = $(this).data("speciality");
        var priority = $(this).data("priority");
        var info = $(this).data("info");
        var id = $(this).attr("rel");

        const previewImageEdit = document.getElementById('editpreviewImage');
        previewImageEdit.src = $(this).data("icon");

        $("#editProblemId").val(id);
        $("#editProblem").val(problem);
        $("#editSpeciality").val(speciality).trigger('change');
        $("#editPriority").val(priority);
        $("#editInfo").val(info);

        $("#editProblemModel").modal("show");
    });

    const fileInputEdit = document.getElementById('editImage');
    const previewImageEdit = document.getElementById('editpreviewImage');


    fileInputEdit.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            previewImageEdit.src = e.target.result; // update preview with chosen file
        };
        reader.readAsDataURL(file);
        } else {
        // if user cancels selection, keep existing preview
            previewImageEdit.src = "http://placehold.jp/150x150.png";
        }
    });


    const fileInputAdd = document.getElementById('addImage');
    const previewImageAdd = document.getElementById('addPreviewImage');


    fileInputAdd.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            previewImageAdd.src = e.target.result; // update preview with chosen file
        };
        reader.readAsDataURL(file);
        } else {
        // if user cancels selection, keep existing preview
            previewImageAdd.src = "http://placehold.jp/150x150.png";
        }
    });

    document.querySelector(".btn-primary.text-white").onclick = function (event) {

        const previewImageAdd = document.getElementById('addPreviewImage');
            previewImageAdd.src = "http://placehold.jp/150x150.png";
    };
  
});
