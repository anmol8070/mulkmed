$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".specialityWiseDiseasesSideA").addClass("activeLi");


    $("#DiseaseTable").dataTable({
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
            url: `${domainUrl}fetchSpecialityWiseDisease`,
            data: function (data) {
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

$('#DiseaseTable').on('xhr.dt', function (e, settings, json) {
    populateSpecialityDropdown('#editSpeciality', json.specialities);
    populateSpecialityDropdown('#addSpeciality', json.specialities);

});

function populateSpecialityDropdown(dropdownId, items) {
    const dropdown = $(dropdownId);
    dropdown.empty().append('<option value="">Select Speciality</option>');

    items.forEach(item => {
        dropdown.append(
            $('<option></option>').val(item.id).text(item.title)
        );
    });
}
    
    $("#DiseaseTable").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}deleteSpecialityWiseDisease` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        $("#DiseaseTable")
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

    $("#addDiseaseForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#addDiseaseForm")[0]);
            $.ajax({
                url: `${domainUrl}addSpecialityWiseDisease`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#addDiseaseModel").modal("hide");
                    $("#addDiseaseForm").trigger("reset");
                    $("#DiseaseTable").DataTable().ajax.reload(null, false);
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
    $("#editDiseaseForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editDiseaseForm")[0]);
            $.ajax({
                url: `${domainUrl}editSpecialityWiseDisease`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#editDiseaseModel").modal("hide");
                    $("#editDiseaseForm").trigger("reset");
                    $("#DiseaseTable").DataTable().ajax.reload(null, false);
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

    $("#DiseaseTable").on("click", ".edit", function (event) {
        event.preventDefault();
        var problem = $(this).data("problem");
        var image = $(this).data("image");
         var speciality = $(this).data("speciality");
        var priority = $(this).data("priority");
        var info = $(this).data("info");
        var id = $(this).attr("rel");

        const previewImageEdit = document.getElementById('editpreviewImage');
        previewImageEdit.src = $(this).data("icon");

        $("#editDiseaseId").val(id);
        $("#editProblem").val(problem);
        $("#editSpeciality").val(speciality).trigger('change');
        $("#editPriority").val(priority);
        $("#editInfo").val(info);


        $("#editDiseaseModel").modal("show");
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
