$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".TouristListSideA").addClass("activeLi");

    const touristTable = $("#TouristListTable").DataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: false,
        aaSorting: [[0, "desc"]],
        ajax: {
            url: domainUrl + "fetchTouristList",
            type: "GET",
            data: function (data) {
                data.start_date = $("#touristStartDate").val();
                data.end_date = $("#touristEndDate").val();
            },
            dataSrc: function (res) {
                return res.data || [];
            },
            error: function (error) {
                console.log("Tourist list fetch error:", error);
            },
        },
        columns: [
            { data: "id" },
            { data: "full_name" },
            { data: "phone_number" },
            { data: "check_in_time" },
            { data: "check_out_time" },
            { data: "fly_in" },
            { data: "fly_out" },
        ],
        columnDefs: [
            {
                targets: [3, 4, 5, 6],
                render: function (data) {
                    return data ? data : "-";
                },
            },
        ],
    });

    $("#touristDateFilterBtn").on("click", function () {
        touristTable.ajax.reload();
    });

    $("#touristDateResetBtn").on("click", function () {
        $("#touristStartDate").val("");
        $("#touristEndDate").val("");
        touristTable.ajax.reload();
    });
});
