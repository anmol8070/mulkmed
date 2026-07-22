$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".MulkmedChoiceOfDoctorsSideA").addClass("activeLi");

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
                targets: [0, 1, 2],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}smo/fetchMulkmedChoiceOfDoctors`,
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
                    var url = `${domainUrl}smo/deleteMulkmedChoiceOfDoctors` + "/" + id;

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
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#addCatForm")[0]);
            $.ajax({
                url: `${domainUrl}smo/addMulkmedChoiceOfDoctors`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
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
    $("#editCatForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editCatForm")[0]);
            $.ajax({
                url: `${domainUrl}smo/editMulkmedChoiceOfDoctors`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
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

    $("#THP").on("click", ".edit", function (event) {
        event.preventDefault();

        var name = $(this).data("name");
        var description = $(this).data("description");
        var doctor_id = $(this).data("doctor_id");
        var id = $(this).attr("rel");

        $("#editCatId").val(id);
        $("#editCatName").val(name);
        $("#editDoctorName").val(doctor_id).trigger('change');
        $("#editCatDescription").val(description);

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
        // previewImageEdit.src = $(this).data("icon");

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

         const fileInput = document.getElementById('addImage');
        const previewImage = document.getElementById('addPreviewImage');
        console.log("dd");
     previewImage.src = "http://placehold.jp/150x150.png";

        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImage.src = e.target.result; // update preview with chosen file
            };
            reader.readAsDataURL(file);
            } else {
            // if user cancels selection, keep existing preview
            previewImage.src = "http://placehold.jp/150x150.png";
            }
        });

         document.querySelector(".btn-primary.text-white").onclick = function (event) {
  
            const previewImageAdd = document.getElementById('addPreviewImage');
             previewImageAdd.src = "http://placehold.jp/150x150.png";
        };

        

        // fetch(`${domainUrl}smo/getDoctors`, {
        //     method: "GET",
        // })
        // .then(res => res.json())
        // .then(data => {

        //     const doctors = data.doctors;

        //     const editDoctorName = $('#editDoctorName');
        //     const addDoctorName = $('#addDoctorName');

        //     // Loop and append options to both
        //     doctors.forEach(doctor => {
        //         const option = `<option value="${doctor.id}">${doctor.name}</option>`;
        //         editDoctorName.append(option);
        //         addDoctorName.append(option);
        //     });
        // })
        // .catch(err => console.error('Error fetching hospitals:', err));


        // $('#editDoctorName').on('change', function () {
        //     const selectedText = $(this).find('option:selected').text(); //  Get the visible text
        //     $('#selectedDoctorTextEdit').val(selectedText); //  Set it to hidden input
        // });
});


document.addEventListener("DOMContentLoaded", function () {
    // ✅ Load jQuery + Select2 dynamically if missing
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    async function initDoctors() {
        // Ensure jQuery is loaded
        if (typeof jQuery === 'undefined') {
            await loadScript('https://code.jquery.com/jquery-3.6.0.min.js');
        }

        // Ensure Select2 is loaded
        if (typeof $.fn.select2 === 'undefined') {
            await loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
            document.head.appendChild(link);
        }

        // Once both are ready → fetch doctors
        fetch(`${domainUrl}smo/getDoctors`)
            .then(res => res.json())
            .then(data => {
                const doctors = data.doctors;
                const $editDoctorName = $('#editDoctorName');
                const $addDoctorName = $('#addDoctorName');

                // Clear + add default blank
                $editDoctorName.empty().append('<option></option>');
                $addDoctorName.empty().append('<option></option>');

                // Append all doctor options
                doctors.forEach(doctor => {
                    const option = `<option value="${doctor.id}">${doctor.name}</option>`;
                    $editDoctorName.append(option);
                    $addDoctorName.append(option);
                });

                // ✅ Initialize Select2 now that it’s 100% loaded
                $editDoctorName.select2({
                    placeholder: "Select Doctor",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#editCatModal')
                });

                $addDoctorName.select2({
                    placeholder: "Select Doctor",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#addCatModal')
                });
            })
            .catch(err => console.error('Error fetching doctors:', err));

        // Handle doctor name change
        $(document).on('change', '#editDoctorName', function () {
            const selectedText = $(this).find('option:selected').text();
            $('#selectedDoctorTextEdit').val(selectedText);
        });

         $(document).on('change', '#addDoctorName', function () {
            const selectedText = $(this).find('option:selected').text();
            $('#selectedDoctorTextAdd').val(selectedText);
        });
    }

    // Run everything
    initDoctors();
});


