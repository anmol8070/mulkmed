$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".doctorPlansSideA").addClass("activeLi");


    $("#PlanTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2,3,4,5,6,7],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorPlansList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#PlanTable").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}deleteDoctorPlan` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#PlanTable")
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
                url: `${domainUrl}addDoctorPlan`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#addCatModal").modal("hide");
                    $("#addPlanForm").trigger("reset");
                    $("#PlanTable").DataTable().ajax.reload(null, false);
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
    $("#editPlanForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#editPlanForm")[0]);
            $.ajax({
                url: `${domainUrl}editDoctorPlan`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#editCatModal").modal("hide");
                    $("#editPlanForm").trigger("reset");
                    $("#PlanTable").DataTable().ajax.reload(null, false);
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

    $("#PlanTable").on("click", ".edit", function (event) {
        event.preventDefault();
        console.log($(this));
        var plan_name = $(this).data("plan_name");
        var original_price = $(this).data("original_price");
         var discount = $(this).data("discount");
        var discount_type = $(this).data("discount_type");
         var hh_price = $(this).data("hh_price");
         console.log(hh_price);
        var number_of_consultations = $(this).data("number_of_consultations");
        var number_of_days = $(this).data("number_of_days");
        var consultation_text = $(this).data("consultation_text");
        var id = $(this).attr("rel");

        $("#editCatId").val(id);
        $("#editPlanName").val(plan_name);
        $("#editOriginalPrice").val(original_price);
         $("#editDiscount").val(discount);
        $("#editDiscountType").val(discount_type);
        $("#editHHPrice").val(hh_price);
        $("#editNumberOfConsultations").val(number_of_consultations);
        $("#editNumberOfDays").val(number_of_days);
        $("#editConsulationText").val(consultation_text);

        $("#editCatModal").modal("show");
    });

     // fetch doctor list
  $.ajax({
    url: 'https://pt.mulkmed.com/v2/doctorPlan/getDoctors',
    method: 'GET',
    dataType: 'json',
    success: function (response) {
      if (response.data && Array.isArray(response.data)) {
        response.data.forEach(function (doctor) {
          // append option dynamically
          $('#edit_doctors, #add_doctors').append(
            $('<option>', {
              value: doctor.id,
              text: doctor.name
            })
          );
        });

        // refresh Select2 after adding options
        $('#edit_doctors, #add_doctors').trigger('change');
      } else {
        console.error('No doctors data found in response', response);
      }
    },
    error: function (xhr, status, error) {
      console.error('Error fetching doctors:', error);
    }
  });
  
});

(function () {
  function loadScript(src) {
    return new Promise((res, rej) => {
      const s = document.createElement('script');
      s.src = src;
      s.onload = res;
      s.onerror = rej;
      document.head.appendChild(s);
    });
  }

  $(async function () {
    if (!$.fn.select2) {
      try {
        await loadScript('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js');
      } catch (e) {
        console.error('Failed to load Select2:', e);
        return;
      }
    }

    const $sels = $('#edit_doctors, #add_doctors');

    // 1) Ensure an empty option exists so placeholder can render
    $sels.each(function () {
      const $s = $(this);
      if (!$s.find('option[value=""]').length) {
        $s.prepend(new Option('', '', false, false)); // empty choice
      }
    });

    // 2) Initialize Select2
    $sels.select2({
      placeholder: 'Select Doctors',
      allowClear: true,
      width: '100%',
    });

$('#edit_doctors, #add_doctors').trigger('change');
  });
  
})();


