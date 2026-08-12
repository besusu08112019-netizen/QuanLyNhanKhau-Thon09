(() => {
  const HOUSEHOLD_MEMBER_PAGE_SIZE = 8;
  const state = { householdMembers: [], householdPage: 1, householdSearch: '' };
  let currentPersonDetail = null;

  function registerModal(id) {
    const modal = document.querySelector('#' + id);
    const service = window.TenantAppPlatform?.modals;
    if (modal && service?.registerBootstrap) service.registerBootstrap(id, '#' + id);
    return modal;
  }

  function openModal(id) {
    const service = window.TenantAppPlatform?.modals;
    if (service?.open && service.open(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.(document.querySelector('#' + id))?.show();
  }

  function closeModal(id) {
    const service = window.TenantAppPlatform?.modals;
    if (service?.close && service.close(id)) return;
    window.bootstrap?.Modal?.getOrCreateInstance?.(document.querySelector('#' + id))?.hide();
  }

  document.addEventListener('DOMContentLoaded', () => {
    registerSprint8Actions();
    ensureSprint8Modals();
    patchImportGuide();
    patchUserManagementUi();
  });

  document.addEventListener('tenant:auth-state', event => {
    if (!event.detail?.authenticated) return;
    registerSprint8Actions();
    ensureSprint8Modals();
    patchImportGuide();
    patchUserManagementUi();
  });

  function registerSprint8Actions() {
    const actions = window.TenantAppPlatform && window.TenantAppPlatform.actions;
    if (window.__TenantAppSprint8ActionsRegistered || !actions || typeof actions.register !== 'function') return;
    window.__TenantAppSprint8ActionsRegistered = true;
    actions
      .register('sprint8.person.edit', context => {
        const id = Number(context.dataset.id || currentPersonDetail?.id || 0);
        closeModal('personDetailModal');
        if (id) openPersonForm(id);
      })
      .register('sprint8.person.print', () => {
        if (currentPersonDetail) printPersonDetail(currentPersonDetail);
      })
      .register('sprint8.member.page', context => {
        state.householdPage = Number(context.dataset.memberPage || 1);
        renderHouseholdMembers();
      });
  }

  window.showHousehold = async function showHousehold(id) {
    try {
      const household = await api('/api/households/' + id);
      const members = await api('/api/persons?' + new URLSearchParams({ householdId: household.household_code, pageSize: 1000 }).toString());
      state.householdMembers = members.items || [];
      state.householdPage = 1;
      state.householdSearch = '';
      document.querySelector('#householdMemberSearch').value = '';
      document.querySelector('#householdMemberTitle').textContent = 'ThÃ nh viÃªn há»™ ' + (household.household_code || '');
      document.querySelector('#householdMemberMeta').innerHTML = details([
        ['M\u00e3 h\u1ed9', household.household_code], ['Ch\u1ee7 h\u1ed9', household.head_citizen_name], ['\u0110\u1ecba ch\u1ec9', household.address], ['S\u1ed1 \u0111i\u1ec7n tho\u1ea1i', household.phone], ['\u1ede nh\u00e0', household.at_home_count || 0], ['\u0110i v\u1eafng', household.away_count || 0]
      ]) + householdRelatedSummaryHtml(household.related_summary || {});
      renderHouseholdMembers();
      openModal('householdMembersModal');
    } catch (error) { showToast(error.message, 'danger'); }
  };

  window.showPerson = async function showPerson(id) {
    try {
      const row = await api('/api/persons/' + id);
      currentPersonDetail = row;
      document.querySelector('#personDetailTitle').textContent = row.full_name || 'Chi tiáº¿t nhÃ¢n kháº©u';
      document.querySelector('#personDetailBody').innerHTML = personDetailHtml(row);
      document.querySelector('#personDetailEditBtn').dataset.id = row.id || '';
      document.querySelector('#personDetailPrintBtn').dataset.id = row.id || '';
      openModal('personDetailModal');
    } catch (error) { showToast(error.message, 'danger'); }
  };

  function ensureSprint8Modals() {
    if (document.querySelector('#householdMembersModal')) {
      registerModal('householdMembersModal');
      registerModal('personDetailModal');
      return;
    }
    document.body.insertAdjacentHTML('beforeend', '<div class="modal fade" id="householdMembersModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 id="householdMemberTitle" class="modal-title">ThÃ nh viÃªn há»™</h5><small class="text-muted">Danh sÃ¡ch nhÃ¢n kháº©u cÃ¹ng mÃ£ há»™</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div class="modal-body"><div id="householdMemberMeta" class="mb-3"></div><div class="toolbar"><input id="householdMemberSearch" class="form-control" placeholder="TÃ¬m há» tÃªn, mÃ£ nhÃ¢n kháº©u, CCCD, sá»‘ Ä‘iá»‡n thoáº¡i"><button class="btn btn-light" type="button" data-bs-dismiss="modal">ÄÃ³ng</button></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>MÃ£ nhÃ¢n kháº©u</th><th>Há» tÃªn</th><th>NgÃ y sinh</th><th>CCCD</th><th>Sá»‘ Ä‘iá»‡n thoáº¡i</th><th>ThÆ°á»ng trÃº</th><th>Hiá»‡n táº¡i</th><th></th></tr></thead><tbody id="householdMemberRows"></tbody></table></div><div id="householdMemberPager" class="pager"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div><div class="modal fade" id="personDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 id="personDetailTitle" class="modal-title">Chi tiáº¿t nhÃ¢n kháº©u</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ÄÃ³ng"></button></div><div id="personDetailBody" class="modal-body"></div><div class="modal-footer"><button id="personDetailEditBtn" class="btn btn-primary" type="button" data-platform-action="sprint8.person.edit">Sá»­a</button><button id="personDetailPrintBtn" class="btn btn-outline-secondary" type="button" data-platform-action="sprint8.person.print">In</button><button class="btn btn-light" type="button" data-bs-dismiss="modal">ÄÃ³ng</button></div></div></div></div>');
    document.querySelector('#householdMemberSearch').addEventListener('input', debounce(event => { state.householdSearch = event.target.value.trim().toLowerCase(); state.householdPage = 1; renderHouseholdMembers(); }, 250));
    registerModal('householdMembersModal');
    registerModal('personDetailModal');
  }

  function renderHouseholdMembers() {
    const filtered = state.householdMembers.filter(row => !state.householdSearch || [row.citizen_code, row.full_name, row.identity_number, row.phone].join(' ').toLowerCase().includes(state.householdSearch));
    const totalPages = Math.max(1, Math.ceil(filtered.length / HOUSEHOLD_MEMBER_PAGE_SIZE));
    state.householdPage = Math.min(state.householdPage, totalPages);
    const start = (state.householdPage - 1) * HOUSEHOLD_MEMBER_PAGE_SIZE;
    const pageItems = filtered.slice(start, start + HOUSEHOLD_MEMBER_PAGE_SIZE);
    document.querySelector('#householdMemberRows').innerHTML = pageItems.map(row => '<tr><td>' + escapeHtml(row.citizen_code || '') + '</td><td>' + escapeHtml(row.full_name || '') + '</td><td>' + formatDate(row.date_of_birth) + '</td><td>' + escapeHtml(row.identity_number || '') + '</td><td>' + escapeHtml(row.phone || '') + '</td><td>' + residencyLabel(row.residency_status) + '</td><td>' + presenceLabel(row.presence_status) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="persons.detail" data-id="' + row.id + '">Xem chi tiáº¿t</button></td></tr>').join('') || emptyRow(8, 'KhÃ´ng cÃ³ thÃ nh viÃªn phÃ¹ há»£p');
    document.querySelector('#householdMemberPager').innerHTML = '<span class="text-muted small">Trang ' + state.householdPage + '/' + totalPages + ' - ' + number(filtered.length) + ' thÃ nh viÃªn</span><button class="btn btn-outline-secondary btn-sm" ' + (state.householdPage <= 1 ? 'disabled' : '') + ' data-platform-action="sprint8.member.page" data-member-page="' + (state.householdPage - 1) + '">TrÆ°á»›c</button><button class="btn btn-outline-secondary btn-sm" ' + (state.householdPage >= totalPages ? 'disabled' : '') + ' data-platform-action="sprint8.member.page" data-member-page="' + (state.householdPage + 1) + '">Sau</button>';
  }

  function personDetailHtml(row) {
    const photo = hasValue(row.photo_url) ? '<div class="person-detail-photo"><img src="' + escapeHtml(row.photo_url) + '" alt="áº¢nh nhÃ¢n kháº©u"></div>' : '';
    const fullName = valueText(row.full_name) || 'NhÃ¢n kháº©u';
    const citizenCode = valueText(row.citizen_code);
    const identity = valueText(row.identity_number || row.personal_id || row.national_id);
    const age = ageText(row.date_of_birth);
    const statusBadges = [
      badge(row.gender, 'neutral'),
      Number(row.party_member) === 1 ? badge('Äáº£ng viÃªn', 'gold') : '',
      isHouseholdHead(row) ? badge('Chá»§ há»™', 'green') : '',
      row.residency_status === 'TEMPORARY' ? badge('Táº¡m trÃº', 'blue') : '',
      row.presence_status === 'AWAY' ? badge('Táº¡m váº¯ng', 'purple') : ''
    ].filter(Boolean).join('');

    const basic = detailSection('Th\u00f4ng tin c\u01a1 b\u1ea3n', 'fa-id-card', [
      ['M\u00e3 h\u1ed9', row.household_code, 'code'],
      ['M\u00e3 nh\u00e2n kh\u1ea9u', row.citizen_code, 'code'],
      ['H\u1ecd v\u00e0 t\u00ean', row.full_name, 'strong'],
      ['Ng\u00e0y sinh', formatDate(row.date_of_birth)],
      ['Tu\u1ed5i', age],
      ['Gi\u1edbi t\u00ednh', row.gender],
      ['CCCD/S\u1ed1 \u0111\u1ecbnh danh', identity, 'code'],
      ['S\u1ed1 \u0111i\u1ec7n tho\u1ea1i', row.phone],
      ['\u0110\u1ecba ch\u1ec9 hi\u1ec7n t\u1ea1i', row.current_address || row.household_address]
    ]);
    const residence = detailSection('Th\u00f4ng tin nh\u00e2n th\u00e2n v\u00e0 c\u01b0 tr\u00fa', 'fa-house-user', [
      ['Quan h\u1ec7 v\u1edbi ch\u1ee7 h\u1ed9', row.relationship],
      ['D\u00e2n t\u1ed9c', row.ethnicity],
      ['Ngh\u1ec1 nghi\u1ec7p', row.occupation],
      ['Tr\u00ecnh \u0111\u1ed9 h\u1ecdc v\u1ea5n', row.education_level],
      ['T\u00ecnh tr\u1ea1ng h\u00f4n nh\u00e2n', row.marital_status],
      ['C\u01b0 tr\u00fa', residencyLabel(row.residency_status)],
      ['Hi\u1ec7n t\u1ea1i', presenceLabel(row.presence_status)],
      ['Ch\u1ee7 h\u1ed9', row.head_citizen_name],
      ['H\u1ecd t\u00ean b\u1ed1', row.father_name || row.father_display_name],
      ['H\u1ecd t\u00ean m\u1eb9', row.mother_name || row.mother_display_name]
    ]);
    const administrative = detailSection('ThÃ´ng tin hÃ nh chÃ­nh', 'fa-landmark', [
      ['Äáº£ng viÃªn', Number(row.party_member) === 1 ? 'CÃ³' : ''],
      ['ÄoÃ n viÃªn Thanh niÃªn', Number(row.youth_union_member) === 1 ? 'CÃ³' : ''],
      ['Há»™i Phá»¥ ná»¯', Number(row.women_union_member) === 1 ? 'CÃ³' : ''],
      ['Há»™i NÃ´ng dÃ¢n', Number(row.farmers_union_member) === 1 ? 'CÃ³' : ''],
      ['Há»™i Cá»±u chiáº¿n binh', Number(row.veterans_union_member) === 1 ? 'CÃ³' : ''],
      ['Há»™i NgÆ°á»i cao tuá»•i', Number(row.elderly_union_member) === 1 ? 'CÃ³' : ''],
      ['Äá»‘i tÆ°á»£ng chÃ­nh sÃ¡ch', policyLabels(row).join(', ')],
      ['Diá»‡n há»™', row.household_type],
      ['Tráº¡ng thÃ¡i', lifeLabel(row.life_status)],
      ['NgÃ y Ä‘Äƒng kÃ½', formatDateTime(row.created_at)],
      ['Ghi chÃº', row.note]
    ]);

    return '<div class="person-detail-card">'
      + '<div class="person-detail-hero">' + photo + '<div class="person-detail-identity"><span>Há»“ sÆ¡ nhÃ¢n kháº©u</span><h3>' + escapeHtml(fullName) + '</h3><div class="person-detail-codes">' + (citizenCode ? '<strong>' + escapeHtml(citizenCode) + '</strong>' : '') + (identity ? '<strong>CCCD: ' + escapeHtml(identity) + '</strong>' : '') + '</div><div class="person-detail-badges">' + statusBadges + '</div></div></div>'
      + '<div class="person-detail-sections">' + [basic, residence, administrative, personRelatedSummaryHtml(row.related_summary || {})].filter(Boolean).join('') + '</div>'
      + '</div>';
  }

  function relatedSummarySection(title, icon, rows) {
    const items = rows.filter(item => item && hasValue(item.value));
    if (!items.length) return '';
    return '<section class="person-info-section person-related-summary"><div class="person-info-section-title"><i class="fa-solid ' + escapeHtml(icon || 'fa-link') + '"></i><h4>' + escapeHtml(title) + '</h4></div><div class="person-info-grid">' + items.map(relatedSummaryField).join('') + '</div></section>';
  }

  function relatedSummaryField(item) {
    const body = '<span>' + escapeHtml(item.label || '') + '</span><strong>' + escapeHtml(item.value || '') + '</strong>';
    const action = relatedDetailButton(item.detail);
    return '<div class="detail-field related-summary-field">' + body + action + '</div>';
  }

  function relatedDetailButton(detail) {
    if (!detail || !detail.action || !detail.id) return '';
    const attrs = ['data-platform-action="' + escapeHtml(detail.action) + '"', 'data-id="' + escapeHtml(detail.id) + '"'];
    if (detail.household_id) attrs.push('data-household-id="' + escapeHtml(detail.household_id) + '"');
    if (detail.campaign_id) attrs.push('data-campaign-id="' + escapeHtml(detail.campaign_id) + '"');
    return '<button class="btn btn-sm btn-outline-primary mt-2" type="button" ' + attrs.join(' ') + '>Xem chi tiáº¿t</button>';
  }

  function personRelatedSummaryHtml(summary) {
    summary = summary || {};
    const party = summary.party_member;
    const orgs = Array.isArray(summary.organizations) ? summary.organizations : [];
    const defense = summary.defense_security || {};
    const policies = Array.isArray(summary.policy_subjects) ? summary.policy_subjects : [];
    const insurance = summary.health_insurance;
    const orgRows = [
      { label: '\u0110\u1ea3ng vi\u00ean', value: party ? [statusText(party.status), party.summary].filter(Boolean).join(' - ') : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u li\u00ean k\u1ebft', detail: party && party.detail },
      ...(orgs.length ? orgs.map(item => ({ label: item.organization_name || '\u0110o\u00e0n th\u1ec3 - Chi h\u1ed9i', value: [item.position_name, statusText(item.status), item.joined_date ? 'T\u1eeb ' + formatDate(item.joined_date) : ''].filter(Boolean).join(' - '), detail: item.detail })) : [{ label: '\u0110o\u00e0n th\u1ec3 - Chi h\u1ed9i', value: 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u' }])
    ];
    const defenseRows = [
      { label: 'Ngh\u0129a v\u1ee5 qu\u00e2n s\u1ef1', value: defense.nvqs ? [defense.nvqs.recruitment_year || defense.nvqs.year, statusText(defense.nvqs.registered_status || defense.nvqs.registration_status), statusText(defense.nvqs.selection_status)].filter(Boolean).join(' - ') : 'Ch\u01b0a c\u00f3 h\u1ed3 s\u01a1 NVQS', detail: defense.nvqs && defense.nvqs.detail },
      { label: 'D\u00e2n qu\u00e2n t\u1ef1 v\u1ec7', value: defense.militia ? [defense.militia.militia_type || defense.militia.force_type, defense.militia.position_name, statusText(defense.militia.participation_status || defense.militia.status)].filter(Boolean).join(' - ') : 'Ch\u01b0a c\u00f3 h\u1ed3 s\u01a1 d\u00e2n qu\u00e2n', detail: defense.militia && defense.militia.detail },
      { label: 'ANTT c\u01a1 s\u1edf', value: defense.security_force ? [defense.security_force.team_name, defense.security_force.position_label || defense.security_force.position_name, statusText(defense.security_force.participation_status || defense.security_force.status)].filter(Boolean).join(' - ') : 'Ch\u01b0a c\u00f3 h\u1ed3 s\u01a1 ANTT', detail: defense.security_force && defense.security_force.detail }
    ];
    const socialRows = [
      ...(policies.length ? policies.map(item => ({ label: item.policy_name || 'Ch\u00ednh s\u00e1ch c\u00e1 nh\u00e2n', value: statusText(item.status), detail: item.detail })) : [{ label: 'Ch\u00ednh s\u00e1ch c\u00e1 nh\u00e2n', value: 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u ch\u00ednh s\u00e1ch c\u00e1 nh\u00e2n' }]),
      { label: 'BHYT', value: insurance ? [Number(insurance.has_health_insurance || 0) === 1 ? 'C\u00f3 BHYT' : 'Ch\u01b0a c\u00f3 BHYT', insurance.number ? 'S\u1ed1 th\u1ebb: ' + insurance.number : '', insurance.end_date ? 'H\u1ebft h\u1ea1n: ' + formatDate(insurance.end_date) : ''].filter(Boolean).join(' - ') : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u BHYT' }
    ];
    return [
      relatedSummarySection('T\u1ed5 ch\u1ee9c - \u0110o\u00e0n th\u1ec3', 'fa-people-group', orgRows),
      relatedSummarySection('An ninh - Qu\u1ed1c ph\u00f2ng', 'fa-shield-halved', defenseRows),
      relatedSummarySection('Ch\u00ednh s\u00e1ch - An sinh', 'fa-hand-holding-heart', socialRows)
    ].filter(Boolean).join('');
  }

  function householdRelatedSummaryHtml(summary) {
    summary = summary || {};
    const members = summary.members || {};
    const water = summary.water;
    const livestock = summary.livestock || {};
    const agriculture = summary.agriculture || {};
    const houses = summary.houses || {};
    const vehicles = summary.vehicles || {};
    const business = summary.business || {};
    const contributions = summary.contributions || {};
    const poverty = summary.poverty;
    const category = summary.household_category || {};
    return [
      relatedSummarySection('Th\u00e0nh vi\u00ean', 'fa-users', [
        { label: 'Nh\u00e2n kh\u1ea9u trong h\u1ed9', value: Number(members.total || 0) + ' nh\u00e2n kh\u1ea9u' + (Number(members.away || 0) ? ' - ' + Number(members.away || 0) + ' t\u1ea1m v\u1eafng' : '') }
      ]),
      relatedSummarySection('\u0110\u1eddi s\u1ed1ng - H\u1ea1 t\u1ea7ng', 'fa-faucet-drip', [
        { label: 'N\u01b0\u1edbc s\u1ea1ch', value: water ? [waterStatusText(water.status), waterCodeText(water.water_source || water.connection_type), waterCodeText(water.clean_water_status), water.provider_name].filter(Boolean).join(' - ') : 'Ch\u01b0a c\u00f3 th\u00f4ng tin n\u01b0\u1edbc sinh ho\u1ea1t', detail: water && water.detail }
      ]),
      relatedSummarySection('S\u1ea3n xu\u1ea5t', 'fa-seedling', [
        { label: 'Ch\u0103n nu\u00f4i/v\u1eadt nu\u00f4i', value: Number(livestock.records || 0) ? Number(livestock.quantity || 0) + ' con / ' + Number(livestock.type_count || 0) + ' lo\u1ea1i' : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u', detail: Number(livestock.records || 0) ? livestock.detail : null },
        { label: 'N\u00f4ng nghi\u1ec7p', value: Number(agriculture.parcels || 0) ? Number(agriculture.parcels || 0) + ' th\u1eeda - ' + number(agriculture.area || 0) + ' m2' : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u', detail: Number(agriculture.parcels || 0) ? agriculture.detail : null },
        { label: 'S\u1ea3n xu\u1ea5t kinh doanh', value: Number(business.total || 0) ? Number(business.total || 0) + ' ho\u1ea1t \u0111\u1ed9ng' + (business.latest_name ? ' - ' + business.latest_name : '') : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u', detail: Number(business.total || 0) ? business.detail : null }
      ]),
      relatedSummarySection('Nh\u00e0 \u1edf - T\u00e0i s\u1ea3n', 'fa-house-chimney', [
        { label: 'Nh\u00e0 \u1edf', value: Number(houses.total || 0) ? Number(houses.total || 0) + ' c\u00f4ng tr\u00ecnh' + (houses.latest_type ? ' - ' + houses.latest_type : '') : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u', detail: Number(houses.total || 0) ? houses.detail : null },
        { label: 'Xe c\u1ed9', value: Number(vehicles.total || 0) ? Number(vehicles.total || 0) + ' ph\u01b0\u01a1ng ti\u1ec7n' : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u', detail: Number(vehicles.total || 0) ? vehicles.detail : null }
      ]),
      relatedSummarySection('Di\u1ec7n h\u1ed9 - Ch\u00ednh s\u00e1ch', 'fa-scale-balanced', [
        { label: 'Di\u1ec7n h\u1ed9 hi\u1ec7n h\u00e0nh', value: category.household_type || 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u' },
        { label: 'L\u1ecbch s\u1eed ngh\u00e8o/c\u1eadn ngh\u00e8o', value: poverty ? [poverty.household_type || poverty.poverty_type, statusText(poverty.status)].filter(Boolean).join(' - ') : 'Ch\u01b0a c\u00f3 l\u1ecbch s\u1eed di\u1ec7n h\u1ed9', detail: poverty && poverty.detail }
      ]),
      relatedSummarySection('\u0110\u00f3ng g\u00f3p', 'fa-hand-holding-dollar', [
        { label: '\u0110\u00f3ng g\u00f3p h\u1ed9', value: Number(contributions.total || 0) ? '\u0110\u00e3 ghi nh\u1eadn ' + Number(contributions.total || 0) + ' kho\u1ea3n, c\u00f2n n\u1ee3 ' + number(contributions.debt || 0) : 'Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u', detail: Number(contributions.total || 0) ? contributions.detail : null }
      ])
    ].filter(Boolean).join('');
  }

  function statusText(value) {
    const map = { ACTIVE: '\u0110ang ho\u1ea1t \u0111\u1ed9ng', INACTIVE: 'Ng\u1eebng ho\u1ea1t \u0111\u1ed9ng', PAUSED: 'T\u1ea1m ng\u1eebng', TRANSFERRED: 'Chuy\u1ec3n sinh ho\u1ea1t', ENDED: '\u0110\u00e3 th\u00f4i tham gia', DECEASED: '\u0110\u00e3 m\u1ea5t', YES: 'C\u00f3', NO: 'Kh\u00f4ng', REGISTERED: '\u0110\u00e3 \u0111\u0103ng k\u00fd', NOT_REGISTERED: 'Ch\u01b0a \u0111\u0103ng k\u00fd', UNKNOWN: 'Ch\u01b0a x\u00e1c \u0111\u1ecbnh', SELECTED: 'Tr\u00fang tuy\u1ec3n', NOT_SELECTED: 'Ch\u01b0a tr\u00fang tuy\u1ec3n', ENLISTED: '\u0110\u00e3 nh\u1eadp ng\u0169' };
    const text = valueText(value);
    return map[text] || text;
  }

  function waterStatusText(value) {
    const map = { ACTIVE: '\u0110ang s\u1eed d\u1ee5ng', INACTIVE: 'T\u1ea1m ng\u1eebng', NEEDS_REPAIR: 'C\u1ea7n s\u1eeda ch\u1eefa', DISCONNECTED: '\u0110\u00e3 ng\u1eaft' };
    const text = valueText(value);
    return map[text] || text;
  }

  function waterCodeText(value) {
    const map = { PIPED: 'N\u01b0\u1edbc m\u00e1y/t\u1eadp trung', BOREHOLE_WELL: 'Gi\u1ebfng khoan', DUG_WELL: 'Gi\u1ebfng \u0111\u00e0o', WELL: 'Gi\u1ebfng', RAINWATER: 'N\u01b0\u1edbc m\u01b0a', PURCHASED: 'N\u01b0\u1edbc mua', OTHER: 'Kh\u00e1c', CENTRALIZED: 'C\u1ea5p n\u01b0\u1edbc t\u1eadp trung', HOUSEHOLD_SCALE: 'Quy m\u00f4 h\u1ed9', COMPLIANT: '\u0110\u1ea1t quy chu\u1ea9n', NON_COMPLIANT: 'Kh\u00f4ng \u0111\u1ea1t quy chu\u1ea9n', UNKNOWN: 'Ch\u01b0a x\u00e1c \u0111\u1ecbnh' };
    const text = valueText(value);
    return map[text] || text;
  }

  function hasValue(value) {
    if (value === null || value === undefined) return false;
    const text = String(value).trim();
    return text !== '' && text !== '-' && text !== 'â€”' && text.toLowerCase() !== 'null' && text.toLowerCase() !== 'undefined';
  }

  function valueText(value) { return hasValue(value) ? String(value).trim() : ''; }

  function detailSection(title, icon, rows) {
    const items = rows.filter(item => hasValue(item[1]));
    if (!items.length) return '';
    return '<section class="person-info-section"><div class="person-info-section-title"><i class="fa-solid ' + icon + '"></i><h4>' + escapeHtml(title) + '</h4></div><div class="person-info-grid">' + items.map(item => detailField(item[0], item[1], item[2])).join('') + '</div></section>';
  }

  function detailField(label, value, tone) {
    const className = tone ? ' person-info-value-' + tone : '';
    return '<div class="person-info-field"><span>' + escapeHtml(label) + '</span><strong class="person-info-value' + className + '">' + escapeHtml(valueText(value)) + '</strong></div>';
  }

  function badge(text, tone) { return hasValue(text) ? '<span class="person-detail-badge person-detail-badge-' + tone + '">' + escapeHtml(text) + '</span>' : ''; }

  function isHouseholdHead(row) {
    return String(row.relationship || '').trim().toLowerCase() === 'chá»§ há»™' || String(row.full_name || '').trim() === String(row.head_citizen_name || '').trim();
  }

  function ageText(value) {
    if (!hasValue(value)) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    const now = new Date();
    let age = now.getFullYear() - date.getFullYear();
    const monthDiff = now.getMonth() - date.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < date.getDate())) age--;
    return age >= 0 ? age + ' tuá»•i' : '';
  }

  function policyLabels(row) {
    const labels = [];
    if (Number(row.martyr_relative) === 1) labels.push('ThÃ¢n nhÃ¢n liá»‡t sÄ©');
    if (Number(row.wounded_soldier) === 1) labels.push('ThÆ°Æ¡ng binh');
    if (Number(row.sick_soldier) === 1) labels.push('Bá»‡nh binh');
    if (Number(row.chemical_warfare_victim) === 1) labels.push('NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ nhiá»…m cháº¥t Ä‘á»™c hÃ³a há»c');
    if (Number(row.imprisoned_resistance_activist) === 1) labels.push('NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ Ä‘á»‹ch báº¯t tÃ¹, Ä‘Ã y');
    if (Number(row.youth_volunteer) === 1) labels.push('Thanh niÃªn xung phong');
    if (Number(row.resistance_hero) === 1) labels.push('Anh hÃ¹ng LLVTND / Anh hÃ¹ng Lao Ä‘á»™ng thá»i ká»³ khÃ¡ng chiáº¿n');
    if (Number(row.revolutionary_activist) === 1) labels.push('NgÆ°á»i hoáº¡t Ä‘á»™ng cÃ¡ch máº¡ng');
    if (Number(row.disabled_person) === 1) labels.push('NgÆ°á»i khuyáº¿t táº­t');
    if (Number(row.social_assistance) === 1) labels.push('Äang hÆ°á»Ÿng trá»£ cáº¥p xÃ£ há»™i');
    return labels;
  }

  function printPersonDetail(row) {
    if (!window.TenantAppPrint) return showToast('Print Framework is not ready', 'warning');
    window.TenantAppPrint.render({
      title: 'Phieu thong tin nhÃ¢n kháº©u',
      type: 'citizen-detail',
      orientation: 'portrait',
      paperSize: 'A4',
      headers: ['ThÃ´ng tin', 'GiÃ¡ trá»‹'],
      headers: ['ThÃ´ng tin', 'Gia tri'],
      filters: { 'NhÃ¢n kháº©u': row.full_name || '', 'Ma nhÃ¢n kháº©u': row.citizen_code || '', 'MÃ£ há»™': row.household_code || '' },
      repeatHeader: true,
      showFooter: true,
      showSummary: false,
      showSignature: true
    });
  }

  function personPrintRows(row) {
    return [
      ['MÃ£ há»™', row.household_code],
      ['Ma nhÃ¢n kháº©u', row.citizen_code],
      ['H? v? t?n', row.full_name],
      ['Ng?y sinh', formatDate(row.date_of_birth)],
      ['Tu?i', ageText(row.date_of_birth)],
      ['Gi?i t?nh', row.gender],
      ['CCCD/S? ??nh danh', row.identity_number || row.personal_id || row.national_id],
      ['S? ?i?n tho?i', row.phone],
      ['Quan h? v?i ch? h?', row.relationship],
      ['DÃ¢n tá»™c', row.ethnicity],
      ['Nghá» nghiá»‡p', row.occupation],
      ['Há»c váº¥n', row.education_level],
      ['HÃ´n nhÃ¢n', row.marital_status],
      ['CÆ° trÃº', residencyLabel(row.residency_status)],
      ['Hiá»‡n táº¡i', presenceLabel(row.presence_status)],
      ['Chá»§ há»™', row.head_citizen_name],
      ['Há» tÃªn bá»‘', row.father_name || row.father_display_name],
      ['Há» tÃªn máº¹', row.mother_name || row.mother_display_name],
      ['Äáº£ng viÃªn', Number(row.party_member) === 1 ? 'CÃ³' : ''],
      ['Äá»‘i tÆ°á»£ng chÃ­nh sÃ¡ch', policyLabels(row).join(', ')],
      ['Ghi chÃº', row.note]
    ].filter(item => hasValue(item[1]));
  }

  function patchImportGuide() {
    const screen = document.querySelector('#importScreen');
    if (screen) screen.dataset.sprint8 = '1';
  }

  function patchUserManagementUi() {
    const screen = document.querySelector('#usersScreen');
    if (!screen || screen.dataset.sprint8) return;
    screen.dataset.sprint8 = '1';
    const head = screen.querySelector('thead tr');
    if (head) head.innerHTML = '<th>Username</th><th>Há» tÃªn</th><th>Email</th><th>Sá»‘ Ä‘iá»‡n thoáº¡i</th><th>Chá»©c vá»¥</th><th>Vai trÃ²</th><th>Tráº¡ng thÃ¡i</th><th>NgÃ y táº¡o</th><th>Láº§n Ä‘Äƒng nháº­p cuá»‘i</th><th></th>';
    const modalBody = document.querySelector('#userForm .modal-body');
    if (modalBody) modalBody.innerHTML = '<input type="hidden" name="id"><div class="row g-3"><div class="col-md-6"><label class="form-label">Username</label><input name="username" class="form-control" required></div><div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div><div class="col-md-6"><label class="form-label">Há» tÃªn</label><input name="displayName" class="form-control" required></div><div class="col-md-6"><label class="form-label">Sá»‘ Ä‘iá»‡n thoáº¡i</label><input name="phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Chá»©c vá»¥</label><input name="position" class="form-control"></div><div class="col-md-6"><label class="form-label">Vai trÃ²</label><select name="role" class="form-select"><option value="ADMIN">Admin</option><option value="OFFICER">CÃ¡n bá»™</option><option value="VIEWER">KhÃ¡ch</option></select></div><div class="col-12"><label class="form-label">Máº­t kháº©u</label><input name="password" type="password" class="form-control" minlength="8"><div class="form-text">Báº¯t buá»™c khi táº¡o má»›i, Ä‘á»ƒ trá»‘ng náº¿u khÃ´ng Ä‘á»•i.</div></div></div>';
    window.openUserForm = async function openUserForm(id = null) {
      const form = document.querySelector('#userForm');
      form.reset(); form.elements.id.value = ''; form.elements.email.disabled = false; form.elements.username.disabled = false;
      if (id) { const row = await api('/api/users/' + id); setForm(form, { id: row.id, username: row.username, email: row.email, displayName: row.displayName, phone: row.phone, position: row.position, role: row.role === 'SUPER_ADMIN' ? 'ADMIN' : row.role }); form.elements.email.disabled = true; form.elements.username.disabled = true; }
      openModal('userModal');
    };
  }

  window.renderUserRowsSprint8 = function renderUserRowsSprint8(data) {
    const body = document.querySelector('#userRows');
    if (!body) return;
    body.innerHTML = data.items.map(row => { const action = row.status === 'ACTIVE' ? 'lock' : 'unlock'; return '<tr><td>' + escapeHtml(row.username || '') + '</td><td>' + escapeHtml(row.display_name || row.displayName || '') + '</td><td>' + escapeHtml(row.email || '') + '</td><td>' + escapeHtml(row.phone || '') + '</td><td>' + escapeHtml(row.position || '') + '</td><td>' + roleLabel(row.role) + '</td><td>' + statusLabel(row.status) + '</td><td>' + formatDateTime(row.created_at) + '</td><td>' + formatDateTime(row.last_login_at) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-platform-action="users.edit" data-id="' + row.id + '">Sá»­a</button> <button class="btn btn-sm btn-outline-warning" type="button" data-platform-action="users.toggle" data-id="' + row.id + '" data-action="' + action + '">' + (action === 'lock' ? 'KhÃ³a' : 'Má»Ÿ khÃ³a') + '</button> <button class="btn btn-sm btn-outline-secondary" type="button" data-platform-action="users.resetPassword" data-id="' + row.id + '">Äáº·t láº¡i máº­t kháº©u</button> <button class="btn btn-sm btn-outline-danger" type="button" data-platform-action="users.delete" data-id="' + row.id + '">XÃ³a</button></td></tr>'; }).join('') || emptyRow(10, 'ChÆ°a cÃ³ ngÆ°á»i dÃ¹ng');
  };

  window.resetUserPassword = async function resetUserPassword(id) {
    const password = prompt('Nháº­p máº­t kháº©u má»›i tá»‘i thiá»ƒu 8 kÃ½ tá»±');
    if (!password) return;
    if (password.length < 8) return showToast('Máº­t kháº©u tá»‘i thiá»ƒu 8 kÃ½ tá»±', 'warning');
    const row = await api('/api/users/' + id);
    await api('/api/users/' + id, { method: 'PUT', body: { displayName: row.displayName, role: row.role, phone: row.phone, position: row.position, password } });
    showToast('ÄÃ£ Ä‘áº·t láº¡i máº­t kháº©u');
  };

  function formatDateTime(value) { if (!value) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? formatDate(value) : date.toLocaleString('vi-VN'); }
  function statusLabel(status) { return status === 'ACTIVE' ? 'Hoáº¡t Ä‘á»™ng' : status === 'INACTIVE' ? 'ÄÃ£ khÃ³a' : status || ''; }
})();
