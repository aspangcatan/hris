@extends('master')

@section('content')
@php($accessRights = strtolower((string) session('access_rights')))
@php($canViewEmployeeDtr = in_array($accessRights, ['admin', 'schedule', 'dh'], true) || session('is_section_head') || session('is_division_head'))

<style>
.dtr-shell{display:grid;grid-template-columns:500px minmax(0,1fr);gap:1.25rem}
.dtr-side,.dtr-main,.dtr-card{background:#fff;border:1px solid #d9e2ec;border-radius:20px;box-shadow:0 16px 34px rgba(15,23,42,.08)}
.dtr-side{padding:1.2rem;position:sticky;top:1rem}
.dtr-main{padding:1.1rem}
.dtr-card{padding:1rem}
.dtr-title{font-size:1.8rem;font-weight:800;color:#0f172a}
.dtr-sub{color:#5b6b81;font-size:.92rem;margin-bottom:1rem}
.dtr-side .form-control,.dtr-side .form-select,.dtr-card .form-control,.dtr-card .form-select{border-radius:12px;min-height:44px}
.dtr-side-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem}
.dtr-field,.dtr-stat{background:#f8fbff;border:1px solid #dce6ef;border-radius:16px;padding:.9rem}
.dtr-field label,.dtr-stat-label{display:block;font-size:.75rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#607086;margin-bottom:.55rem}
.dtr-stat{display:flex;flex-direction:column;justify-content:center}
.dtr-stat strong{display:block;font-size:1.55rem;line-height:1;color:#0f172a}
.dtr-stat span{margin-top:.35rem;color:#5b6b81;font-size:.9rem}
.dtr-field--full,.dtr-actions{grid-column:1 / -1}
.dtr-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
.dtr-actions .btn:last-child{grid-column:1 / -1}
.dtr-actions .btn,.employee-foot .btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;text-align:center;white-space:nowrap}
.employee-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
.employee-card{background:#fff;border:1px solid #d9e2ec;border-radius:18px;padding:1rem;box-shadow:0 10px 24px rgba(15,23,42,.06);display:flex;flex-direction:column;min-height:190px}
.employee-card.selected{border-color:#0f7b7a;box-shadow:0 16px 30px rgba(15,123,122,.14)}
.employee-head{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem}
.employee-profile{display:flex;gap:.8rem;align-items:center;min-width:0}
.employee-avatar{width:54px;height:54px;border-radius:16px;object-fit:cover;background:#e5edf5;flex-shrink:0}
.employee-name{font-weight:800;color:#0f172a;line-height:1.15;font-size:1rem}
.employee-meta{font-size:.84rem;color:#64748b}
.employee-designation{margin-top:.2rem}
.employee-select-toggle{margin-top:.15rem;flex-shrink:0}
.employee-foot{display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin-top:auto;padding-top:1rem}
.employee-id{font-size:.95rem;color:#6b7280}
.employee-foot .btn{padding-inline:.9rem}
.employee-foot .btn.is-loading{pointer-events:none;opacity:.85}
.employee-foot .btn .spinner-border{width:.9rem;height:.9rem;border-width:.14em}
.dtr-empty{text-align:center;padding:2rem 1rem;border:1px dashed #cbd5e1;border-radius:16px;color:#64748b;background:#fbfdff}
@media (max-width:1199.98px){.employee-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:991.98px){.dtr-shell{grid-template-columns:1fr}.dtr-side{position:static}.dtr-side-grid,.dtr-actions{grid-template-columns:1fr}.employee-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:575.98px){.employee-grid{grid-template-columns:1fr}.employee-foot{flex-direction:column;align-items:stretch}.employee-foot .btn{width:100%}}
</style>

@if(!$canViewEmployeeDtr)
<div class="alert alert-danger mb-0">You are not authorized to access this page.</div>
@else
<div class="dtr-shell">
    <div class="dtr-side">
        <div class="dtr-title">Bulk Print</div>
        <div class="dtr-sub">Select employees, define the date window, then print one combined DTR PDF.</div>

        <div class="dtr-side-grid">
            <div class="dtr-field">
                <label for="bulk_from">From</label>
                <input type="date" class="form-control" id="bulk_from">
            </div>
            <div class="dtr-field">
                <label for="bulk_to">To</label>
                <input type="date" class="form-control" id="bulk_to">
            </div>
            @if(in_array($accessRights, ['admin', 'dh'], true))
            <div class="dtr-field">
                <label for="search_division">Division</label>
                <select class="form-select" id="search_division"><option value="">All divisions</option></select>
            </div>
            @endif
            @if(in_array($accessRights, ['admin', 'dh', 'schedule'], true))
            <div class="dtr-field">
                <label for="search_section">Section</label>
                <select class="form-select" id="search_section"><option value="">Loading sections...</option></select>
            </div>
            @endif
            <div class="dtr-stat">
                <div class="dtr-stat-label">Selected Employees</div>
                <input type="text" class="form-control" id="selected_count" readonly disabled>
            </div>
            <div class="dtr-field dtr-field--full">
                <label for="bulk_enable_automation">Enable Automation</label>
                <select class="form-select" id="bulk_enable_automation">
                    <option value="yes" selected>Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="dtr-field">
                <label for="bulk_reg_days_am">Reg Days (AM)</label>
                <input type="text" class="form-control" id="bulk_reg_days_am">
            </div>
            <div class="dtr-field">
                <label for="bulk_reg_days_pm">Reg Days (PM)</label>
                <input type="text" class="form-control" id="bulk_reg_days_pm">
            </div>
            <div class="dtr-field">
                <label for="bulk_sat_days_am">Saturday (AM)</label>
                <input type="text" class="form-control" id="bulk_sat_days_am">
            </div>
            <div class="dtr-field">
                <label for="bulk_sat_days_pm">Saturday (PM)</label>
                <input type="text" class="form-control" id="bulk_sat_days_pm">
            </div>
            <div class="dtr-actions">
                <button class="btn btn-success btn-view" id="btn_select_page" type="button"><i class="bi bi-check2-square"></i> Select Visible</button>
                <button class="btn btn-light border" id="btn_clear_selection" type="button"><i class="bi bi-eraser"></i> Clear Selection</button>
                <button class="btn btn-danger btn-edit" id="btn_bulk_print" type="button"><i class="bi bi-printer"></i> Print Selected</button>
            </div>
        </div>
    </div>

    <div class="dtr-main">
        <div class="dtr-card mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0 fw-bold">Filters</h5>
                <div class="text-muted small" id="employee_range">Showing 0-0 of 0</div>
            </div>
            <div class="row g-3">
                <div class="col-md-5"><input class="form-control" placeholder="Search by firstname" id="search_fname"></div>
                <div class="col-md-5"><input class="form-control" placeholder="Search by lastname" id="search_lname"></div>
                <div class="col-md-2 d-grid"><button class="btn btn-light border" id="btn_refresh_employees" type="button">Refresh</button></div>
            </div>
        </div>

        <div id="loading-message" class="text-center my-4" style="display:none;">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
            <p class="mt-2">Loading, please wait while we fetch the data...</p>
        </div>

        <div class="employee-grid" id="employee-container"></div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
            <div class="text-muted small" id="selection_hint">Choose employees from the cards, then print them in one PDF.</div>
            <nav aria-label="Employee DTR pagination"><ul class="pagination mb-0" id="employee-pagination"></ul></nav>
        </div>
    </div>
</div>

@include('modals.employee_dtr')
@endif
@stop

@section('js')
@if($canViewEmployeeDtr)
<script>
let userid=0,log_id=0,currentPage=1,visibleEmployeeIds=[];
const perPage=12,accessRights=@json($accessRights),canEditRawTimelog=["admin","it","dh"].includes(accessRights),selectedEmployees=new Set(),defaultViewDtrButtonHtml='<i class="bi bi-eye"></i> View DTR';

$(document).ready(function(){
    initDates(); initFilters(); getEmployeeDtr(1); updateSelectionSummary();
    $("#month,#year").change(function(){ viewDtr(userid); });
    $("#btn_print").click(function(){
        const p=new URLSearchParams({month:$("#month").val(),year:$("#year").val(),cutoff:$("#cutoff").val(),userid:userid,reg_days_am:$("#reg_days_am").val()||"",reg_days_pm:$("#reg_days_pm").val()||"",sat_days_am:$("#sat_days_am").val()||"",sat_days_pm:$("#sat_days_pm").val()||"",enable_automation:$("#enable_automation").val()||"yes"});
        window.open(`{{ route('printDTR') }}?${p.toString()}`,'_blank');
    });
    $("#btn_refresh_employees").click(()=>getEmployeeDtr(1));
    $("#search_section").change(()=>getEmployeeDtr(1));
    $("#search_division").change(function(){ loadSectionsByDivision($(this).val()).then(()=>getEmployeeDtr(1)); });
    $("#search_fname,#search_lname").on("keypress",e=>{ if(e.which===13) getEmployeeDtr(1); });
    $("#btn_select_page").click(function(){ visibleEmployeeIds.forEach(id=>selectedEmployees.add(Number(id))); syncCardSelections(); updateSelectionSummary(); });
    $("#btn_clear_selection").click(function(){ selectedEmployees.clear(); syncCardSelections(); updateSelectionSummary(); });
    $("#btn_bulk_print").click(bulkPrintSelected);
    $(document).on("change",".employee-select-toggle",function(){ const id=Number($(this).data("employee-id")); $(this).is(":checked")?selectedEmployees.add(id):selectedEmployees.delete(id); syncCardSelections(); updateSelectionSummary(); });
    $(document).on("click","#employee-pagination .page-link",function(e){ e.preventDefault(); const page=parseInt($(this).data("page"),10); if(page&&page!==currentPage) getEmployeeDtr(page); });
    bindRawLogEvents();
});

function initDates(){ $("#bulk_from").val(moment().startOf('month').format('YYYY-MM-DD')); $("#bulk_to").val(moment().endOf('month').format('YYYY-MM-DD')); }
function initFilters(){ if(["admin","dh"].includes(accessRights)){ loadDivision(); $("#search_section").html('<option value="">All sections</option>'); } else if(accessRights==="schedule"){ loadManagedSections(); } }
function currentFilters(){ return {division:$("#search_division").val()||"",section:$("#search_section").val()||"",fname:$("#search_fname").val()||"",lname:$("#search_lname").val()||""}; }

function loadDivision(){
    const myDivision=@json(Auth::user()->division), access=@json($accessRights);
    fetch("{{ route('getDivisionList') }}").then(r=>r.json()).then(data=>{
        const select=$("#search_division"); select.find("option:not(:first)").remove();
        data.forEach(it=>{ if(access==="dh" && String(myDivision)!==String(it.id)) return; select.append(`<option value="${it.id}">${it.description}</option>`); });
    }).catch(()=>alert("Ooops, something went wrong. Please reload"));
}

function loadSectionsByDivision(division){
    const select=$("#search_section"); if(!select.length) return Promise.resolve();
    if(!division){ select.html('<option value="">All sections</option>'); return Promise.resolve(); }
    return fetch(`{{ route('getSectionList') }}?division=${division}`).then(r=>r.json()).then(data=>{
        select.html('<option value="">All sections</option>');
        data.forEach(it=>select.append(`<option value="${it.id}">${it.description}</option>`));
    }).catch(()=>alert("Ooops, something went wrong. Please reload"));
}

function loadManagedSections(){
    fetch("{{ route('getManagedSections') }}").then(r=>r.json()).then(payload=>{
        const select=$("#search_section"), sections=payload.sections||[];
        select.html('<option value="">All assigned sections</option>');
        sections.forEach(section=>select.append(`<option value="${section.id}">${section.division_description?section.division_description+' - ':''}${section.description}</option>`));
    }).catch(()=>alert("Ooops, something went wrong. Please reload"));
}

function renderPagination(meta){
    const container=$("#employee-pagination"); container.empty(); if(!meta||meta.last_page<=1) return;
    const current=meta.current_page,last=meta.last_page,build=(page,label,disabled=false,active=false)=>`<li class="page-item${disabled?' disabled':''}${active?' active':''}"><a class="page-link" href="#" ${disabled?'':`data-page="${page}"`}>${label}</a></li>`;
    container.append(build(current-1,"&laquo;",current===1));
    const start=Math.max(1,current-2), end=Math.min(last,current+2);
    if(start>1){ container.append(build(1,"1",false,current===1)); if(start>2) container.append(build(0,"...",true)); }
    for(let page=start;page<=end;page++) container.append(build(page,page,false,page===current));
    if(end<last){ if(end<last-1) container.append(build(0,"...",true)); container.append(build(last,last,false,current===last)); }
    container.append(build(current+1,"&raquo;",current===last));
}

async function getEmployeeDtr(page=1){
    const f=currentFilters(), container=$("#employee-container"), loading=$("#loading-message");
    container.empty(); loading.show(); $("#employee-pagination").empty(); $("#employee_range").text("Showing 0-0 of 0"); currentPage=page; visibleEmployeeIds=[];
    fetch(`{{ route('getEmployeeDtr') }}?search_section=${encodeURIComponent(f.section)}&search_division=${encodeURIComponent(f.division)}&search_fname=${encodeURIComponent(f.fname)}&search_lname=${encodeURIComponent(f.lname)}&page=${page}&per_page=${perPage}`)
        .then(async r=>{ const data=await r.json(); if(!r.ok) throw new Error(data.error||"Failed to load employee DTR records."); return data; })
        .then(data=>{
            const list=data.data||[], meta={current_page:data.current_page||1,last_page:data.last_page||1,total:data.total||list.length,from:data.from||(list.length?1:0),to:data.to||list.length};
            $("#employee_range").text(`Showing ${meta.from}-${meta.to} of ${meta.total}`); renderPagination(meta);
            if(!list.length){ container.html(`<div class="dtr-empty"><div class="mb-2"><i class="bi bi-archive fs-3"></i></div><div>No employee records found for the current filters.</div></div>`); loading.hide(); return; }
            list.forEach(it=>{
                visibleEmployeeIds.push(Number(it.id));
                const image=it.picture?`https://dohcsmc.com/id/storage/crop/${it.picture}`:"assets/img/logo.png";
                const name=`${it.fname} ${it.mname?it.mname[0]+'.':''} ${it.lname}`; const checked=selectedEmployees.has(Number(it.id)); 
                container.append(`<article class="employee-card ${checked?'selected':''}" data-employee-id="${it.id}"><div class="employee-head"><div class="employee-profile"><img src="${image}" class="employee-avatar" alt="${name}"><div><div class="employee-name">${name}</div><div class="employee-meta employee-designation">${it.designation}</div></div></div><input class="form-check-input employee-select-toggle" type="checkbox" data-employee-id="${it.id}" ${checked?'checked':''}></div><div class="employee-foot"><span class="employee-id">ID: ${it.id}</span><button class="btn btn-success btn-view btn-sm" onclick="viewDtr(${it.id}, this)">${defaultViewDtrButtonHtml}</button></div></article>`);
            });
            syncCardSelections(); updateSelectionSummary(); loading.hide();
        }).catch(error=>{ console.log(error); container.html(`<div class="dtr-empty"><div class="mb-2"><i class="bi bi-exclamation-octagon fs-3"></i></div><div>${error.message||'Unable to load employee records.'}</div></div>`); loading.hide(); });
}

function setViewDtrButtonLoading(button, isLoading){
    if(!button) return;
    const $button=$(button);
    $button.prop("disabled",isLoading).toggleClass("is-loading",isLoading);
    $button.html(isLoading?'<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...':defaultViewDtrButtonHtml);
}

function setModalDtrLoadingState(isLoading){
    const loadingRow="<tr><td colspan='3' class='text-center text-muted'>Loading DTR records...</td></tr>";
    $("#month,#year,#cutoff,#btn_print").prop("disabled",isLoading);
    if(isLoading){
        $("#dtr_list").html(loadingRow);
        $("#raw_list").html(canEditRawTimelog?"<tr><td colspan='5' class='text-center text-muted'>Loading raw logs...</td></tr>":"<tr><td colspan='3' class='text-center text-muted'>Loading raw logs...</td></tr>");
    }
}

function syncCardSelections(){ $(".employee-card").each(function(){ const id=Number($(this).data("employee-id")), selected=selectedEmployees.has(id); $(this).toggleClass("selected",selected); $(this).find(".employee-select-toggle").prop("checked",selected); }); }
function updateSelectionSummary(){ $("#selected_count").val(selectedEmployees.size); $("#selection_hint").text(selectedEmployees.size?`${selectedEmployees.size} employee(s) queued for bulk DTR printing.`:"Choose employees from the cards, then print them in one PDF."); }

function bulkPrintSelected(){
    if(!selectedEmployees.size){ toastr.warning("Please select at least one employee.","No Employees Selected"); return; }
    const from=$("#bulk_from").val(), to=$("#bulk_to").val();
    if(!from||!to){ toastr.warning("Please set both From and To dates.","Missing Date Range"); return; }
    if(moment(to).isBefore(moment(from))){ toastr.warning("The To date must not be earlier than the From date.","Invalid Date Range"); return; }
    if(!moment(from).isSame(moment(to),'month')){ toastr.warning("Bulk DTR printing currently supports one month at a time.","Invalid Date Range"); return; }
    const p=new URLSearchParams({from:from,to:to,reg_days_am:$("#bulk_reg_days_am").val()||"",reg_days_pm:$("#bulk_reg_days_pm").val()||"",sat_days_am:$("#bulk_sat_days_am").val()||"",sat_days_pm:$("#bulk_sat_days_pm").val()||"",enable_automation:$("#bulk_enable_automation").val()||"yes",userids:Array.from(selectedEmployees).join(",")});
    window.open(`{{ route('printDTR') }}?${p.toString()}`,"_blank");
}

function bindRawLogEvents(){
    $("#btn_save_log").click(async function(){
        if(!canEditRawTimelog){ alert("Permission Denied"); return; }
        const date=$("#log_date").val(),time=$("#log_time").val(),status=$("#log_status").val(),datetime=moment(`${date} ${time}`,"YYYY-MM-DD HH:mm");
        if(!date||!time){ alert("All fields are required."); return; }
        Swal.fire({title:"Confirm Action",html:`Are you sure you want to set the time <b>${datetime.format("hh:mm A")}</b> on <b>${datetime.format("MMMM D, YYYY")}</b> with a status of <b>${status}</b>?`,icon:"question",showCancelButton:true,confirmButtonText:"Yes, confirm",cancelButtonText:"Cancel",confirmButtonColor:"#3085d6",cancelButtonColor:"#d33"}).then(async result=>{
            if(!result.isConfirmed) return;
            try{
                const response=await fetch("{{ route('storeManual') }}",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr('content')},body:JSON.stringify({log_id:log_id,userid:userid,date:date,time:time,status:status})});
                if(!response.ok){ const error=await response.json(); throw new Error(error.message||"Upload failed"); }
                const payload=await response.json(); toastr.success(payload.message,"Information"); $("#add_log_modal").modal("hide"); $("#log_date,#log_time").val(""); $("#log_status").val(""); viewDtr(userid);
            }catch(err){ console.error(err); alert("Error saving log: "+err.message); }
        });
    });
    $(document).on("click",".empty-field",function(e){ e.preventDefault(); log_id=0; const status=$(this).data("type"),day=$(this).data("day"); let month=$("#month").val(),year=$("#year").val(); if(!month||!year){ month=moment($("#bulk_from").val()||undefined).format("MM"); year=moment($("#bulk_from").val()||undefined).format("YYYY"); } $("#add_log_modal").modal("show"); $("#log_date").val(moment(`${year}-${month}-${day}`,"YYYY-MM-DD").format("YYYY-MM-DD")); $("#log_status").val(status); });
    $("#raw_list").on("click",".edit-btn",function(){ if(!canEditRawTimelog){ alert("Permission Denied"); return; } log_id=$(this).data("id"); $("#log_date").val(moment($(this).data("date"),"MM/DD/YYYY").format("YYYY-MM-DD")); $("#log_status").val($(this).data("status")); $("#log_time").val(moment($(this).data("time"),"hh:mm:ss A").format("HH:mm")); $("#add_log_modal").modal("show"); });
    $("#raw_list").on("click",".delete-btn",function(){ if(!canEditRawTimelog){ alert("Permission Denied"); return; } log_id=$(this).data("id"); const date=$(this).data("date"),status=$(this).data("status"),time=$(this).data("time"); Swal.fire({title:"Confirm Action",html:`Are you sure you want to delete the log time <b>${time}</b> on <b>${date}</b> with a status of <b>${status}</b>?`,icon:"question",showCancelButton:true,confirmButtonText:"Yes, confirm",cancelButtonText:"Cancel",confirmButtonColor:"#3085d6",cancelButtonColor:"#d33"}).then(async result=>{ if(!result.isConfirmed) return; try{ const response=await fetch("{{ route('deleteTimeLog') }}",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr('content')},body:JSON.stringify({log_id:log_id})}); if(!response.ok){ const error=await response.json(); throw new Error(error.message||"Upload failed"); } const payload=await response.json(); toastr.success(payload.message,"Information"); viewDtr(userid);}catch(err){ console.error(err); alert("Error saving log: "+err.message); } }); });
}

function addRawLog(){ if(!canEditRawTimelog){ alert("Permission Denied"); return; } $("#log_date,#log_status,#log_time").val(""); $("#add_log_modal").modal("show"); }

async function viewDtr(id, triggerButton=null){
    if(!id){ return; }
    setViewDtrButtonLoading(triggerButton, true);
    userid=id; let month=$("#month").val(),year=$("#year").val(); if(!month||!year){ month=moment($("#bulk_from").val()||undefined).format("M"); year=moment($("#bulk_from").val()||undefined).format("YYYY"); }
    $("#month").val(month); $("#year").val(year);
    setModalDtrLoadingState(true);
    fetch(`{{ route('getDTRLogs') }}?month=${month}&year=${year}&userid=${id}`).then(async res=>{ const data=await res.json(); if(!res.ok) throw new Error(data.error||"Failed to load DTR logs."); return data; }).then(data=>{
        $("#dtr_modal").modal("show"); $("#dtr_list").empty(); $("#raw_list").empty();
        data.results.forEach(it=>{ const ti=it.date_timein?moment(it.date_timein).format("hh:mm A"):"", to=it.date_timeout?moment(it.date_timeout).format("hh:mm A"):"", empty='<span class="text-muted">EMPTY</span>'; $("#dtr_list").append(`<tr><td>${it.day}</td><td>${ti||(canEditRawTimelog?`<a href="#" class="empty-field" data-type="IN" data-day="${it.day}">EMPTY</a>`:empty)}</td><td>${to||(canEditRawTimelog?`<a href="#" class="empty-field" data-type="OUT" data-day="${it.day}">EMPTY</a>`:empty)}</td></tr>`); });
        if(!data.raw_data.length){ $("#raw_list").append("<tr><td class='text-center' colspan='3'>No record found</td></tr>"); return; }
        data.raw_data.forEach(it=>{ const date=moment(it.created_at).format("MM/DD/YYYY"), time=moment(it.created_at).format("hh:mm:ss A"); $("#raw_list").append(canEditRawTimelog?`<tr><td>${date}</td><td>${it.status}</td><td><a target="_blank">${time}</a></td><td><button class="btn btn-action btn-edit edit-btn" data-id="${it.id}" data-date="${date}" data-status="${it.status}" data-time="${time}" title="Edit"><i class="bi bi-pencil"></i></button></td><td><button class="btn btn-action btn-delete delete-btn" data-id="${it.id}" data-date="${date}" data-status="${it.status}" data-time="${time}" title="Delete"><i class="bi bi-trash"></i></button></td></tr>`:`<tr><td>${date}</td><td>${it.status}</td><td><a target="_blank">${time}</a></td></tr>`); });
    }).catch(err=>{ console.log(err); toastr.error(err.message||"Unable to load DTR logs.","Error"); }).finally(()=>{ setModalDtrLoadingState(false); setViewDtrButtonLoading(triggerButton, false); });
}
</script>
@endif
@stop
