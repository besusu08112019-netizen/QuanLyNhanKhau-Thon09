(function(){
  'use strict';

  function reportModule(){
    var currentReport = null;
    var timeOptionalTypes = new Set(['summary','population','citizen','household','party_member','party','youth_union_member','meritorious_person','disabled_person','disability','age','gender','labor','elderly','children','poor-households','poor','near-poor-households','near_poor','health-insurance-area','health-insurance-household','health-insurance-expired','health-insurance-expiring','health-insurance-missing']);
    function q(s){return document.querySelector(s);}
    function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
    function token(){return localStorage.getItem('thon09_token')||(window.App&&window.App.token)||'';}
    function buildParams(){var form=q('#reportForm');var params=new URLSearchParams();if(!form)return params;var data=new FormData(form);data.forEach(function(value,key){if(value==null)return;var text=String(value).trim();if(text!=='')params.set(key,text);});var type=params.get('type')||params.get('report_type')||'summary';params.set('type',type);params.set('report_type',type);return params;}
    function reportType(){return buildParams().get('type')||'summary';}
    function apiUrl(path){var params=buildParams();return path+(params.toString()?'?'+params.toString():'');}
    async function fetchJson(path){var tk=token();if(!tk)throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');var res=await fetch(path,{headers:{Accept:'application/json',Authorization:'Bearer '+tk},cache:'no-store'});var json=await res.json().catch(function(){return null;});if(!res.ok||!json||!json.ok)throw new Error((json&&json.error&&json.error.message)||'Không tải được báo cáo.');return json.data||{};}
    function setTitle(text){var el=q('#reportTitle');if(el)el.textContent=text||'Báo cáo';}
    function setCount(report){var el=q('#reportCount');if(!el)return;var rows=Number(report&&report.totalRows!=null?report.totalRows:(report&&report.rows?report.rows.length:0));el.textContent='Tổng số: '+rows.toLocaleString('vi-VN')+' dòng';}
    function setActions(show){var el=q('#reportActions');if(el)el.classList.toggle('d-none',!show);}
    function table(report){var headers=report.headers||[];var rows=report.rows||[];if(!headers.length)return '<div class="report-empty-state">Báo cáo chưa có cấu trúc hiển thị.</div>';var head=headers.map(function(h){return '<th>'+esc(h)+'</th>';}).join('');var body=rows.length?rows.map(function(row){return '<tr>'+row.map(function(cell){return '<td>'+esc(cell)+'</td>';}).join('')+'</tr>';}).join(''):'<tr><td colspan="'+headers.length+'" class="text-center text-muted py-4">Không có dữ liệu</td></tr>';return '<table class="table report-table align-middle mb-0"><thead><tr>'+head+'</tr></thead><tbody>'+body+'</tbody></table>';}
    function showMessage(text,type){var box=q('#reportPreview');if(box)box.innerHTML='<div class="alert alert-'+(type||'info')+' mb-0">'+esc(text)+'</div>';}
    async function viewReport(){setActions(false);setTitle('Báo cáo');var count=q('#reportCount');if(count)count.textContent='Đang tải dữ liệu...';showMessage('Đang sinh báo cáo...','info');try{var report=await fetchJson(apiUrl('/api/reports/summary'));currentReport=report;setTitle(report.title||'Báo cáo');setCount(report);var preview=q('#reportPreview');if(preview)preview.innerHTML=table(report);setActions(true);return report;}catch(e){currentReport=null;setTitle('Báo cáo');if(count)count.textContent='Không sinh được báo cáo';showMessage(e.message||'Không sinh được báo cáo.','danger');throw e;}}
    async function ensureReport(){return currentReport||viewReport();}
    function download(kind){var tk=token();if(!tk){showMessage('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.','danger');return;}var url=apiUrl(kind==='excel'?'/api/reports/export-excel':'/api/reports/export-pdf');fetch(url,{headers:{Authorization:'Bearer '+tk},cache:'no-store'}).then(function(res){if(!res.ok)throw new Error('Không xuất được file.');return res.blob().then(function(blob){var name=(currentReport&&currentReport.title?currentReport.title:'bao_cao').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'')||'bao_cao';var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name+(kind==='excel'?'.xls':'.pdf');document.body.appendChild(a);a.click();URL.revokeObjectURL(a.href);a.remove();});}).catch(function(e){showMessage(e.message||'Không xuất được file.','danger');});}
    async function printReport(){
      try{
        var report=await ensureReport();
        var printData=await fetchJson(apiUrl('/api/reports/print')).catch(function(){return report;});
        var w=window.open('','_blank');
        if(!w){showMessage('Trình duyệt đang chặn cửa sổ in. Vui lòng cho phép popup.','warning');return;}
        var title=esc(printData.title||report.title||'Báo cáo');
        var html='<!doctype html><html><head><meta charset="utf-8"><title>'+title+'</title><style>body{font-family:Arial,sans-serif;color:#111827;margin:24px}h1{text-align:center;font-size:20px;margin:0 0 8px}p{text-align:center;margin:0 0 18px;color:#555}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #777;padding:6px;text-align:left;vertical-align:top}th{background:#eef2f7;font-weight:700}.sign{margin-top:36px;display:flex;justify-content:flex-end;text-align:center}</style></head><body><h1>'+title+'</h1><p>Loại báo cáo: '+esc(reportType())+' - Tổng số: '+Number(printData.totalRows||0).toLocaleString('vi-VN')+' dòng</p>'+table(printData)+'<div class="sign"><div>Người lập báo cáo<br><br><br>........................</div></div><script>window.onload=function(){window.print();};<\/script></body></html>';
        w.document.write(html);
        w.document.close();
      }catch(e){showMessage(e.message||'Không in được báo cáo.','danger');}
    }
    function lockReportTypes(){var select=q('#reportTypeSelect');if(!select)return;var value=select.value||'summary';var options=[['summary','Báo cáo tổng hợp'],['population','Báo cáo nhân khẩu'],['household','Báo cáo hộ gia đình'],['temporary_residence','Báo cáo tạm trú'],['temporary_absence','Báo cáo tạm vắng'],['migration','Báo cáo biến động'],['health_insurance','Báo cáo Bảo hiểm y tế'],['health-insurance-missing','Danh sách chưa tham gia BHYT'],['health-insurance-expiring','Danh sách BHYT sắp hết hạn (30 ngày)'],['health-insurance-expired','Danh sách BHYT đã hết hạn'],['health-insurance-household','Thống kê BHYT theo hộ'],['health-insurance-area','Thống kê BHYT theo khu vực'],['party_member','Báo cáo Đảng viên'],['meritorious_person','Báo cáo người có công'],['disabled_person','Báo cáo người khuyết tật'],['age','Báo cáo theo độ tuổi'],['gender','Báo cáo theo giới tính'],['youth_union_member','Báo cáo Đoàn viên'],['poor-households','Báo cáo hộ nghèo'],['near-poor-households','Báo cáo hộ cận nghèo'],['labor','Báo cáo lao động'],['elderly','Báo cáo người cao tuổi'],['children','Báo cáo trẻ em']];var html=options.map(function(item){return '<option value="'+item[0]+'">'+item[1]+'</option>';}).join('');if(select.innerHTML.replace(/\s+/g,' ').trim()!==html.replace(/\s+/g,' ').trim())select.innerHTML=html;if(!Array.prototype.some.call(select.options,function(o){return o.value===value;}))value='summary';select.value=value;}
    function updateDateVisibility(){lockReportTypes();var type=reportType();var hide=timeOptionalTypes.has(type);document.querySelectorAll('[data-report-date-field]').forEach(function(el){el.classList.toggle('report-date-muted',hide);});}
    function bind(){lockReportTypes();if(window.__thon09ReportReadyV2)return;window.__thon09ReportReadyV2=true;var form=q('#reportForm');if(form){form.addEventListener('submit',function(e){e.preventDefault();viewReport();});form.addEventListener('change',function(e){if(e.target&&e.target.name==='type'){currentReport=null;setActions(false);updateDateVisibility();}});}var print=q('#reportPrintBtn');if(print)print.addEventListener('click',function(e){e.preventDefault();printReport();});var excel=q('#reportExcelBtn');if(excel)excel.addEventListener('click',function(e){e.preventDefault();download('excel');});var pdf=q('#reportPdfBtn');if(pdf)pdf.addEventListener('click',function(e){e.preventDefault();download('pdf');});updateDateVisibility();}
    window.thon09ViewReport=function(){bind();return viewReport();};
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bind);else bind();
    setTimeout(lockReportTypes,0);
  }

  function personFilterModule(){
    function qs(s,r){return (r||document).querySelector(s);} function qsa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}
    function fillSelects(){qsa('[data-dictionary]').forEach(function(el){var list=(window.App&&App.dictionaries&&App.dictionaries[el.dataset.dictionary])||[];var current=el.value;el.innerHTML='<option value="">Tất cả</option>'+list.map(function(item){return '<option value="'+escapeHtml(item)+'">'+escapeHtml(item)+'</option>';}).join('');el.value=current||'';});}
    function applyResidence(p,value){if(value==='PERMANENT'||value==='TEMPORARY')p.set('residencyStatus',value);else if(value==='AWAY')p.set('presenceStatus','AWAY');}
    function applyAgeGroup(p,value){if(value==='0_5'){p.set('ageFrom','0');p.set('ageTo','5');}else if(value==='6_14'){p.set('ageFrom','6');p.set('ageTo','14');}else if(value==='15_17'){p.set('ageFrom','15');p.set('ageTo','17');}else if(value==='18_59'){p.set('ageFrom','18');p.set('ageTo','59');}else if(value==='60_plus'){p.set('ageFrom','60');}}
    function appendFilter(p,key,value){if(!value)return;if(key==='residenceCombined')applyResidence(p,value);else if(key==='ageGroup')applyAgeGroup(p,value);else p.set(key,value);}
    function personParams(includeSearch){var p=new URLSearchParams({page:App.persons.page||1,pageSize:App.persons.pageSize||20});if(includeSearch){var search=(qs('#personSearch')&&qs('#personSearch').value||App.persons.search||'').trim();if(search)p.set('search',search);}qsa('[data-person-filter]').forEach(function(el){var key=el.dataset.personFilter,val=String(el.value||'').trim();App.persons[key]=val;appendFilter(p,key,val);});return p;}
    function activeFilterParams(){var p=personParams(false);p.delete('page');p.delete('pageSize');return Object.fromEntries(p.entries());}
    function matchesQuickSearch(row,searchText){return [row.full_name,row.citizen_code,row.identity_number].some(function(value){return normalizeSearchText(value).includes(searchText);});}
    window.loadPersons=async function loadPersonsAdvanced(){try{var searchText=normalizeSearchText((qs('#personSearch')&&qs('#personSearch').value||App.persons.search||'').trim());App.persons.search=(qs('#personSearch')&&qs('#personSearch').value||'').trim();var items=[],total=0;var data=await api('/api/persons?'+personParams(true).toString(),{cacheTtl:12000});items=data.items||[];total=data.total||0;var totalEl=qs('#personTotalCount');if(totalEl)totalEl.innerHTML='Tổng số: <strong>'+number(total)+'</strong> nhân khẩu';var rows=qs('#personRows');if(rows)rows.innerHTML=renderPersonRows(items);if(typeof refreshUiEnhancements==='function')refreshUiEnhancements(qs('#personsScreen')||document);if(typeof updateBulkDeleteButtons==='function')updateBulkDeleteButtons();renderPager('#personPager',{total:total,page:App.persons.page,pageSize:App.persons.pageSize},function(page){App.persons.page=page;window.loadPersons();});}catch(error){showToast('Không tải được danh sách nhân khẩu: '+error.message,'danger');}};
    function bind(){fillSelects();if(window.__thon09PersonAdvancedBound)return;window.__thon09PersonAdvancedBound=true;qsa('[data-person-filter]').forEach(function(el){el.addEventListener('change',function(){App.persons.page=1;window.loadPersons();});el.addEventListener('input',debounce(function(){App.persons.page=1;window.loadPersons();},350));});var search=qs('#personSearch');if(search)search.addEventListener('input',debounce(function(){App.persons.page=1;window.loadPersons();},350));var pageSize=qs('#personPageSize');if(pageSize)pageSize.addEventListener('change',function(){App.persons.pageSize=Number(this.value||20);App.persons.page=1;window.loadPersons();});var toggle=qs('#personAdvancedToggle'),panel=qs('#personAdvancedFilters');function setAdvancedFilterOpen(open){if(!toggle||!panel)return;panel.classList.toggle('d-none',!open);toggle.setAttribute('aria-expanded',open?'true':'false');toggle.innerHTML='<i class="fa-solid fa-sliders"></i> '+(open?'Ẩn bộ lọc nâng cao':'Bộ lọc nâng cao');}if(toggle&&panel)toggle.addEventListener('click',function(){setAdvancedFilterOpen(panel.classList.contains('d-none'));});var apply=qs('#personAdvancedApply');if(apply)apply.addEventListener('click',function(){setAdvancedFilterOpen(false);App.persons.page=1;window.loadPersons();});var clearAdvanced=qs('#personAdvancedClear');if(clearAdvanced)clearAdvanced.addEventListener('click',function(){qsa('#personAdvancedFilters [data-person-filter]').forEach(function(el){el.value='';App.persons[el.dataset.personFilter]='';});App.persons.page=1;window.loadPersons();});var reset=qs('#personFilterReset');if(reset)reset.addEventListener('click',function(){if(search)search.value='';qsa('[data-person-filter]').forEach(function(el){el.value='';App.persons[el.dataset.personFilter]='';});App.persons.search='';App.persons.page=1;setAdvancedFilterOpen(false);window.loadPersons();});}
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bind);else bind();
  }

  function moduleDisplayOrderModule(){
    var orderedModules=[
      {screen:'households',mobileLabel:'Hộ',icon:'fa-house-chimney'},
      {screen:'persons',mobileLabel:'Nhân khẩu',icon:'fa-users'},
      {screen:'temporaryResidence',mobileLabel:'Tạm trú',icon:'fa-location-dot'},
      {screen:'temporaryAbsence',mobileLabel:'Tạm vắng',icon:'fa-person-walking-arrow-right'},
      {screen:'movements',mobileLabel:'Biến động',icon:'fa-right-left'},
      {screen:'publicAssets',mobileLabel:'Công trình',icon:'fa-building-columns'},
      {screen:'businessHouseholds',mobileLabel:'Kinh doanh',icon:'fa-store'},
      {screen:'livestock',mobileLabel:'Vật nuôi',icon:'fa-paw'},
      {screen:'houses',mobileLabel:'Nhà ở',icon:'fa-building-user'},
      {screen:'vehicles',mobileLabel:'Xe cộ',icon:'fa-car'},
      {screen:'agriculture',mobileLabel:'Nông nghiệp',icon:'fa-seedling'},
      {screen:'contributions',mobileLabel:'Đóng góp',icon:'fa-hand-holding-dollar'}
    ];
    var moduleScreens=orderedModules.map(function(item){return item.screen;});
    var moduleRank=Object.create(null);
    orderedModules.forEach(function(item,index){moduleRank[item.screen]=index;});
    var dashboardOrder=['dashboardHouseholds','dashboardPopulation','dashboardBusiness','dashboardLivestock','dashboardVehicles','dashboardGis','dashboardReports'];
    var dashboardRank=Object.create(null);
    dashboardOrder.forEach(function(screen,index){dashboardRank[screen]=index;});
    window.Thon09ModuleOrder=orderedModules.slice();
    window.Thon09ModuleScreenOrder=moduleScreens.slice();
    function screenOf(el){return el&&(el.dataset.screen||el.dataset.mobileScreen)||'';}
    function sortChildren(parent,selector,rank){
      if(!parent)return;
      var items=Array.prototype.slice.call(parent.querySelectorAll(selector)).filter(function(item){return rank[screenOf(item)]!==undefined;});
      if(items.length<2)return;
      items.sort(function(a,b){return rank[screenOf(a)]-rank[screenOf(b)];});
      var anchor=items[0];
      items.forEach(function(item){parent.insertBefore(item,anchor);anchor=item.nextSibling;});
    }
    function makeMobileButton(item){
      var button=document.createElement('button');
      button.type='button';
      button.dataset.mobileScreen=item.screen;
      button.setAttribute('aria-label',item.mobileLabel);
      button.innerHTML='<i class="fa-solid '+item.icon+'" aria-hidden="true"></i><span class="mobile-bottom-label">'+item.mobileLabel+'</span>';
      button.addEventListener('click',function(event){
        event.preventDefault();
        button.classList.add('is-pressing');
        window.setTimeout(function(){button.classList.remove('is-pressing');},180);
        if(typeof window.switchScreen==='function')window.switchScreen(item.screen);
      });
      return button;
    }
    function syncMobileNav(){
      var nav=document.querySelector('.mobile-bottom-nav');
      if(!nav)return;
      orderedModules.forEach(function(item){
        if(!nav.querySelector('[data-mobile-screen="'+item.screen+'"]'))nav.appendChild(makeMobileButton(item));
      });
      sortChildren(nav,'[data-mobile-screen]',moduleRank);
    }
    function syncSidebar(){
      document.querySelectorAll('.sidebar .nav-section').forEach(function(section){
        sortChildren(section,'.nav-link[data-screen]',moduleRank);
      });
    }
    function syncDashboardTree(){
      var tree=document.querySelector('[data-dashboard-children]');
      sortChildren(tree,'.dashboard-tree-link[data-screen]',dashboardRank);
    }
    function syncAll(){
      syncSidebar();
      syncMobileNav();
      syncDashboardTree();
    }
    window.thon09ApplyModuleDisplayOrder=syncAll;
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',syncAll);else syncAll();
    if(window.MutationObserver){
      var timer=0;
      var observer=new MutationObserver(function(records){
        var relevant=records.some(function(record){
          return Array.prototype.some.call(record.addedNodes||[],function(node){
            return node.nodeType===1&&((node.matches&&node.matches('.mobile-bottom-nav,.nav-link,[data-dashboard-children]'))||(node.querySelector&&node.querySelector('.mobile-bottom-nav,.nav-link,[data-dashboard-children]')));
          });
        });
        if(!relevant)return;
        clearTimeout(timer);
        timer=setTimeout(syncAll,0);
      });
      observer.observe(document.body,{childList:true,subtree:true});
    }
    setTimeout(syncAll,0);
    setTimeout(syncAll,250);
  }

  function headerGuardModule(){
    var labels={dashboard:'Dashboard',dashboardHouseholds:'Dashboard Hộ dân',dashboardPopulation:'Dashboard Nhân khẩu',dashboardBusiness:'Dashboard Kinh doanh',dashboardVehicles:'Dashboard Xe cộ',dashboardLivestock:'Dashboard Chăn nuôi',dashboardGis:'Dashboard GIS',dashboardReports:'Dashboard Báo cáo',operationCenter:'Trung tâm điều hành',gis:'Bản đồ địa bàn',businessHouseholds:'Hộ sản xuất & kinh doanh',vehicles:'Quản lý xe cộ',livestock:'Quản lý vật nuôi',agriculture:'Sản xuất nông nghiệp',contributions:'Đóng góp hộ',publicAssets:'Công trình công cộng',households:'Quản lý hộ gia đình',persons:'Quản lý nhân khẩu',reports:'Báo cáo thống kê',temporaryResidence:'Tạm trú',temporaryAbsence:'Tạm vắng',movements:'Biến động nhân khẩu',import:'Import dữ liệu',export:'Xuất Excel',exportExcel:'Xuất Excel',printForms:'In biểu mẫu',users:'Quản lý tài khoản',permissions:'Phân quyền',logs:'Nhật ký hệ thống',backups:'Sao lưu dữ liệu',restore:'Khôi phục dữ liệu',settings:'Cấu hình hệ thống',appearance:'Cấu hình giao diện'};
    function activeScreen(){var active=document.querySelector('.screen.active');if(active&&active.id)return active.id.replace(/Screen$/,'');return(window.App&&window.App.screen)||localStorage.getItem('thon09_screen')||'dashboard';}
    function cleanHeader(){var screen=activeScreen();var label=labels[screen]||'Dashboard';var title=document.querySelector('#screenTitle');var crumb=document.querySelector('#breadcrumbTrail');if(title)title.textContent=label;if(crumb)crumb.textContent='Trang chủ / '+label;document.querySelectorAll('.topbar-title-block small:not(#breadcrumbTrail), .topbar-title-block .text-muted:not(#breadcrumbTrail), .topbar > div:first-of-type small:not(#breadcrumbTrail), .topbar > div:first-of-type .text-muted:not(#breadcrumbTrail)').forEach(function(el){el.remove();});document.querySelectorAll('.dashboard-hero-row, .module-page-head > div, .person-page-head > div, .report-page-head, .screen > .admin-heading > div').forEach(function(el){el.remove();});}
    window.thon09CleanHeader=cleanHeader;document.addEventListener('DOMContentLoaded',cleanHeader);document.addEventListener('click',function(e){if(e.target.closest('[data-screen]'))setTimeout(cleanHeader,80);},true);setTimeout(cleanHeader,120);setTimeout(cleanHeader,500);
  }

  function navigationRepairModule(){
    var labels={dashboard:'Dashboard',dashboardHouseholds:'Dashboard Hộ dân',dashboardPopulation:'Dashboard Nhân khẩu',dashboardBusiness:'Dashboard Kinh doanh',dashboardVehicles:'Dashboard Xe cộ',dashboardLivestock:'Dashboard Chăn nuôi',dashboardGis:'Dashboard GIS',dashboardReports:'Dashboard Báo cáo',operationCenter:'Trung tâm điều hành',gis:'Bản đồ địa bàn',businessHouseholds:'Hộ sản xuất & kinh doanh',vehicles:'Quản lý xe cộ',livestock:'Quản lý vật nuôi',agriculture:'Sản xuất nông nghiệp',contributions:'Đóng góp hộ',households:'Quản lý hộ gia đình',persons:'Quản lý nhân khẩu',reports:'Báo cáo thống kê',temporaryResidence:'Tạm trú',temporaryAbsence:'Tạm vắng',movements:'Biến động nhân khẩu',import:'Import dữ liệu',export:'Xuất Excel',exportExcel:'Xuất Excel',printForms:'In biểu mẫu',users:'Quản lý tài khoản',permissions:'Phân quyền',logs:'Nhật ký hệ thống',backups:'Sao lưu dữ liệu',restore:'Khôi phục dữ liệu',settings:'Cấu hình hệ thống',appearance:'Cấu hình giao diện'};
    labels={dashboard:'Dashboard',dashboardHouseholds:'Dashboard H\u1ed9 d\u00e2n',dashboardPopulation:'Dashboard Nh\u00e2n kh\u1ea9u',dashboardBusiness:'Dashboard Kinh doanh',dashboardVehicles:'Dashboard Xe c\u1ed9',dashboardLivestock:'Dashboard Ch\u0103n nu\u00f4i',dashboardGis:'Dashboard GIS',dashboardReports:'Dashboard B\u00e1o c\u00e1o',operationCenter:'Trung t\u00e2m \u0111i\u1ec1u h\u00e0nh',gis:'B\u1ea3n \u0111\u1ed3 \u0111\u1ecba b\u00e0n',businessHouseholds:'H\u1ed9 s\u1ea3n xu\u1ea5t & kinh doanh',vehicles:'Qu\u1ea3n l\u00fd xe c\u1ed9',livestock:'Qu\u1ea3n l\u00fd v\u1eadt nu\u00f4i',agriculture:'S\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p',contributions:'\u0110\u00f3ng g\u00f3p h\u1ed9',publicAssets:'C\u00f4ng tr\u00ecnh c\u00f4ng c\u1ed9ng',households:'Qu\u1ea3n l\u00fd h\u1ed9 gia \u0111\u00ecnh',persons:'Qu\u1ea3n l\u00fd nh\u00e2n kh\u1ea9u',reports:'B\u00e1o c\u00e1o th\u1ed1ng k\u00ea',temporaryResidence:'T\u1ea1m tr\u00fa',temporaryAbsence:'T\u1ea1m v\u1eafng',movements:'Bi\u1ebfn \u0111\u1ed9ng nh\u00e2n kh\u1ea9u',import:'Import d\u1eef li\u1ec7u',export:'Xu\u1ea5t Excel',exportExcel:'Xu\u1ea5t Excel',printForms:'In bi\u1ec3u m\u1eabu',users:'Qu\u1ea3n l\u00fd t\u00e0i kho\u1ea3n',permissions:'Ph\u00e2n quy\u1ec1n',logs:'Nh\u1eadt k\u00fd h\u1ec7 th\u1ed1ng',backups:'Sao l\u01b0u d\u1eef li\u1ec7u',restore:'Kh\u00f4i ph\u1ee5c d\u1eef li\u1ec7u',settings:'C\u1ea5u h\u00ecnh h\u1ec7 th\u1ed1ng',appearance:'C\u1ea5u h\u00ecnh giao di\u1ec7n'};
    var dashboardScreens={dashboard:true,dashboardHouseholds:true,dashboardPopulation:true,dashboardBusiness:true,dashboardVehicles:true,dashboardLivestock:true,dashboardGis:true,dashboardReports:true};
    var originalSwitchScreen=typeof window.switchScreen==='function'?window.switchScreen:null;
    function normalizeScreen(screen){return screen==='export'?'exportExcel':(screen||'dashboard');}
    function centerMobileBottomNavItem(button){
      if(!button)return;
      try{button.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});}
      catch(error){
        var nav=button.closest&&button.closest('.mobile-bottom-nav');
        if(nav)nav.scrollLeft=Math.max(0,button.offsetLeft-((nav.clientWidth-button.offsetWidth)/2));
      }
    }
    function ensurePublicAssetsMobileNavItem(){
      var nav=document.querySelector('.mobile-bottom-nav');
      if(!nav||nav.querySelector('[data-mobile-screen="publicAssets"]'))return;
      var button=document.createElement('button');
      button.type='button';
      button.dataset.mobileScreen='publicAssets';
      button.setAttribute('aria-label','Công trình công cộng');
      button.innerHTML='<i class="fa-solid fa-building-columns" aria-hidden="true"></i><span class="mobile-bottom-label">Công trình</span>';
      button.addEventListener('click',function(event){event.preventDefault();button.classList.add('is-pressing');window.setTimeout(function(){button.classList.remove('is-pressing');},180);window.switchScreen('publicAssets');});
      var gis=nav.querySelector('[data-mobile-screen="gis"]');
      nav.insertBefore(button,gis||null);
    }
    function setDashboardTreeOpen(open,persist){var tree=document.querySelector('[data-dashboard-tree]');if(!tree)return;var toggle=tree.querySelector('[data-dashboard-toggle]');var caret=tree.querySelector('.dashboard-tree-caret');tree.classList.toggle('is-open',!!open);if(toggle)toggle.setAttribute('aria-expanded',open?'true':'false');if(caret)caret.innerHTML=open?'&#9662;':'&#9656;';if(persist){try{localStorage.setItem('thon09_dashboard_tree_open',open?'1':'0');}catch(error){}}}
    function syncDashboardTree(screen,requested){screen=normalizeScreen(screen);requested=normalizeScreen(requested);var active=!!(dashboardScreens[screen]||dashboardScreens[requested]);setDashboardTreeOpen(active,false);var tree=document.querySelector('[data-dashboard-tree]');if(tree)tree.classList.toggle('is-active',active);document.querySelectorAll('[data-dashboard-tree] .dashboard-tree-link[data-screen]').forEach(function(btn){var current=btn.dataset.screen===screen||btn.dataset.screen===requested;btn.classList.toggle('active',current);btn.setAttribute('aria-current',current?'page':'false');});}
    function syncCentralScreenState(screen,requested){var previous=window.App&&window.App.screen;if(window.App)window.App.screen=screen;try{localStorage.setItem('thon09_screen',screen);}catch(error){}if(previous===screen)return;try{document.dispatchEvent(new CustomEvent('thon09:screen-change',{detail:{screen:screen,requestedScreen:requested}}));}catch(error){}if(screen==='households'&&typeof window.loadHouseholds==='function')window.loadHouseholds();if(screen==='persons'&&typeof window.loadPersons==='function')window.loadPersons();if(screen==='dashboard'&&typeof window.loadDashboard==='function')window.loadDashboard();}
    function repairScreenState(screen,requested){screen=normalizeScreen(screen);var target=document.getElementById(screen+'Screen')||document.getElementById('dashboardScreen');if(!target)return;ensurePublicAssetsMobileNavItem();if(typeof window.thon09ApplyModuleDisplayOrder==='function')window.thon09ApplyModuleDisplayOrder();syncCentralScreenState(screen,requested);document.querySelectorAll('.screen').forEach(function(el){el.classList.toggle('active',el===target);});document.querySelectorAll('.sidebar .nav-link').forEach(function(btn){btn.classList.toggle('active',btn.dataset.screen===screen||btn.dataset.screen===requested);btn.setAttribute('aria-current',(btn.dataset.screen===screen||btn.dataset.screen===requested)?'page':'false');});syncDashboardTree(screen,requested);document.querySelectorAll('.mobile-bottom-nav [data-mobile-screen]').forEach(function(btn){var active=btn.dataset.mobileScreen===screen||btn.dataset.mobileScreen===requested;btn.classList.toggle('active',active);btn.setAttribute('aria-current',active?'page':'false');if(active)centerMobileBottomNavItem(btn);});var label=labels[screen]||labels[requested]||'Dashboard';var title=document.getElementById('screenTitle');if(title)title.textContent=label;var breadcrumb=document.getElementById('breadcrumbTrail');if(breadcrumb)breadcrumb.textContent='Trang chủ / '+label;}
    function navigateToScreen(screen,event){screen=normalizeScreen(screen);if(!screen)return;if(event&&typeof event.preventDefault==='function')event.preventDefault();if(event&&typeof event.stopPropagation==='function')event.stopPropagation();window.switchScreen(screen);}
    window.thon09NavigateToScreen=navigateToScreen;
    window.switchScreen=function(screen){var requested=screen;var normalized=normalizeScreen(screen);var ranOriginal=false;if(originalSwitchScreen&&originalSwitchScreen!==window.switchScreen){try{originalSwitchScreen(normalized);ranOriginal=true;}catch(error){console.error('switchScreen original failed',error);}}if(!ranOriginal)repairScreenState(normalized,requested);setTimeout(function(){repairScreenState(normalized,requested);},0);};
    document.addEventListener('click',function(event){var toggle=event.target.closest&&event.target.closest('[data-dashboard-toggle]');if(toggle){event.preventDefault();var tree=toggle.closest('[data-dashboard-tree]');setDashboardTreeOpen(!(tree&&tree.classList.contains('is-open')),true);return;}var desktopButton=event.target.closest&&event.target.closest('.sidebar .nav-link[data-screen]');if(desktopButton&&!desktopButton.classList.contains('gov-logout')){navigateToScreen(desktopButton.dataset.screen,event);return;}var mobileButton=event.target.closest&&event.target.closest('.mobile-bottom-nav [data-mobile-screen]');if(mobileButton){navigateToScreen(mobileButton.dataset.mobileScreen,event);}},true);
    setTimeout(function(){var screen='dashboard';try{screen=localStorage.getItem('thon09_screen')||'dashboard';}catch(error){}repairScreenState(screen,'');},0);
  }

  function navigationControllerModule(){
    var labels={dashboard:'Dashboard',dashboardHouseholds:'Dashboard Há»™ dÃ¢n',dashboardPopulation:'Dashboard NhÃ¢n kháº©u',dashboardBusiness:'Dashboard Kinh doanh',dashboardVehicles:'Dashboard Xe cá»™',dashboardLivestock:'Dashboard ChÄƒn nuÃ´i',dashboardGis:'Dashboard GIS',dashboardReports:'Dashboard BÃ¡o cÃ¡o',operationCenter:'Trung tÃ¢m Ä‘iá»u hÃ nh',gis:'Báº£n Ä‘á»“ Ä‘á»‹a bÃ n',businessHouseholds:'Há»™ sáº£n xuáº¥t & kinh doanh',vehicles:'Quáº£n lÃ½ xe cá»™',livestock:'Quáº£n lÃ½ váº­t nuÃ´i',agriculture:'Sáº£n xuáº¥t nÃ´ng nghiá»‡p',contributions:'ÄÃ³ng gÃ³p há»™',publicAssets:'CÃ´ng trÃ¬nh cÃ´ng cá»™ng',households:'Quáº£n lÃ½ há»™ gia Ä‘Ã¬nh',persons:'Quáº£n lÃ½ nhÃ¢n kháº©u',reports:'BÃ¡o cÃ¡o thá»‘ng kÃª',temporaryResidence:'Táº¡m trÃº',temporaryAbsence:'Táº¡m váº¯ng',movements:'Biáº¿n Ä‘á»™ng nhÃ¢n kháº©u',import:'Import dá»¯ liá»‡u',export:'Xuáº¥t Excel',exportExcel:'Xuáº¥t Excel',printForms:'In biá»ƒu máº«u',users:'Quáº£n lÃ½ tÃ i khoáº£n',permissions:'PhÃ¢n quyá»n',logs:'Nháº­t kÃ½ há»‡ thá»‘ng',backups:'Sao lÆ°u dá»¯ liá»‡u',restore:'KhÃ´i phá»¥c dá»¯ liá»‡u',settings:'Cáº¥u hÃ¬nh há»‡ thá»‘ng',appearance:'Cáº¥u hÃ¬nh giao diá»‡n'};
    labels={dashboard:'Dashboard',dashboardHouseholds:'Dashboard H\u1ed9 d\u00e2n',dashboardPopulation:'Dashboard Nh\u00e2n kh\u1ea9u',dashboardBusiness:'Dashboard Kinh doanh',dashboardVehicles:'Dashboard Xe c\u1ed9',dashboardLivestock:'Dashboard Ch\u0103n nu\u00f4i',dashboardGis:'Dashboard GIS',dashboardReports:'Dashboard B\u00e1o c\u00e1o',operationCenter:'Trung t\u00e2m \u0111i\u1ec1u h\u00e0nh',gis:'B\u1ea3n \u0111\u1ed3 \u0111\u1ecba b\u00e0n',businessHouseholds:'H\u1ed9 s\u1ea3n xu\u1ea5t & kinh doanh',vehicles:'Qu\u1ea3n l\u00fd xe c\u1ed9',livestock:'Qu\u1ea3n l\u00fd v\u1eadt nu\u00f4i',agriculture:'S\u1ea3n xu\u1ea5t n\u00f4ng nghi\u1ec7p',contributions:'\u0110\u00f3ng g\u00f3p h\u1ed9',publicAssets:'C\u00f4ng tr\u00ecnh c\u00f4ng c\u1ed9ng',households:'Qu\u1ea3n l\u00fd h\u1ed9 gia \u0111\u00ecnh',persons:'Qu\u1ea3n l\u00fd nh\u00e2n kh\u1ea9u',reports:'B\u00e1o c\u00e1o th\u1ed1ng k\u00ea',temporaryResidence:'T\u1ea1m tr\u00fa',temporaryAbsence:'T\u1ea1m v\u1eafng',movements:'Bi\u1ebfn \u0111\u1ed9ng nh\u00e2n kh\u1ea9u',import:'Import d\u1eef li\u1ec7u',export:'Xu\u1ea5t Excel',exportExcel:'Xu\u1ea5t Excel',printForms:'In bi\u1ec3u m\u1eabu',users:'Qu\u1ea3n l\u00fd t\u00e0i kho\u1ea3n',permissions:'Ph\u00e2n quy\u1ec1n',logs:'Nh\u1eadt k\u00fd h\u1ec7 th\u1ed1ng',backups:'Sao l\u01b0u d\u1eef li\u1ec7u',restore:'Kh\u00f4i ph\u1ee5c d\u1eef li\u1ec7u',settings:'C\u1ea5u h\u00ecnh h\u1ec7 th\u1ed1ng',appearance:'C\u1ea5u h\u00ecnh giao di\u1ec7n'};
    var dashboardScreens={dashboard:true,dashboardHouseholds:true,dashboardPopulation:true,dashboardBusiness:true,dashboardVehicles:true,dashboardLivestock:true,dashboardGis:true,dashboardReports:true};
    var loaderNames={dashboard:'loadDashboard',households:'loadHouseholds',businessHouseholds:'loadHouseholdBusiness',persons:'loadPersons',operationCenter:'loadOperationCenter',publicAssets:'loadPublicAssets',livestock:'loadLivestock',agriculture:'loadAgriculture',houses:'loadHouses'};
    var previousSwitchScreen=typeof window.switchScreen==='function'?window.switchScreen:null;
    var inController=false;
    var log=[];
    function normalize(screen){return screen==='export'?'exportExcel':(screen||'dashboard');}
    function targetFor(screen){return document.getElementById(screen+'Screen')||document.getElementById('dashboardScreen');}
    function domState(){
      var rows=Array.prototype.slice.call(document.querySelectorAll('.screen')).map(function(el){
        var style=getComputedStyle(el),rect=el.getBoundingClientRect();
        return {id:el.id,active:el.classList.contains('active'),inlineDisplay:el.style.display||'',computedDisplay:style.display,visibility:style.visibility,zIndex:style.zIndex,width:Math.round(rect.width),height:Math.round(rect.height)};
      });
      var visible=rows.filter(function(row){return row.computedDisplay!=='none'&&row.visibility!=='hidden'&&row.width>0&&row.height>0;});
      var top=visible.map(function(row){return {id:row.id,z:Number.parseInt(row.zIndex,10)||0};}).sort(function(a,b){return b.z-a.z;})[0]||null;
      return {screens:rows,activeScreens:rows.filter(function(row){return row.active;}),visibleScreens:visible,displayBlockCount:rows.filter(function(row){return row.computedDisplay==='block';}).length,highestZIndexScreen:top};
    }
    function writeLog(step,data){
      var entry=Object.assign({step:step,time:Date.now()},data||{});
      log.push(entry);
      window.__thon09NavigationLog=log.slice();
      if(window.THON09_NAV_DEBUG)console.debug('[NavigationController]',step,entry);
    }
    function centerMobile(button){
      if(!button)return;
      try{button.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});}catch(error){}
    }
    function setDashboardTree(screen,requested){
      var active=!!(dashboardScreens[screen]||dashboardScreens[requested]);
      var tree=document.querySelector('[data-dashboard-tree]');
      if(tree)tree.classList.toggle('is-active',active);
      var toggle=tree&&tree.querySelector('[data-dashboard-toggle]');
      if(toggle)toggle.setAttribute('aria-expanded',active?'true':'false');
      if(tree)tree.classList.toggle('is-open',active);
      document.querySelectorAll('[data-dashboard-tree] .dashboard-tree-link[data-screen]').forEach(function(btn){
        var current=btn.dataset.screen===screen||btn.dataset.screen===requested;
        btn.classList.toggle('active',current);
        btn.setAttribute('aria-current',current?'page':'false');
      });
    }
    function setActiveScreen(screen,requested){
      var previous=window.App&&window.App.screen;
      if(window.App)window.App.screen=screen;
      try{localStorage.setItem('thon09_screen',screen);}catch(error){}
      writeLog('setActiveScreen',{previousScreen:previous,currentScreen:screen,requestedScreen:requested});
      try{document.dispatchEvent(new CustomEvent('thon09:screen-change',{detail:{screen:screen,requestedScreen:requested}}));}catch(error){}
    }
    function hideOtherScreens(target){
      document.querySelectorAll('.screen').forEach(function(el){
        var active=el===target;
        el.classList.toggle('active',active);
        el.style.display=active?'block':'none';
        el.setAttribute('aria-hidden',active?'false':'true');
      });
      writeLog('hideOtherScreens',domState());
    }
    function syncActiveNav(screen,requested){
      document.querySelectorAll('.sidebar .nav-link[data-screen]').forEach(function(btn){
        var active=btn.dataset.screen===screen||btn.dataset.screen===requested;
        btn.classList.toggle('active',active);
        btn.setAttribute('aria-current',active?'page':'false');
      });
      setDashboardTree(screen,requested);
      document.querySelectorAll('.mobile-bottom-nav [data-mobile-screen]').forEach(function(btn){
        var active=btn.dataset.mobileScreen===screen||btn.dataset.mobileScreen===requested;
        btn.classList.toggle('active',active);
        btn.setAttribute('aria-current',active?'page':'false');
        if(active)centerMobile(btn);
      });
    }
    function render(screen){
      if(screen==='gis'&&typeof window.ensureGisAssets==='function'&&typeof window.loadGisMap==='function'){
        window.ensureGisAssets().then(function(){window.loadGisMap();}).catch(function(error){if(typeof window.showToast==='function')window.showToast('KhÃ´ng táº£i Ä‘Æ°á»£c thÆ° viá»‡n báº£n Ä‘á»“: '+error.message,'danger');});
        writeLog('render',{screen:screen,loader:'ensureGisAssets/loadGisMap'});
        return;
      }
      var loader=loaderNames[screen];
      if(loader&&typeof window[loader]==='function'){setTimeout(function(){window[loader]();},0);writeLog('render',{screen:screen,loader:loader});}
      else writeLog('render',{screen:screen,loader:''});
    }
    function showScreen(screen,requested){
      screen=normalize(screen);
      var target=targetFor(screen);
      if(!target){writeLog('showScreen',{screen:screen,found:false});return false;}
      if(typeof window.thon09ApplyModuleDisplayOrder==='function')window.thon09ApplyModuleDisplayOrder();
      setActiveScreen(screen,requested);
      hideOtherScreens(target);
      syncActiveNav(screen,requested);
      var label=labels[screen]||labels[requested]||'Dashboard';
      var title=document.getElementById('screenTitle');
      var breadcrumb=document.getElementById('breadcrumbTrail');
      if(title)title.textContent=label;
      if(breadcrumb)breadcrumb.textContent='Trang ch\u1ee7 / '+label;
      document.body.classList.remove('sidebar-open');
      var sidebar=document.querySelector('.sidebar');
      if(sidebar)sidebar.classList.remove('open');
      render(screen);
      writeLog('showScreen',Object.assign({screen:screen,targetScreen:target.id,currentScreen:window.App&&window.App.screen},domState()));
      return true;
    }
    function switchScreen(screen){
      if(inController)return showScreen(screen,screen);
      log=[];
      var requested=screen;
      var normalized=normalize(screen);
      if(!targetFor(normalized))normalized='dashboard';
      writeLog('Click menu',{menuKey:requested,moduleKey:normalized,targetScreen:normalized+'Screen',currentScreen:window.App&&window.App.screen});
      inController=true;
      try{
        if(previousSwitchScreen&&previousSwitchScreen!==switchScreen)previousSwitchScreen(normalized);
      }catch(error){
        console.error('switchScreen original failed',error);
        writeLog('switchScreen original failed',{message:error&&error.message});
      }finally{
        inController=false;
      }
      writeLog('switchScreen',{requestedScreen:requested,screen:normalized});
      showScreen(normalized,requested);
      setTimeout(function(){showScreen(normalized,requested);},0);
    }
    function navigate(screen,event){
      screen=normalize(screen);
      if(event&&typeof event.preventDefault==='function')event.preventDefault();
      if(event&&typeof event.stopPropagation==='function')event.stopPropagation();
      switchScreen(screen);
      return true;
    }
    window.Thon09NavigationController={navigate:navigate,switchScreen:switchScreen,showScreen:showScreen,hideOtherScreens:hideOtherScreens,render:render,inspect:domState,getLog:function(){return log.slice();}};
    window.thon09NavigateToScreen=navigate;
    window.switchScreen=switchScreen;
    document.addEventListener('click',function(event){
      var toggle=event.target.closest&&event.target.closest('[data-dashboard-toggle]');
      if(toggle)return;
      var desktop=event.target.closest&&event.target.closest('.sidebar .nav-link[data-screen]');
      if(desktop&&!desktop.classList.contains('gov-logout')){navigate(desktop.dataset.screen,event);return;}
      var mobile=event.target.closest&&event.target.closest('.mobile-bottom-nav [data-mobile-screen]');
      if(mobile)navigate(mobile.dataset.mobileScreen,event);
    },true);
    setTimeout(function(){var screen='dashboard';try{screen=localStorage.getItem('thon09_screen')||'dashboard';}catch(error){}showScreen(screen,'');},0);
  }

  function accessibilityRepairModule(){
    var form=document.getElementById('publicAssetForm');
    var gps=form&&form.elements&&form.elements.gps_accuracy;
    if(gps){
      gps.id=gps.id||'publicAssetGpsAccuracyInput';
      gps.setAttribute('aria-label','Sai số GPS (m)');
    }
  }

  function commonModalModule(){
    function normalize(){
      document.querySelectorAll('.modal').forEach(function(modal){
        modal.classList.add('common-modal');
        modal.dataset.commonModal='true';
        modal.setAttribute('role',modal.getAttribute('role')||'dialog');
        modal.setAttribute('aria-modal','true');
        var dialog=modal.querySelector('.modal-dialog');
        if(dialog)dialog.classList.add('modal-dialog-scrollable');
        var content=modal.querySelector('.modal-content');
        if(content)content.classList.add('common-modal-content');
      });
    }
    normalize();
    if(window.MutationObserver){
      var observer=new MutationObserver(function(records){
        var shouldNormalize=records.some(function(record){
          return Array.prototype.some.call(record.addedNodes||[],function(node){
            return node.nodeType===1&&((node.matches&&node.matches('.modal'))||(node.querySelector&&node.querySelector('.modal')));
          });
        });
        if(shouldNormalize)normalize();
      });
      observer.observe(document.body,{childList:true,subtree:true});
    }
  }

  try{reportModule();}catch(error){console.error('report inline module failed',error);}
  try{personFilterModule();}catch(error){console.error('person filter module failed',error);}
  try{moduleDisplayOrderModule();}catch(error){console.error('module display order module failed',error);}
  try{headerGuardModule();}catch(error){console.error('header guard module failed',error);}
  try{navigationRepairModule();}catch(error){console.error('navigation repair module failed',error);}
  try{navigationControllerModule();}catch(error){console.error('navigation controller module failed',error);}
  try{accessibilityRepairModule();}catch(error){console.error('accessibility repair module failed',error);}
  try{commonModalModule();}catch(error){console.error('common modal module failed',error);}
})();
