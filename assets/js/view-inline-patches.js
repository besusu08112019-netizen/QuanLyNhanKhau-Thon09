(function(){
'use strict';
function reportModule(){
var currentReport = null;
var timeOptionalTypes = new Set(['summary','population','citizen','household','party_member','party','youth_union_member','meritorious_person','disabled_person','disability','age','gender','labor','elderly','children','poor-households','poor','near-poor-households','near_poor','health-insurance-area','health-insurance-household','health-insurance-expired','health-insurance-expiring','health-insurance-missing']);
function q(s){return document.querySelector(s);}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
function token(){return localStorage.getItem(tenantStorageKey('token'))||(window.App&&window.App.token)||'';}
function buildParams(){var form=q('#reportForm');var params=new URLSearchParams();if(!form)return params;var data=new FormData(form);data.forEach(function(value,key){if(value==null)return;var text=String(value).trim();if(text!=='')params.set(key,text);});var type=params.get('type')||params.get('report_type')||'summary';params.set('type',type);params.set('report_type',type);return params;}
function reportType(){return buildParams().get('type')||'summary';}
function apiUrl(path){var params=buildParams();return path+(params.toString()?'?'+params.toString():'');}
async function fetchJson(path){var tk=token();if(!tk)throw new Error('PhiÃªn Ä‘Äƒng nháº­p Ä‘Ã£ háº¿t háº¡n. Vui lÃ²ng Ä‘Äƒng nháº­p láº¡i.');var res=await fetch(path,{headers:{Accept:'application/json',Authorization:'Bearer '+tk},cache:'no-store'});var json=await res.json().catch(function(){return null;});if(!res.ok||!json||!json.ok)throw new Error((json&&json.error&&json.error.message)||'KhÃ´ng táº£i Ä‘Æ°á»£c bÃ¡o cÃ¡o.');return json.data||{};}
function setTitle(text){var el=q('#reportTitle');if(el)el.textContent=text||'BÃ¡o cÃ¡o';}
function setCount(report){var el=q('#reportCount');if(!el)return;var rows=Number(report&&report.totalRows!=null?report.totalRows:(report&&report.rows?report.rows.length:0));el.textContent='Tá»•ng sá»‘: '+rows.toLocaleString('vi-VN')+' dÃ²ng';}
function setActions(show){var el=q('#reportActions');if(el)el.classList.toggle('d-none',!show);}
function table(report){var headers=report.headers||[];var rows=report.rows||[];if(!headers.length)return '<div class="report-empty-state">BÃ¡o cÃ¡o chÆ°a cÃ³ cáº¥u trÃºc hiá»ƒn thá»‹.</div>';var head=headers.map(function(h){return '<th>'+esc(h)+'</th>';}).join('');var body=rows.length?rows.map(function(row){return '<tr>'+row.map(function(cell){return '<td>'+esc(cell)+'</td>';}).join('')+'</tr>';}).join(''):'<tr><td colspan="'+headers.length+'" class="text-center text-muted py-4">KhÃ´ng cÃ³ dá»¯ liá»‡u</td></tr>';return '<table class="table report-table align-middle mb-0"><thead><tr>'+head+'</tr></thead><tbody>'+body+'</tbody></table>';}
function showMessage(text,type){var box=q('#reportPreview');if(box)box.innerHTML='<div class="alert alert-'+(type||'info')+' mb-0">'+esc(text)+'</div>';}
async function viewReport(){setActions(false);setTitle('BÃ¡o cÃ¡o');var count=q('#reportCount');if(count)count.textContent='Äang táº£i dá»¯ liá»‡u...';showMessage('Äang sinh bÃ¡o cÃ¡o...','info');try{var report=await fetchJson(apiUrl('/api/reports/summary'));currentReport=report;setTitle(report.title||'BÃ¡o cÃ¡o');setCount(report);var preview=q('#reportPreview');if(preview)preview.innerHTML=table(report);setActions(true);return report;}catch(e){currentReport=null;setTitle('BÃ¡o cÃ¡o');if(count)count.textContent='KhÃ´ng sinh Ä‘Æ°á»£c bÃ¡o cÃ¡o';showMessage(e.message||'KhÃ´ng sinh Ä‘Æ°á»£c bÃ¡o cÃ¡o.','danger');throw e;}}
async function ensureReport(){return currentReport||viewReport();}
function download(kind){var tk=token();if(!tk){showMessage('PhiÃªn Ä‘Äƒng nháº­p Ä‘Ã£ háº¿t háº¡n. Vui lÃ²ng Ä‘Äƒng nháº­p láº¡i.','danger');return;}var url=apiUrl(kind==='excel'?'/api/reports/export-excel':'/api/reports/export-pdf');fetch(url,{headers:{Authorization:'Bearer '+tk},cache:'no-store'}).then(function(res){if(!res.ok)throw new Error('KhÃ´ng xuáº¥t Ä‘Æ°á»£c file.');return res.blob().then(function(blob){var name=(currentReport&&currentReport.title?currentReport.title:'bao_cao').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/d/g,'d').replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'')||'bao_cao';var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name+(kind==='excel'?'.xls':'.pdf');document.body.appendChild(a);a.click();URL.revokeObjectURL(a.href);a.remove();});}).catch(function(e){showMessage(e.message||'KhÃ´ng xuáº¥t Ä‘Æ°á»£c file.','danger');});}
async function printReport(){
try{
var report=await ensureReport();
var printData=await fetchJson(apiUrl('/api/reports/print')).catch(function(){return report;});
if(!window.TenantAppPrint){showMessage('Print Framework is not ready','warning');return;}
var popup=window.TenantAppPrint.render({
  title: printData.title||report.title||'Bo co',
  type: reportType(),
  paperSize: 'A4',
  headers: printData.headers||report.headers||[],
  rows: printData.rows||report.rows||[],
  totalRows: printData.totalRows||report.totalRows,
  filters: printData.filters||Object.fromEntries(buildParams().entries()),
  meta: printData.meta||report.meta||{},
  repeatHeader: true,
  showFooter: true,
  showSummary: true,
  showSignature: true
});
if(!popup)showMessage('TrÃ¬nh duyá»‡t Ä‘ang cháº·n cá»­a sá»• in. Vui lÃ²ng cho phÃ©p popup.','warning');
}catch(e){showMessage(e.message||'KhÃ´ng in Ä‘Æ°á»£c bÃ¡o cÃ¡o.','danger');}
}
function lockReportTypes(){var select=q('#reportTypeSelect');if(!select)return;var value=select.value||'summary';var options=[['summary','BÃ¡o cÃ¡o tá»•ng há»£p'],['population','BÃ¡o cÃ¡o nhÃ¢n kháº©u'],['household','BÃ¡o cÃ¡o há»™ gia Ä‘Ã¬nh'],['settled-elsewhere-households','BÃ¡o cÃ¡o há»™ dÃ¢n sinh sá»‘ng á»•n Ä‘á»‹nh á»Ÿ nÆ¡i khÃ¡c'],['public-assets','CÃ´ng trÃ¬nh cÃ´ng cá»™ng - Danh sÃ¡ch'],['public-assets-located','CÃ´ng trÃ¬nh cÃ´ng cá»™ng - ÄÃ£ cÃ³ GPS'],['public-assets-missing-gps','CÃ´ng trÃ¬nh cÃ´ng cá»™ng - ChÆ°a cÃ³ GPS'],['public-assets-inventory','CÃ´ng trÃ¬nh cÃ´ng cá»™ng - Kiá»ƒm kÃª tÃ i sáº£n'],['houses','NhÃ  á»Ÿ vÃ  cÃ´ng trÃ¬nh - Danh sÃ¡ch'],['houses-degraded','NhÃ  á»Ÿ xuá»‘ng cáº¥p'],['houses-temporary','NhÃ  táº¡m'],['houses-fire-risk','NhÃ  nguy cÆ¡ PCCC'],['houses-missing-gps','NhÃ  chÆ°a cÃ³ GPS'],['household-business-production','Há»™ sáº£n xuáº¥t'],['household-business-trade','Há»™ kinh doanh'],['household-business-sector','Há»™ SXKD theo ngÃ nh nghá»'],['household-business-status','Há»™ SXKD theo tráº¡ng thÃ¡i'],['agriculture','Sáº£n xuáº¥t nÃ´ng nghiá»‡p - Danh sÃ¡ch'],['agriculture-producers','Chá»§ thá»ƒ sáº£n xuáº¥t nÃ´ng nghiá»‡p'],['agriculture-area','Diá»‡n tÃ­ch sáº£n xuáº¥t nÃ´ng nghiá»‡p'],['agriculture-crop','CÃ¢y trá»“ng'],['agriculture-season','MÃ¹a vá»¥'],['agriculture-production','Sáº£n lÆ°á»£ng nÃ´ng nghiá»‡p'],['agriculture-damage','Thiá»‡t háº¡i nÃ´ng nghiá»‡p'],['livestock','Váº­t nuÃ´i - Danh sÃ¡ch'],['livestock-by-type','Váº­t nuÃ´i theo loáº¡i'],['livestock-vaccinated','Váº­t nuÃ´i Ä‘Ã£ tiÃªm phÃ²ng'],['livestock-unvaccinated','Váº­t nuÃ´i chÆ°a tiÃªm phÃ²ng'],['livestock-disease','Váº­t nuÃ´i cÃ³ dá»‹ch bá»‡nh'],['livestock-pig-farms','Danh sÃ¡ch trang tráº¡i lá»£n'],['livestock-pig-sow','Danh sÃ¡ch há»™ nuÃ´i lá»£n nÃ¡i'],['livestock-pig-meat','Danh sÃ¡ch há»™ nuÃ´i lá»£n thá»‹t'],['livestock-pig-sow-and-meat','Há»™ vá»«a nuÃ´i lá»£n nÃ¡i vá»«a nuÃ´i lá»£n thá»‹t'],['vehicles','Xe cá»™ - Danh sÃ¡ch'],['vehicles-by-type','Xe cá»™ theo loáº¡i'],['vehicles-missing-plate','Xe chÆ°a cÃ³ biá»ƒn sá»‘'],['vehicles-expired-inspection','Xe háº¿t háº¡n kiá»ƒm Ä‘á»‹nh'],['vehicles-expired-insurance','Xe háº¿t háº¡n báº£o hiá»ƒm'],['contributions-list','ÄÃ³ng gÃ³p há»™ - Danh sÃ¡ch'],['contributions-collection','ÄÃ³ng gÃ³p há»™ - Thu tiá»n'],['contributions-unpaid-list','ÄÃ³ng gÃ³p há»™ - ChÆ°a ná»™p'],['contributions-partial','ÄÃ³ng gÃ³p há»™ - Ná»™p má»™t pháº§n'],['contributions-exempt','ÄÃ³ng gÃ³p há»™ - Miá»…n giáº£m'],['contributions-summary','ÄÃ³ng gÃ³p há»™ - Tá»•ng há»£p'],['contributions-year-summary','ÄÃ³ng gÃ³p há»™ - Tá»•ng há»£p nÄƒm'],['contributions-by-contribution','ÄÃ³ng gÃ³p há»™ - Theo khoáº£n thu'],['gis','GIS - Há»™ gia Ä‘Ã¬nh'],['gis-located','GIS - ÄÃ£ Ä‘á»‹nh vá»‹'],['gis-unlocated','GIS - ChÆ°a Ä‘á»‹nh vá»‹'],['digital-profile','Há»“ sÆ¡ sá»‘'],['profile-complete','Há»“ sÆ¡ hoÃ n chá»‰nh'],['profile-missing-photo','Há»“ sÆ¡ thiáº¿u áº£nh'],['profile-missing-documents','Há»“ sÆ¡ thiáº¿u giáº¥y tá»'],['profile-incomplete','Há»“ sÆ¡ chÆ°a hoÃ n thiá»‡n'],['temporary_residence','BÃ¡o cÃ¡o táº¡m trÃº'],['temporary_absence','BÃ¡o cÃ¡o táº¡m váº¯ng'],['migration','BÃ¡o cÃ¡o biáº¿n Ä‘á»™ng'],['health_insurance','BÃ¡o cÃ¡o Báº£o hiá»ƒm y táº¿'],['health-insurance-missing','Danh sÃ¡ch chÆ°a tham gia BHYT'],['health-insurance-expiring','Danh sÃ¡ch BHYT sáº¯p háº¿t háº¡n (30 ngay)'],['health-insurance-expired','Danh sÃ¡ch BHYT Ä‘Ã£ háº¿t háº¡n'],['health-insurance-household','Thá»‘ng kÃª BHYT theo há»™'],['health-insurance-area','Thá»‘ng kÃª BHYT theo khu vá»±c'],['party_member','BÃ¡o cÃ¡o Äáº£ng viÃªn'],['meritorious_person','BÃ¡o cÃ¡o ngÆ°á»i cÃ³ cÃ´ng'],['disabled_person','BÃ¡o cÃ¡o ngÆ°á»i khuyáº¿t táº­t'],['age','BÃ¡o cÃ¡o theo Ä‘á»™ tuá»•i'],['gender','BÃ¡o cÃ¡o theo giá»›i tÃ­nh'],['youth_union_member','BÃ¡o cÃ¡o ÄoÃ n viÃªn'],['poor-households','BÃ¡o cÃ¡o há»™ nghÃ¨o'],['near-poor-households','BÃ¡o cÃ¡o há»™ cáº­n nghÃ¨o'],['labor','BÃ¡o cÃ¡o lao Ä‘á»™ng'],['elderly','BÃ¡o cÃ¡o ngÆ°á»i cao tuá»•i'],['children','BÃ¡o cÃ¡o tráº» em']];var html=options.map(function(item){return '<option value="'+item[0]+'">'+item[1]+'</option>';}).join('');if(select.innerHTML.replace(/\s+/g,' ').trim()!==html.replace(/\s+/g,' ').trim())select.innerHTML=html;if(!Array.prototype.some.call(select.options,function(o){return o.value===value;}))value='summary';select.value=value;}
function updateDateVisibility(){lockReportTypes();var type=reportType();var hide=timeOptionalTypes.has(type);document.querySelectorAll('[data-report-date-field]').forEach(function(el){el.classList.toggle('report-date-muted',hide);});}
function bind(){lockReportTypes();if(window.__TenantAppReportReadyV2)return;window.__TenantAppReportReadyV2=true;var form=q('#reportForm');if(form){form.addEventListener('submit',function(e){e.preventDefault();viewReport();});form.addEventListener('change',function(e){if(e.target&&e.target.name==='type'){currentReport=null;setActions(false);updateDateVisibility();}});}updateDateVisibility();}
window.TenantAppViewReport=function(){bind();return viewReport();};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bind);else bind();
setTimeout(lockReportTypes,0);
}
function personFilterModule(){
function qs(s,r){return (r||document).querySelector(s);} function qsa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}
function fillSelects(){qsa('[data-dictionary]').forEach(function(el){var list=(window.App&&App.dictionaries&&App.dictionaries[el.dataset.dictionary])||[];var current=el.value;el.innerHTML='<option value="">Tat ca</option>'+list.map(function(item){return '<option value="'+escapeHtml(item)+'">'+escapeHtml(item)+'</option>';}).join('');el.value=current||'';});}
function applyResidence(p,value){if(value==='PERMANENT'||value==='TEMPORARY')p.set('residencyStatus',value);else if(value==='AWAY')p.set('presenceStatus','AWAY');}
function applyAgeGroup(p,value){if(value==='0_5'){p.set('ageFrom','0');p.set('ageTo','5');}else if(value==='6_14'){p.set('ageFrom','6');p.set('ageTo','14');}else if(value==='15_17'){p.set('ageFrom','15');p.set('ageTo','17');}else if(value==='18_59'){p.set('ageFrom','18');p.set('ageTo','59');}else if(value==='60_plus'){p.set('ageFrom','60');}}
function appendFilter(p,key,value){if(!value)return;if(key==='residenceCombined')applyResidence(p,value);else if(key==='ageGroup')applyAgeGroup(p,value);else p.set(key,value);}
function personParams(includeSearch){var p=new URLSearchParams({page:App.persons.page||1,pageSize:App.persons.pageSize||20});if(includeSearch){var search=(qs('#personSearch')&&qs('#personSearch').value||App.persons.search||'').trim();if(search)p.set('search',search);}qsa('[data-person-filter]').forEach(function(el){var key=el.dataset.personFilter,val=String(el.value||'').trim();App.persons[key]=val;appendFilter(p,key,val);});return p;}
function activeFilterParams(){var p=personParams(false);p.delete('page');p.delete('pageSize');return Object.fromEntries(p.entries());}
function matchesQuickSearch(row,searchText){return [row.full_name,row.citizen_code,row.identity_number].some(function(value){return normalizeSearchText(value).includes(searchText);});}
function canPersonAction(action){var role=(App&&App.user&&App.user.role||'').toUpperCase();if(role==='SUPER_ADMIN'||role==='ADMIN')return true;if(typeof TenantAppCanAccess==='function'&&(TenantAppCanAccess('citizen',action)||TenantAppCanAccess('persons',action)))return true;var permissions=App&&App.user&&App.user.permissions||{};return Boolean((permissions.citizen&&permissions.citizen[action])||(permissions.persons&&permissions.persons[action]));}
function ensurePersonActionButtons(){var rows=qs('#personRows');if(!rows)return;var canEdit=canPersonAction('update'),canDelete=canPersonAction('delete');qsa('td.text-end [data-platform-action="persons.detail"]',rows).forEach(function(viewBtn){var id=viewBtn.dataset.id||viewBtn.dataset.personId||'';var cell=viewBtn.closest('td');if(!id||!cell)return;if(canEdit&&!cell.querySelector('[data-platform-action="persons.edit"]'))viewBtn.insertAdjacentHTML('afterend',' <button class="btn btn-sm person-row-btn person-row-edit" type="button" data-platform-action="persons.edit" data-id="'+escapeHtml(id)+'">Sá»­a</button>');if(canDelete&&!cell.querySelector('[data-platform-action="persons.delete"]'))cell.insertAdjacentHTML('beforeend',' <button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="persons.delete" data-id="'+escapeHtml(id)+'">XÃ³a</button>');});}
window.loadPersons=async function loadPersonsAdvanced(){try{var searchText=normalizeSearchText((qs('#personSearch')&&qs('#personSearch').value||App.persons.search||'').trim());App.persons.search=(qs('#personSearch')&&qs('#personSearch').value||'').trim();var items=[],total=0;var data=await api('/api/persons?'+personParams(true).toString(),{cacheTtl:12000});items=data.items||[];total=data.total||0;var totalEl=qs('#personTotalCount');if(totalEl)totalEl.innerHTML='Tá»•ng sá»‘: <strong>'+number(total)+'</strong> nhÃ¢n kháº©u';var rows=qs('#personRows');if(rows){rows.innerHTML=renderPersonRows(items);ensurePersonActionButtons();}if(typeof refreshUiEnhancements==='function')refreshUiEnhancements(qs('#personsScreen')||document);if(typeof updateBulkDeleteButtons==='function')updateBulkDeleteButtons();renderPager('#personPager',{total:total,page:App.persons.page,pageSize:App.persons.pageSize},function(page){App.persons.page=page;window.loadPersons();});}catch(error){showToast('KhÃ´ng táº£i Ä‘Æ°á»£c danh sÃ¡ch nhÃ¢n kháº©u: '+error.message,'danger');}};
function advancedFilterElements(){return {toggle:qs('#personAdvancedToggle'),panel:qs('#personAdvancedFilters')};}
function setAdvancedFilterOpen(open){var els=advancedFilterElements(),toggle=els.toggle,panel=els.panel;if(!toggle||!panel)return;panel.classList.toggle('d-none',!open);toggle.setAttribute('aria-expanded',open?'true':'false');toggle.innerHTML='<i class="fa-solid fa-sliders"></i> '+(open?'An bo loc nang cao':'Bo loc nang cao');}
function reloadPersonsFromStart(){App.persons.page=1;window.loadPersons();}
function clearAdvancedFilters(){qsa('#personAdvancedFilters [data-person-filter]').forEach(function(el){el.value='';App.persons[el.dataset.personFilter]='';});reloadPersonsFromStart();}
function resetPersonFilters(){var search=qs('#personSearch');if(search)search.value='';qsa('[data-person-filter]').forEach(function(el){el.value='';App.persons[el.dataset.personFilter]='';});App.persons.search='';setAdvancedFilterOpen(false);reloadPersonsFromStart();}
function preparePersonFilterActions(){[['#personAdvancedToggle','personFilters.toggleAdvanced'],['#personAdvancedApply','personFilters.applyAdvanced'],['#personAdvancedClear','personFilters.clearAdvanced'],['#personFilterReset','personFilters.reset']].forEach(function(item){var el=qs(item[0]);if(el&&!el.dataset.platformAction)el.dataset.platformAction=item[1];});}
function registerPersonFilterActions(){var actions=window.TenantAppPlatform&&window.TenantAppPlatform.actions;if(window.__TenantAppPersonFilterActionsRegistered||!actions||typeof actions.register!=='function')return;window.__TenantAppPersonFilterActionsRegistered=true;actions.register('personFilters.toggleAdvanced',function(){var panel=qs('#personAdvancedFilters');setAdvancedFilterOpen(panel&&panel.classList.contains('d-none'));}).register('personFilters.applyAdvanced',function(){setAdvancedFilterOpen(false);reloadPersonsFromStart();}).register('personFilters.clearAdvanced',clearAdvancedFilters).register('personFilters.reset',resetPersonFilters);}
function bind(){fillSelects();registerPersonFilterActions();preparePersonFilterActions();if(window.__TenantAppPersonAdvancedBound)return;window.__TenantAppPersonAdvancedBound=true;qsa('[data-person-filter]').forEach(function(el){el.addEventListener('change',reloadPersonsFromStart);el.addEventListener('input',debounce(reloadPersonsFromStart,350));});var search=qs('#personSearch');if(search)search.addEventListener('input',debounce(reloadPersonsFromStart,350));var pageSize=qs('#personPageSize');if(pageSize)pageSize.addEventListener('change',function(){App.persons.pageSize=Number(this.value||20);reloadPersonsFromStart();});}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bind);else bind();
}
function moduleDisplayOrderModule(){
var platform=window.TenantAppPlatform;
var orderedModules=(platform&&platform.menuRenderer&&platform.menuRenderer.mobileModules?platform.menuRenderer.mobileModules():[]).map(function(module){
return {screen:module.screenId,mobileLabel:module.mobileLabel||module.label,icon:module.icon};
});
var moduleScreens=orderedModules.map(function(item){return item.screen;});
var moduleRank=Object.create(null);
orderedModules.forEach(function(item,index){moduleRank[item.screen]=index;});
var dashboardMenu=platform&&platform.menus&&platform.menus.get?platform.menus.get('dashboard'):null;
var dashboardOrder=(dashboardMenu&&dashboardMenu.items||[]).map(function(moduleKey){
var module=platform.modules.get(moduleKey);
return module&&module.screenId;
}).filter(function(screen){return screen&&screen!=='dashboard';});
var dashboardRank=Object.create(null);
dashboardOrder.forEach(function(screen,index){dashboardRank[screen]=index;});
window.TenantAppModuleOrder=orderedModules.slice();
window.TenantAppModuleScreenOrder=moduleScreens.slice();
function syncAll(){}
window.TenantAppApplyModuleDisplayOrder=syncAll;
}
function headerGuardModule(){
function platformScreen(){var navigation=window.TenantAppPlatform&&window.TenantAppPlatform.navigation;try{var current=navigation&&navigation.current&&navigation.current();return current&&current.screenId||'';}catch(error){return '';}}
function activeScreen(){var active=document.querySelector('.screen.active');if(active&&active.id)return active.id.replace(/Screen$/,'');return platformScreen()||(window.App&&window.App.screen)||localStorage.getItem(tenantStorageKey('screen'))||'dashboard';}
function platformModule(screen){var platform=window.TenantAppPlatform;if(!platform||!platform.modules||!platform.modules.list)return null;return platform.modules.list().find(function(module){return module.screenId===screen||module.moduleKey===screen;})||null;}
function labelFor(screen){var module=platformModule(screen);return module&&module.label||'Dashboard';}
function cleanHeader(){var screen=activeScreen();var label=labelFor(screen);var title=document.querySelector('#screenTitle');var crumb=document.querySelector('#breadcrumbTrail');if(title)title.textContent=label;if(crumb)crumb.textContent='Trang ch\u1ee7 / '+label;document.querySelectorAll('.topbar-title-block small:not(#breadcrumbTrail), .topbar-title-block .text-muted:not(#breadcrumbTrail), .topbar > div:first-of-type small:not(#breadcrumbTrail), .topbar > div:first-of-type .text-muted:not(#breadcrumbTrail)').forEach(function(el){el.remove();});document.querySelectorAll('.dashboard-hero-row, .module-page-head > div, .person-page-head > div, .report-page-head, .screen > .admin-heading > div').forEach(function(el){if(el.querySelector&&el.querySelector('button, [data-platform-action], a.btn, .btn'))return;el.remove();});}
window.TenantAppCleanHeader=cleanHeader;document.addEventListener('DOMContentLoaded',cleanHeader);document.addEventListener('tenant:screen-change',function(){setTimeout(cleanHeader,0);});setTimeout(cleanHeader,120);setTimeout(cleanHeader,500);
}
function navigationControllerModule(){
var log=[];
var renderRetryTimers=Object.create(null);
var dynamicLoaderPromises=Object.create(null);
function platformModule(screen){var platform=window.TenantAppPlatform;if(!platform||!platform.modules||!platform.modules.list)return null;return platform.modules.list().find(function(module){return module.screenId===screen||module.moduleKey===screen;})||null;}
function platformScreen(){var navigation=window.TenantAppPlatform&&window.TenantAppPlatform.navigation;try{var current=navigation&&navigation.current&&navigation.current();return current&&current.screenId||'';}catch(error){return '';}}
function storedScreen(){try{return localStorage.getItem(tenantStorageKey('screen'))||'';}catch(error){return '';}}
function currentScreen(){return platformScreen()||(window.App&&window.App.screen)||storedScreen()||'dashboard';}
function initialScreen(){return platformScreen()||storedScreen()||(window.App&&window.App.screen)||'dashboard';}
function platformDashboardScreens(){var platform=window.TenantAppPlatform,menu=platform&&platform.menus&&platform.menus.get&&platform.menus.get('dashboard'),screens={};if(menu&&platform.modules&&platform.modules.get){(menu.items||[]).forEach(function(moduleKey){var module=platform.modules.get(moduleKey);if(module)screens[module.screenId]=true;});}return screens;}
function labelFor(screen,requested){var module=platformModule(screen)||platformModule(requested);return module&&module.label||'Dashboard';}
function loaderFor(screen){var module=platformModule(screen);return module&&module.loaderName||'';}
function normalize(screen){return screen==='export'?'exportExcel':(screen||'dashboard');}
function targetFor(screen){var target=document.getElementById(screen+'Screen');if(target)return target;return screen==='dashboard'?document.getElementById('dashboardScreen'):null;}
function domState(){
var rows=Array.prototype.slice.call(document.querySelectorAll('.screen')).map(function(el){var style=getComputedStyle(el),rect=el.getBoundingClientRect();return {id:el.id,active:el.classList.contains('active'),inlineDisplay:el.style.display||'',computedDisplay:style.display,visibility:style.visibility,zIndex:style.zIndex,width:Math.round(rect.width),height:Math.round(rect.height)};});
var visible=rows.filter(function(row){return row.computedDisplay!=='none'&&row.visibility!=='hidden'&&row.width>0&&row.height>0;});
var top=visible.map(function(row){return {id:row.id,z:Number.parseInt(row.zIndex,10)||0};}).sort(function(a,b){return b.z-a.z;})[0]||null;
return {screens:rows,activeScreens:rows.filter(function(row){return row.active;}),visibleScreens:visible,displayBlockCount:rows.filter(function(row){return row.computedDisplay==='block';}).length,highestZIndexScreen:top};
}
function writeLog(step,data){var entry=Object.assign({step:step,time:Date.now()},data||{});log.push(entry);window.__TenantAppNavigationLog=log.slice();if(window.TENANT_NAV_DEBUG)console.debug('[NavigationController]',step,entry);}
function clearRenderRetry(screen){if(renderRetryTimers[screen]){clearTimeout(renderRetryTimers[screen]);delete renderRetryTimers[screen];}}
function renderLoaderError(screen,loader){var target=targetFor(screen);if(!target||target.id!==screen+'Screen'||target.dataset.loaderErrorRendered==='1')return;target.dataset.loaderErrorRendered='1';var host=target.querySelector('.module-placeholder-screen, .content-card, .dashboard-kpi-grid, .agri-kpi-grid')||target;host.innerHTML='<div class="empty-state text-muted py-4 text-center">KhÃ´ng táº£i Ä‘Æ°á»£c module. Vui lÃ²ng táº£i láº¡i trang hoáº·c kiá»ƒm tra file JavaScript: '+loader+'</div>';}
function kebab(value){return String(value||'').replace(/^load/,'').replace(/([a-z0-9])([A-Z])/g,'$1-$2').replace(/[_\s]+/g,'-').toLowerCase();}
function moduleAssetFor(screen,loader){var module=platformModule(screen)||{};return module.assetPath||('/assets/js/'+kebab(loader||module.moduleKey||screen)+'.min.js');}
function ensureModuleAsset(screen,loader){var asset=moduleAssetFor(screen,loader);if(dynamicLoaderPromises[asset])return dynamicLoaderPromises[asset];dynamicLoaderPromises[asset]=new Promise(function(resolve,reject){var script=document.createElement('script');script.src=asset+(asset.indexOf('?')>=0?'&':'?')+'loader_retry='+Date.now();script.async=false;script.onload=function(){writeLog('dynamicLoaderLoaded',{screen:screen,loader:loader,asset:asset});resolve(asset);};script.onerror=function(){writeLog('dynamicLoaderFailed',{screen:screen,loader:loader,asset:asset});reject(new Error('KhÃ´ng táº£i Ä‘Æ°á»£c '+asset));};(document.head||document.documentElement).appendChild(script);writeLog('dynamicLoaderStart',{screen:screen,loader:loader,asset:asset});});dynamicLoaderPromises[asset].catch(function(){});return dynamicLoaderPromises[asset];}
function scheduleRenderRetry(screen,loader,attempt){clearRenderRetry(screen);renderRetryTimers[screen]=setTimeout(function(){var target=targetFor(screen);if(!target||target.id!==screen+'Screen'||!target.classList.contains('active')){clearRenderRetry(screen);return;}var currentLoader=loaderFor(screen)||loader;if(currentLoader&&typeof window[currentLoader]==='function'){writeLog('renderRetryReady',{screen:screen,loader:currentLoader,attempt:attempt});render(screen);return;}if(attempt>=30){writeLog('renderRetryFailed',{screen:screen,loader:currentLoader||loader,attempt:attempt});renderLoaderError(screen,currentLoader||loader);clearRenderRetry(screen);return;}writeLog('renderRetryWaiting',{screen:screen,loader:currentLoader||loader,attempt:attempt});scheduleRenderRetry(screen,currentLoader||loader,attempt+1);},attempt<10?100:250);}
function centerMobile(button){if(!button)return;try{button.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});}catch(error){}}
function setSidebarSectionOpen(section,open,persist){if(!section)return;var toggle=section.querySelector(':scope > .nav-section-title, :scope > .sidebar-accordion-toggle');section.classList.toggle('is-open',!!open);section.classList.toggle('is-collapsed',!open);if(toggle){toggle.setAttribute('aria-expanded',open?'true':'false');if(persist&&toggle.dataset.sidebarGroupToggle){try{localStorage.setItem(tenantStorageKey('sidebar_group_' + toggle.dataset.sidebarGroupToggle),open?'1':'0');}catch(error){}}}}
function registerNavigationPatchActions(){var actions=window.TenantAppPlatform&&window.TenantAppPlatform.actions;if(window.__TenantAppNavigationPatchActionsRegistered||!actions||typeof actions.register!=='function')return;window.__TenantAppNavigationPatchActionsRegistered=true;actions.register('navigation.sidebarGroup.toggle',function(context){var section=context.target&&context.target.closest&&context.target.closest('.nav-section');setSidebarSectionOpen(section,!(section&&section.classList.contains('is-open')),true);});}
function syncSidebarAccordion(screen,requested){document.querySelectorAll('.gov-nav .nav-section').forEach(function(section){var active=!!section.querySelector('.nav-link.active[data-screen]');if(active)setSidebarSectionOpen(section,true,false);});}
function setAppState(screen,requested){var previous=currentScreen();if(window.App)window.App.screen=screen;try{localStorage.setItem(tenantStorageKey('screen'),screen);}catch(error){}writeLog('setActiveScreen',{previousScreen:previous,currentScreen:screen,requestedScreen:requested});try{document.dispatchEvent(new CustomEvent('tenant:screen-change',{detail:{screen:screen,requestedScreen:requested,previousScreen:previous}}));}catch(error){}}
function hideOtherScreens(target){document.querySelectorAll('.screen').forEach(function(el){var active=el===target;el.classList.toggle('active',active);el.style.display=active?'block':'none';el.setAttribute('aria-hidden',active?'false':'true');});writeLog('hideOtherScreens',domState());}
function syncActiveNavigation(screen,requested){document.querySelectorAll('.sidebar .nav-link[data-screen]').forEach(function(btn){var active=btn.dataset.screen===screen||btn.dataset.screen===requested;btn.classList.toggle('active',active);btn.setAttribute('aria-current',active?'page':'false');});syncSidebarAccordion(screen,requested);document.querySelectorAll('.mobile-bottom-nav [data-mobile-screen]').forEach(function(btn){var active=btn.dataset.mobileScreen===screen||btn.dataset.mobileScreen===requested;btn.classList.toggle('active',active);btn.setAttribute('aria-current',active?'page':'false');if(active)centerMobile(btn);});}
function updateHeader(screen,requested){var label=labelFor(screen,requested);var title=document.getElementById('screenTitle');var breadcrumb=document.getElementById('breadcrumbTrail');if(title)title.textContent=label;if(breadcrumb)breadcrumb.textContent='Trang ch\u1ee7 / '+label;}
function closeMobileShell(){document.body.classList.remove('sidebar-open');var sidebar=document.querySelector('.sidebar');if(sidebar)sidebar.classList.remove('open');}
function render(screen){if(screen==='gis'&&typeof window.ensureGisAssets==='function'&&typeof window.loadGisMap==='function'){clearRenderRetry(screen);window.ensureGisAssets().then(function(){window.loadGisMap();}).catch(function(error){if(typeof window.showToast==='function')window.showToast('Kh\u00f4ng t\u1ea3i \u0111\u01b0\u1ee3c th\u01b0 vi\u1ec7n b\u1ea3n \u0111\u1ed3: '+error.message,'danger');});writeLog('render',{screen:screen,loader:'ensureGisAssets/loadGisMap'});return;}var loader=loaderFor(screen);if(loader&&typeof window[loader]==='function'){clearRenderRetry(screen);setTimeout(function(){window[loader]();},0);writeLog('render',{screen:screen,loader:loader});}else{writeLog('render',{screen:screen,loader:loader||''});if(loader){ensureModuleAsset(screen,loader).then(function(){var target=targetFor(screen);if(target&&target.id===screen+'Screen'&&target.classList.contains('active'))render(screen);}).catch(function(){renderLoaderError(screen,loader);});scheduleRenderRetry(screen,loader,1);}}}
function displayScreen(screen,requested){var target=targetFor(screen);var loader=loaderFor(screen);if(!target&&loader&&typeof window[loader]==='function'){try{window[loader]();}catch(error){writeLog('displayLoaderError',{screen:screen,loader:loader,message:error&&error.message||String(error)});}target=targetFor(screen);}if(!target&&loader){writeLog('displayWaitForLoader',{screen:screen,loader:loader});ensureModuleAsset(screen,loader).then(function(){displayScreen(screen,requested);}).catch(function(){renderLoaderError(screen,loader);});return false;}if(!target){writeLog('displayScreen',{screen:screen,found:false});return false;}if(typeof window.TenantAppApplyModuleDisplayOrder==='function')window.TenantAppApplyModuleDisplayOrder();setAppState(screen,requested);hideOtherScreens(target);syncActiveNavigation(screen,requested);updateHeader(screen,requested);closeMobileShell();render(screen);writeLog('displayScreen',Object.assign({screen:screen,targetScreen:target.id,currentScreen:currentScreen()},domState()));return true;}
function navigate(screen,event){if(event&&event.defaultPrevented)return false;log=[];var requested=screen;var normalized=normalize(screen);var module=platformModule(normalized);if(!targetFor(normalized)&&!module)normalized='dashboard';if(event&&typeof event.preventDefault==='function')event.preventDefault();if(event&&typeof event.stopPropagation==='function')event.stopPropagation();writeLog('Click menu',{menuKey:requested,moduleKey:normalized,targetScreen:normalized+'Screen',currentScreen:currentScreen(),eventTarget:event&&event.target&&event.target.tagName,eventCurrentTarget:event&&event.currentTarget&&event.currentTarget.tagName});displayScreen(normalized,requested);return true;}
window.TenantAppNavigationController={navigate:navigate,hideOtherScreens:hideOtherScreens,render:render,inspect:domState,getLog:function(){return log.slice();}};
registerNavigationPatchActions();
document.addEventListener('click',function(event){var item=event.target.closest&&event.target.closest('[data-screen],[data-mobile-screen]');if(!item||item.classList.contains('gov-logout'))return;var delegation=window.TenantAppPlatform&&window.TenantAppPlatform.navigationDelegation;if(delegation&&typeof delegation.handleClick==='function'){delegation.handleClick(event,{stopPropagation:true,source:'legacy-navigation-controller'});return;}var screen=item.dataset.screen||item.dataset.mobileScreen;if(screen)navigate(screen,event);},true);
setTimeout(function(){navigate(initialScreen());},0);
}
function accessibilityRepairModule(){
var form=document.getElementById('publicAssetForm');
var gps=form&&form.elements&&form.elements.gps_accuracy;
if(gps){
gps.id=gps.id||'publicAssetGpsAccuracyInput';
gps.setAttribute('aria-label','Sai so GPS (m)');
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
try{navigationControllerModule();}catch(error){console.error('navigation controller module failed',error);}
try{accessibilityRepairModule();}catch(error){console.error('accessibility repair module failed',error);}
try{commonModalModule();}catch(error){console.error('common modal module failed',error);}
})();
