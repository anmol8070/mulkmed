$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".BiddingBannerSideA").addClass("activeLi");


    $("#bannerTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        aaSorting: [[0, "desc"]],
        columnDefs: [
                {
                    orderable: false,
                },
            ],
        ajax: {
            url: `${domainUrl}bidding/fetchBiddingBanners`,
            data: function (data) {
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

// function populateSpecialityDropdown(dropdownId, items, dropdownName) {
//     const dropdown = $(dropdownId);
//     dropdown.empty().append(`
//         <option value="">Select ${dropdownName}</option>`);

//     items.forEach(item => {
//         dropdown.append(
//             $('<option></option>').val(item.id).text(item.title)
//         );
//     });
// }

// function populateCHPDropdown(dropdownId, items, dropdownName) {
//     const dropdown = $(dropdownId);
//     dropdown.empty().append(`
//         <option value="">Select ${dropdownName}</option>`);

//     items.forEach(item => {
//         dropdown.append(
//             $('<option></option>').val(item.id).text(item.problem)
//         );
//     });
// }
    
    $("#bannerTable").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}bidding/deleteBiddingBanners` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        $("#bannerTable")
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

    function getFilteredFormData(formId){
        var formdata = new FormData();
        $(formId).find(':input[name]').each(function () {
            const $input = $(this);

            if ($input.is(':visible') || ($input.attr('id') == "editBannerId")) {
                const name = $input.attr('name');

                if ($input.attr('type') === 'file') {
                    const files = $input[0].files;
                    for (let i = 0; i < files.length; i++) {
                        formdata.append(name, files[i]);
                    }
                } else {
                    formdata.append(name, $input.val());
                }
            }
        });
        formdata.append('_token', $('meta[name="csrf-token"]').attr('content'));

        return formdata;
    }



    $("#addBannerForm").on("submit", function (event) {
        event.preventDefault();

         $(this).find("select").each(function () {
            const $parent = $(this).closest(".form-group, .dropdownBannerSubselect");

            if (!$parent.hasClass("d-none")) {
                
                $(this).prop("required", true);
            } else {
                $(this).prop("required", false);
            }
        });
        
        if (this.checkValidity()) {

            $(".loader").show();
            if (user_type == "1") {
                formdata = getFilteredFormData($('#addBannerForm'));
                $.ajax({
                    url: `${domainUrl}bidding/addBiddingBanners`,
                    type: "POST",
                    data: formdata,
                    dataType: "json",
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (response) {
                        $(".loader").hide();
                        $("#addBannerModal").modal("hide");
                        $("#addBannerForm").trigger("reset");
                        $("#bannerTable").DataTable().ajax.reload(null, false);
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
        }
        else {
            
            this.reportValidity();
        }
    });
    $("#editBannerForm").on("submit", function (event) {
        event.preventDefault();

        $(this).find("select").each(function () {
            const $parent = $(this).closest(".form-group, .dropdownBannerSubselect");

            if (!$parent.hasClass("d-none")) {
                $(this).prop("required", true);
            } else {
                $(this).prop("required", false);
            }
        });

         if (this.checkValidity()) {
            $(".loader").show();

            if (user_type == "1") {
                formdata = getFilteredFormData($('#editBannerForm'));
                $.ajax({
                    url: `${domainUrl}bidding/editBiddingBanners`,
                    type: "POST",
                    data: formdata,
                    dataType: "json",
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (response) {
                        $(".loader").hide();
                        $("#editBannerModel").modal("hide");
                        $("#editBannerForm").trigger("reset");
                        $("#bannerTable").DataTable().ajax.reload(null, false);
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
        }
        else {
            
            this.reportValidity();
        }
    });

    $("#bannerTable").on("click", ".edit", function (event) {
        event.preventDefault();
        var location = $(this).data("location");
        var id = $(this).attr("rel");

         const previewImageEdit = document.getElementById('editpreviewImage');
        previewImageEdit.src = $(this).data("icon");

        $("#editBannerId").val(id);
        $("#editLocation").val(location).trigger('change');
        $("#editBannerModel").modal("show");
    });

function hideElementWithClass(element) {
    if (element && !element.classList.contains('d-none')) {
        element.classList.add('d-none');
    }
}

function unhideElementWithClass(element) {
    if (element && element.classList.contains('d-none')) {
        element.classList.remove('d-none');
    }
}


const addSection = document.getElementById("addSection");
const addSubsection = document.getElementById("addSubsection");
const addDynamicDropdown = document.getElementById("addDynamicDropdown");
const addPage = document.getElementById("addPage");

const addSectionContainer = document.getElementById("addSectionContainer");
const addSubsectionContainer = document.getElementById("addSubsectionContainer");
const addDynamicDropdownContainer = document.getElementById("addDynamicDropdownContainer");
const addPageContainer = document.getElementById("addPageContainer");
const addDynamicDropdownLabel = document.getElementById("addDynamicDropdownLabel");

const editSection = document.getElementById("editSection");
const editSubsection = document.getElementById("editSubsection");
const editDynamicDropdown = document.getElementById("editDynamicDropdown");
const editPage = document.getElementById("editPage");

const editSectionContainer = document.getElementById("editSectionContainer");
const editSubsectionContainer = document.getElementById("editSubsectionContainer");
const editDynamicDropdownContainer = document.getElementById("editDynamicDropdownContainer");
const editPageContainer = document.getElementById("editPageContainer");
const editDynamicDropdownLabel = document.getElementById("editDynamicDropdownLabel");

const data = {
    "Mulkmed symptom checker": {
        "SymptomCheckerPage": []
    },
    "ads banner": {
        
    },
    "MULK AI Vitals":{
        "AI Vitals":[]
    },
    "MULK HnH": {
        "Web-page": []
    },
    "Orders Prescription": {
        "Orders": []
    },
    "Mulk Senior Card": {
        "Web-page": []
    },
    "Best Offers": {
        "BestOffersPage": []
    },
    "Surgical Cost estimation":{
        "Web-page": []
    },
    "Tourist gold card":{
        
    },
    "Second Medical Opinion (SMO)":{
        
    }
};

// add
// function initAddForm() {
 
//   addSection.value = "";
//   addSubsection.innerHTML = `<option value="">Select Subsection</option>`;
// //   addDynamicDropdown.innerHTML = `<option value="">Select Option</option>`;
//   addDynamicDropdown.innerHTML = `<input type="text" value="" name="url">`;
//   addPage.innerHTML = `<option value="">Select Page</option>`;
//   hideElementWithClass(addSubsectionContainer);
//   hideElementWithClass(addDynamicDropdownContainer);
//   hideElementWithClass(addPageContainer);

//  if (addSection.options.length <= 1) {
//     for (let section in data) {
//       addSection.innerHTML += `<option value="${section}">${section}</option>`;
//     }
//   }
// }

// // Reuse populateSubsection for Add
// addSection.addEventListener("change", async () => {
//   const selectedSection = addSection.value;
//   await populateSubsection(selectedSection);
// });

// Reuse populateSubsection for Edit
// editSection.addEventListener("change", async () => {
    
//   const selectedSection = editSection.value;
//   await populateEditSubsection(selectedSection);
// });

// Reuse dynamic/static logic
// addSubsection.addEventListener("change", async () => {
//   const section = addSection.value;
//   const selectedOption = addSubsection.options[addSubsection.selectedIndex];
//   const originalSubsection = selectedOption.dataset.original;
//   const isDynamic = selectedOption.dataset.dynamic === "true";

//   const pages = data[section]?.[originalSubsection];

//   if (isDynamic) {
//     const label = originalSubsection.includes("Web-page") ? "Enter URL:":"";
//     await populateDynamicOptions(originalSubsection, label);

//     //  Handle dropdown change to show pages
//     addDynamicDropdown.addEventListener("change", () => {
//       populatePages(pages);
//     });

//   } else {
//     hideElementWithClass(addDynamicDropdownContainer);
//     populatePages(pages);
//   }
// });

// // Reuse dynamic/static logic
// editSubsection.addEventListener("change", async () => {
//   const section = editSection.value;
//   const selectedOption = editSubsection.options[editSubsection.selectedIndex];
//   const originalSubsection = selectedOption.dataset.original;
//   const isDynamic = selectedOption.dataset.dynamic === "true";
//       const subsection = selectedOption.dataset.original;

//   const pages = data[section]?.[originalSubsection];

//   if (isDynamic) {
//     const label = subsection.includes("Web-page") ? "Enter URL:" : "";
//     await populateAddDynamicOptions(subsection, label, '');



//   } else {
//     hideElementWithClass(editDynamicDropdownContainer);
//     populatePages(pages);
//   }
// });


// async function fetchDynamicOptions(type) {

//     if(type.includes("Specialitywise Disease"))
//     {
//         try {
//             const response = await fetch(`${domainUrl}getSpecialityWiseDisease`);
//             const data = await response.json();
//             return data?.data; 
//         } catch (error) {
//             console.error('API Failed:', error);
//             return null;
//         }
//     }

//     else if (type.includes("Speciality")) {
//         try {
//             const response = await fetch(`${domainUrl}getSpecialities`);
//             const data = await response.json();
//             return data?.data; 
//         } catch (error) {
//             console.error('API Failed:', error);
//             return null;
//         }
//     }

//     else if(type.includes("Problem"))
//     {
//         try {
//             const response = await fetch(`${domainUrl}getCommonHealthProblems`);
//             const data = await response.json();
//             return data?.data; 
//         } catch (error) {
//             console.error('API Failed:', error);
//             return null;
//         }
//     }

//     return []; 
// }

function getDynamicDropdownTitle(item, subsection) {
     if (subsection.includes("Specialitywise Disease")) return item.problem;
    if (subsection.includes("Speciality")) return item.title;
    if (subsection.includes("Problem")) return item.problem;
    return item.title || item.name || 'Unknown';
}

async function populateSubsection(section) {
  addSubsection.innerHTML = `<option value="">Select Redirection</option>`;
  addPage.innerHTML = `<option value="">Select Page</option>`;
  addDynamicDropdown.innerHTML = `<option value="">Select Option</option>`;
  hideElementWithClass(addPageContainer);
  hideElementWithClass(addDynamicDropdownContainer);

  if (data[section] && Object.keys(data[section]).length > 0) {
    unhideElementWithClass(addSubsectionContainer);

    Object.keys(data[section]).forEach(sub => {
      const isDynamic = sub.includes("Web-page");
      const clean = sub.replace(/\s*\(dynamic\)/i, '');
      addSubsection.innerHTML += `<option value="${clean}" data-original="${sub}" data-dynamic="${isDynamic}">${clean}</option>`;
    //   addSubsection.innerHTML += `<input type="text" value="" data-dynamic="${isDynamic}" name="url">`;
    });
  } else {
    hideElementWithClass(addSubsectionContainer);
  }
}

async function populateEditSubsection(section) {
  editSubsection.innerHTML = `<option value="">Select Subsection</option>`;
  editPage.innerHTML = `<option value="">Select Page</option>`;
  editDynamicDropdown.innerHTML = `<option value="">Select Option</option>`;
  hideElementWithClass(editPageContainer);
  hideElementWithClass(editDynamicDropdownContainer);

  if (data[section] && Object.keys(data[section]).length > 0) {
    unhideElementWithClass(editSubsectionContainer);

    Object.keys(data[section]).forEach(sub => {
      const isDynamic = sub.includes("Web-page");
      const clean = sub.replace(/\s*\(dynamic\)/i, '');
      editSubsection.innerHTML += `<option value="${clean}" data-original="${sub}" data-dynamic="${isDynamic}">${sub}</option>`;
    });
  } else {
    hideElementWithClass(editSubsectionContainer);
  }
}

async function populateDynamicOptions(subsection, labelText) {
  unhideElementWithClass(addDynamicDropdownContainer);
  addDynamicDropdownLabel.innerText = labelText;

//   const apiData = await fetchDynamicOptions(subsection);
  addDynamicDropdown.innerHTML = `<input type="text" value="" name="url" class="form-control">`;

//   if (Array.isArray(apiData)) {
//     apiData.forEach(item => {
//       const label = getDynamicDropdownTitle(item, subsection);
//       addDynamicDropdown.innerHTML += `<option value="${item.id}">${label}</option>`;
//     });
//   } else {
//     console.warn("No data received or not an array:", apiData);
//   }
}

async function populateAddDynamicOptions(subsection, labelText, url = "") {
  unhideElementWithClass(editDynamicDropdownContainer);
  editDynamicDropdownLabel.innerText = labelText;
    editDynamicDropdown.innerHTML = `<input type="text" value="${url}" name="url" class="form-control">`;
//   const apiData = await fetchDynamicOptions(subsection);
//   editDynamicDropdown.innerHTML = `<option value="">Select Option</option>`;

//   if (Array.isArray(apiData)) {
//     apiData.forEach(item => {
//       const label = getDynamicDropdownTitle(item, subsection);
//       editDynamicDropdown.innerHTML += `<option value="${item.id}">${label}</option>`;
//     });
//   } 
//   else {
//     console.warn("No data received or not an array:", apiData);
//   }
}

function populatePages(pages) {
    if(pages.length){
    addPage.innerHTML = `<option value="">Select Page</option>`;
    pages.forEach(p => {
        addPage.innerHTML += `<option value="${p}">${p}</option>`;
    });
    unhideElementWithClass(addPageContainer);
    }
}

function populateAddPages(pages) {
    if(pages && pages.length){
    editPage.innerHTML = `<option value="">Select Page</option>`;
    pages.forEach(p => {
        editPage.innerHTML += `<option value="${p}">${p}</option>`;
    });
    unhideElementWithClass(editPageContainer);
    }
}
// edit
$(document).on('click', '.edit', async function () {

    // const section = this.dataset.name;
    // const subsection = this.dataset.redirection;
    // const url = this.dataset.url;
    // const page = this.dataset.page;
    const name = this.dataset.name;

    document.getElementById('editName').value = name;
  
//     editSection.value = "";
//     editSubsection.innerHTML = `<option value="">Select Subsection</option>`;
//     editDynamicDropdown.innerHTML = `<option value="">Select Option</option>`;
//     editPage.innerHTML = `<option value="">Select Page</option>`;
//     hideElementWithClass(editSubsectionContainer);
//     hideElementWithClass(editDynamicDropdownContainer);
//     hideElementWithClass(editPageContainer);

//  if (editSection.options.length <= 1) {
//     for (let section in data) {
//       editSection.innerHTML += `<option value="${section}">${section}</option>`;
//     }
//   }
//   // Preselect section
//   editSection.value = section;
//   await populateEditSubsection(section);
  
//   // Preselect subsection
// //   console.log(Array.from(editSubsection.options).find(opt => opt.dataset.original === subsection));
//     const selectedOpt = Array.from(editSubsection.options).find(opt => opt.dataset.original === subsection);

//     if (selectedOpt) {
        
//       editSubsection.value = selectedOpt.value;
//       const isDynamic = selectedOpt.dataset.dynamic === "true";
//       const pages = data[section]?.[selectedOpt.dataset.original];
   
//       if (isDynamic) {
//         const label = subsection.includes("Web-page") ? "Enter URL:" : "";
//         await populateAddDynamicOptions(subsection, label, url);

//         // if (optionId) {
//         //   editDynamicDropdown.value = optionId;
//         //   populateAddPages(pages);
//         // }

//       } else {
//         hideElementWithClass(editDynamicDropdownContainer);
//         populateAddPages(pages);
//       }

//       if (page) editPage.value = page;
//     }
  
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


