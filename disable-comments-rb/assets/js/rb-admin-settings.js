/**
 * RB Disable Comments — settings screen UI.
 *
 * Everything renders inside one Shadow DOM attached to #rb-disable-comments-root.
 * Tabs: Disable, Delete, AI Assistant, General Settings, Statistics.
 * Two columns: settings card on the left, a Help card on the right (same
 * visual style as the settings card) with a fixed "how this works" outline
 * plus a hint area that updates on hover/focus via [data-help-key]
 * delegation. Dependency-free — no React/component runtime.
 */
( function () {
	'use strict';

	var data = window.RBDisableComments;
	if ( ! data ) {
		return;
	}

	var host = document.getElementById( 'rb-disable-comments-root' );
	if ( ! host || ! host.attachShadow ) {
		if ( host ) {
			host.textContent = data.strings.error;
		}
		return;
	}

	var shadow = host.attachShadow( { mode: 'open' } );
	var s = data.strings;
	var help = data.help || {};

	var STYLE = ''
		+ ':host{all:initial;display:block;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#1d1b20;}'
		+ '*{box-sizing:border-box;}'
		+ '.layout{display:flex;align-items:flex-start;gap:20px;}'
		+ '.card{background:#fff;border-radius:12px;padding:0;'
		+ 'box-shadow:0 1px 2px rgba(0,0,0,.06),0 1px 6px rgba(0,0,0,.08);overflow:hidden;}'
		+ '.main{flex:1;min-width:0;max-width:680px;}'
		+ '.card-head{padding:24px 28px 0;}'
		+ '.card-body{padding:8px 28px 28px;}'
		+ 'h1{font-size:20px;font-weight:600;margin:0 0 4px;color:#1d1b20;}'
		+ 'p.intro{margin:0 0 16px;color:#49454f;font-size:14px;line-height:1.5;}'
		+ '.tabs{display:flex;gap:4px;border-bottom:1px solid #eee;padding:0 28px;flex-wrap:wrap;}'
		+ '.tab{background:none;border:0;padding:12px 14px;font-size:14px;font-weight:500;'
		+ 'color:#49454f;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;'
		+ 'display:flex;align-items:center;gap:6px;}'
		+ '.tab.active{color:#6750a4;border-bottom-color:#6750a4;}'
		+ '.tab:hover{color:#6750a4;}'
		+ '.tab.ai-tab{color:#8b5cf6;}'
		+ '.tab.ai-tab.active{color:#6750a4;}'
		+ '.ai-badge{background:linear-gradient(135deg,#8b5cf6,#6750a4);color:#fff;font-size:10px;'
		+ 'font-weight:700;padding:2px 7px;border-radius:10px;letter-spacing:.02em;}'
		+ '.panel{display:none;}'
		+ '.panel.active{display:block;}'
		+ 'fieldset{border:0;padding:0;margin:0 0 20px;}'
		+ 'legend{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;'
		+ 'color:#6750a4;margin-bottom:10px;padding:0;}'
		+ '.option{display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:8px;'
		+ 'cursor:pointer;transition:background-color .12s ease;}'
		+ '.option:hover{background:#f4eff9;}'
		+ '.option input[type=radio],.option input[type=checkbox]{margin-top:2px;width:18px;height:18px;'
		+ 'accent-color:#6750a4;cursor:pointer;flex-shrink:0;}'
		+ '.option .label{font-size:14px;line-height:1.4;}'
		+ '.option .label strong{display:block;font-weight:500;}'
		+ '.option .label.has-badge{display:flex;align-items:center;flex-wrap:wrap;gap:0;}'
		+ '.option .label.has-badge strong{display:inline-block;}'
		+ '.indent{margin-left:30px;margin-top:4px;padding-left:12px;border-left:2px solid #e8e0f3;'
		+ 'display:none;flex-direction:column;gap:2px;}'
		+ '.indent.is-open{display:flex;}'
		+ '.date-row{display:flex;gap:16px;align-items:center;padding:10px 12px;flex-wrap:wrap;}'
		+ '.date-row label{font-size:13px;color:#49454f;display:flex;flex-direction:column;gap:4px;}'
		+ '.date-row input[type=date]{border:1px solid #cac4d0;border-radius:6px;padding:6px 8px;font-size:14px;}'
		+ '.actions{display:flex;align-items:center;gap:12px;margin-top:8px;flex-wrap:wrap;}'
		+ '.actions + fieldset{margin-top:26px;padding-top:20px;border-top:1px solid #f0ecf6;}'
		+ 'button.save,button.secondary,button.danger{border:0;border-radius:20px;padding:10px 24px;'
		+ 'font-size:14px;font-weight:500;cursor:pointer;transition:background-color .12s ease;font-family:inherit;}'
		+ 'button.save{background:#6750a4;color:#fff;}'
		+ 'button.save:hover{background:#5a4592;}'
		+ 'button.save:disabled,button.secondary:disabled{background:#c9c2d9;color:#fff;cursor:default;}'
		+ 'button.secondary{background:#eee7f8;color:#4a3b6e;}'
		+ 'button.secondary:hover{background:#e1d6f4;}'
		+ 'button.danger{background:#b3261e;color:#fff;}'
		+ 'button.danger:hover{background:#94201a;}'
		+ 'button.text-btn{background:none;border:1px dashed #cac4d0;color:#6750a4;border-radius:8px;'
		+ 'padding:8px 14px;font-size:13px;cursor:pointer;}'
		+ 'button.text-btn:hover{background:#f4eff9;}'
		+ '.status{font-size:13px;}'
		+ '.status.ok{color:#146c2e;}'
		+ '.status.err{color:#b3261e;}'
		+ '.stats-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:4px;}'
		+ '.stats-table th,.stats-table td{text-align:left;padding:8px 10px;border-bottom:1px solid #f0ecf6;}'
		+ '.stats-table th{color:#6750a4;font-weight:600;text-transform:uppercase;font-size:11px;letter-spacing:.03em;}'
		+ '.stats-totals{display:flex;gap:10px;margin:0;flex-wrap:wrap;}'
		+ '.stat-pill{background:#f4eff9;border-radius:10px;padding:10px 12px;flex:1 1 84px;min-width:84px;}'
		+ '.stats-section:first-child{margin-top:12px;}'
		+ '.stats-section + .stats-section{margin-top:28px;padding-top:22px;border-top:1px solid #f0ecf6;}'
		+ '.stats-heading{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#6750a4;margin:0 0 6px;}'
		+ '.stats-note{font-size:13px;line-height:1.5;color:#6b6578;margin:0 0 16px;}'
		+ '.stat-pill .num{font-size:20px;font-weight:600;display:block;}'
		+ '.stat-pill .lbl{font-size:12px;color:#49454f;}'
		+ '.checkgrid{display:flex;flex-wrap:wrap;gap:2px 16px;}'
		+ '.field{display:flex;flex-direction:column;gap:4px;margin-bottom:12px;}'
		+ '.field label{font-size:12px;color:#49454f;font-weight:500;}'
		+ '.field input[type=text],.field input[type=url],.field textarea,.field select{'
		+ 'border:1px solid #cac4d0;border-radius:8px;padding:8px 10px;font-size:14px;font-family:inherit;width:100%;}'
		+ '.field textarea{resize:vertical;min-height:60px;}'
		+ '.rule-card{border:1px solid #ece5f6;border-radius:10px;padding:16px;margin-bottom:14px;background:#fbfaff;}'
		+ '.rule-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}'
		+ '.rule-card-head span{font-size:12px;font-weight:700;color:#6750a4;text-transform:uppercase;letter-spacing:.03em;}'
		+ '.rule-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}'
		+ '.help{width:auto;}'
		+ '.help .card-body{padding:20px 22px 22px;}'
		+ '.help ol{margin:0 0 18px;padding-left:18px;font-size:13px;line-height:1.7;color:#3a3648;}'
		+ '.hint-box{background:#f4eff9;border-radius:10px;padding:13px 14px;min-height:64px;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;}'
		+ '.hint-box .bulb{flex-shrink:0;color:#6750a4;line-height:0;margin-top:2px;}'
		+ '.hint-box p{font-size:13px;line-height:1.6;color:#3a3648;margin:0;}'
		+ '.hint-box .placeholder{color:#8a8494;font-style:italic;}'
		+ '.right-col{width:280px;flex-shrink:0;display:flex;flex-direction:column;gap:16px;}'
		+ '.help-tabs{display:flex;gap:4px;margin:-4px 0 12px;border-bottom:1px solid #ece5f6;}'
		+ '.help-tab{background:none;border:0;padding:6px 10px;font-size:12px;font-weight:600;'
		+ 'color:#8a8494;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;}'
		+ '.help-tab.active{color:#6750a4;border-bottom-color:#6750a4;}'
		+ '.help-panel{display:none;}'
		+ '.help-panel.active{display:block;}'
		+ '.faq-item{margin-bottom:14px;}'
		+ '.faq-item:last-child{margin-bottom:0;}'
		+ '.faq-q{font-size:13px;font-weight:600;color:#1d1b20;margin:0 0 4px;}'
		+ '.faq-a{font-size:13px;line-height:1.6;color:#49454f;margin:0;}'
		+ '.about-subtitle{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6750a4;margin:14px 0 8px;}'
		+ '.about-features{margin:0 0 16px;padding-left:16px;font-size:12.5px;line-height:1.6;color:#3a3648;}'
		+ '.about-features li{margin-bottom:6px;}'
		+ '.about-links{display:flex;flex-direction:column;gap:8px;}'
		+ '.about-link{display:block;text-align:center;text-decoration:none;font-size:13px;font-weight:500;'
		+ 'border-radius:10px;padding:9px 12px;background:#f4eff9;color:#4a3b6e;}'
		+ '.about-link:hover{background:#e1d6f4;}'
		+ '.about-link.primary{background:#6750a4;color:#fff;}'
		+ '.about-link.primary:hover{background:#5a4592;}'
		+ '.preset-row{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap;}'
		+ '.preset-row label{font-size:12px;color:#49454f;font-weight:500;}'
		+ '.preset-row select{border:1px solid #cac4d0;border-radius:8px;padding:7px 10px;font-size:13px;font-family:inherit;}'
		+ '.pro-badge{background:linear-gradient(135deg,#ffd54a,#f0a800);color:#3a2c00;font-size:10px;font-weight:700;'
		+ 'box-shadow:0 0 0 1px rgba(224,168,0,.35),0 1px 3px rgba(224,168,0,.4);'
		+ 'padding:2px 8px;border-radius:10px;letter-spacing:.03em;margin-left:8px;vertical-align:middle;}'
		+ 'a.pro-badge{text-decoration:none;cursor:pointer;}'
		+ 'a.pro-badge:hover,a.pro-badge:focus{background:linear-gradient(135deg,#ffe07a,#ffb800);color:#3a2c00;}'
		+ '.pro-locked{position:relative;}'
		+ '.pro-locked legend{color:#b7b2c4;}'
		+ '.pro-locked > p.intro{opacity:.6;}'
		+ '.pro-locked .option .label,.pro-locked .option .label strong{color:#a39cb3;}'
		+ '.pro-locked input,.pro-locked select,.pro-locked textarea{opacity:.5;cursor:not-allowed !important;}'
		+ '.pro-locked button:not(.pro-badge){opacity:.5;cursor:not-allowed !important;}'
		+ '.pro-locked .date-row{opacity:.6;}'
		+ '.pro-locked .indent{border-left-color:#e8e5ee;}'
		+ '.pro-locked .stats-heading{color:#b7b2c4;}'
		+ '.pro-locked .stat-pill{opacity:.55;}'
		+ '.pro-locked .pro-badge{filter:none;opacity:1;}'
		+ '.pro-banner{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;'
		+ 'background:linear-gradient(135deg,#fff8e6,#fdf0d5);border:1px solid #f0d585;border-radius:10px;'
		+ 'padding:14px 18px;margin-bottom:18px;}'
		+ '.pro-banner-text{font-size:13px;color:#6b4e00;}'
		+ '.pro-banner-text strong{display:block;font-size:14px;margin-bottom:2px;color:#4a3600;}'
		+ '.pro-buy-btn{background:linear-gradient(135deg,#ffd54a,#f0a800);color:#3a2c00;border:0;border-radius:18px;padding:9px 18px;font-size:13px;'
		+ 'font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;box-shadow:0 1px 3px rgba(224,168,0,.4);}'
		+ '.pro-buy-btn:hover{background:linear-gradient(135deg,#ffe07a,#ffb800);color:#3a2c00;}'
		+ '.cli-commands{background:#12141c;color:#c9f299;border-radius:10px;padding:14px 16px;font-size:12px;'
		+ 'line-height:1.8;overflow-x:auto;white-space:pre;font-family:Menlo,Consolas,monospace;margin:8px 0 0;}'
		+ '@media (max-width: 900px){.layout{flex-direction:column;}.right-col{width:auto;}.main{max-width:none;}}'
		+ '.locked-banner{background:#fff4e5;border:1px solid #f0c04e;border-radius:10px;padding:14px 16px;margin-bottom:18px;}'
		+ '.locked-banner strong{display:block;font-size:13px;color:#6b4e00;margin-bottom:4px;}'
		+ '.locked-banner p{font-size:13px;color:#7a5c00;margin:0;line-height:1.5;}'
		+ '@media (max-width: 560px){.card-head,.card-body{padding-left:18px;padding-right:18px;}'
		+ '.tabs{padding:0 18px;}.rule-grid{grid-template-columns:1fr;}.stat-pill{min-width:76px;padding:8px 12px;}}';

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( key === 'text' ) {
				node.textContent = attrs[ key ];
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			node.appendChild( child );
		} );
		return node;
	}

	function withHelp( key, node ) {
		if ( key && help[ key ] ) {
			node.setAttribute( 'data-help-key', key );
			if ( ! node.hasAttribute( 'tabindex' ) ) {
				node.setAttribute( 'tabindex', '-1' );
			}
		}
		return node;
	}

	/**
	 * PRO gating for the UI. Fields stay visible (so free users see what
	 * they're missing) but every input inside gets disabled and the
	 * whole block is dimmed — matches "показать но не кликабельно",
	 * not "hide entirely". No-op when data.isPro is true.
	 */
	function proBadge() {
		if ( data.isPro ) {
			return el( 'span', { class: 'pro-badge', text: s.proBadge } );
		}
		// until PRO is unlocked, each badge leads to the pricing
		return el( 'a', { class: 'pro-badge', href: data.proBuyUrl, target: '_blank', rel: 'noopener', text: s.proBadge } );
	}

	function lockPro( node ) {
		if ( data.isPro ) {
			return node;
		}
		node.classList.add( 'pro-locked' );
		node.querySelectorAll( 'input, select, textarea, button' ).forEach( function ( input ) {
			input.disabled = true;
		} );
		return node;
	}

	function postForm( body ) {
		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( r ) { return r.json(); } );
	}

	var state = {
		mode: data.mode || 'off',
		selected: ( data.selected || [] ).slice(),
		blockNew: !! data.blockNew,
		visibility: data.visibility || 'none',
		dateRule: data.dateRule || 'between',
		dateFrom: data.dateFrom || '',
		dateTo: data.dateTo || '',
		dateBefore: data.dateBefore || '',
		delTypes: [],
		delStatuses: [],
		delPermanent: false,
		menuLocation: data.menuLocation || 'submenu',
		xmlrpcBlocked: !! data.xmlrpcBlocked,
		restBlocked: !! data.restBlocked,
		ai: {
			enabled: !! ( data.aiAssistant && data.aiAssistant.enabled ),
			autoPublish: !! ( data.aiAssistant && data.aiAssistant.auto_publish ),
			rules: ( data.aiAssistant && data.aiAssistant.rules ) ? data.aiAssistant.rules.slice() : [],
		},
	};

	var styleTag = el( 'style' );
	styleTag.textContent = STYLE;
	shadow.appendChild( styleTag );

	var card = el( 'div', { class: 'card main' } );
	var head = el( 'div', { class: 'card-head' }, [
		el( 'h1', { text: s.title } ),
		el( 'p', { class: 'intro', text: s.intro } ),
	] );

	// ---- Tabs shell: Disable, Delete, AI Assistant, General, Statistics --
	var tabsBar = el( 'div', { class: 'tabs' } );
	var panels  = el( 'div', { class: 'card-body' } );

	var disableTab = el( 'button', { class: 'tab active', type: 'button' }, [ document.createTextNode( s.tabDisable ) ] );
	var deleteTab  = el( 'button', { class: 'tab', type: 'button' }, [ document.createTextNode( s.tabDelete ) ] );
	var aiTab      = el( 'button', { class: 'tab ai-tab', type: 'button' }, [
		document.createTextNode( s.tabAI + ' ' ),
		el( 'span', { class: 'ai-badge', text: data.isPro ? s.aiBadge : s.proBadge } ),
	] );
	var generalTab = el( 'button', { class: 'tab', type: 'button' }, [ document.createTextNode( s.tabGeneral ) ] );
	var statsTab   = el( 'button', { class: 'tab', type: 'button' }, [ document.createTextNode( s.tabStatistics ) ] );

	[ disableTab, deleteTab, aiTab, generalTab, statsTab ].forEach( function ( t ) { tabsBar.appendChild( t ); } );

	// Delete/Statistics act on one site's wp_comments table — meaningless
	// from the Network Admin screen, so they're simply not shown there.
	if ( data.isNetworkAdmin ) {
		deleteTab.style.display = 'none';
		statsTab.style.display = 'none';
	}

	var disablePanel = el( 'div', { class: 'panel active' } );
	var deletePanel  = el( 'div', { class: 'panel' } );
	var aiPanel      = el( 'div', { class: 'panel' } );
	var generalPanel = el( 'div', { class: 'panel' } );
	var statsPanel   = el( 'div', { class: 'panel' } );
	[ disablePanel, deletePanel, aiPanel, generalPanel, statsPanel ].forEach( function ( p ) { panels.appendChild( p ); } );

	var allTabs   = [ disableTab, deleteTab, aiTab, generalTab, statsTab ];
	var allPanels = [ disablePanel, deletePanel, aiPanel, generalPanel, statsPanel ];

	function activateTab( tabBtn, panel ) {
		allTabs.forEach( function ( t ) { t.classList.remove( 'active' ); } );
		allPanels.forEach( function ( p ) { p.classList.remove( 'active' ); } );
		tabBtn.classList.add( 'active' );
		panel.classList.add( 'active' );
	}

	disableTab.addEventListener( 'click', function () { activateTab( disableTab, disablePanel ); } );
	deleteTab.addEventListener( 'click', function () { activateTab( deleteTab, deletePanel ); } );
	aiTab.addEventListener( 'click', function () { activateTab( aiTab, aiPanel ); } );
	generalTab.addEventListener( 'click', function () { activateTab( generalTab, generalPanel ); } );
	statsTab.addEventListener( 'click', function () { activateTab( statsTab, statsPanel ); loadStats(); } );

	// ======================================================================
	// Disable panel
	// ======================================================================
	var whereFieldset = el( 'fieldset' );
	var modeGroup = 'rb-dc-mode';
	var postTypesBox = el( 'div', { class: 'indent' } );

	function renderPostTypes() {
		postTypesBox.innerHTML = '';
		postTypesBox.classList.toggle( 'is-open', state.mode === 'selected' );
		Object.keys( data.postTypes || {} ).forEach( function ( slug ) {
			var checkbox = el( 'input', { type: 'checkbox', value: slug } );
			checkbox.checked = state.selected.indexOf( slug ) !== -1;
			checkbox.addEventListener( 'change', function () {
				var idx = state.selected.indexOf( slug );
				if ( checkbox.checked && idx === -1 ) { state.selected.push( slug ); }
				else if ( ! checkbox.checked && idx !== -1 ) { state.selected.splice( idx, 1 ); }
			} );
			postTypesBox.appendChild( el( 'label', { class: 'option' }, [ checkbox, el( 'span', { class: 'label', text: data.postTypes[ slug ] } ) ] ) );
		} );
	}

	function whereRow( value, label, helpKey, extraNode ) {
		var input = el( 'input', { type: 'radio', name: modeGroup, value: value } );
		input.checked = state.mode === value;
		input.addEventListener( 'change', function () { state.mode = value; renderPostTypes(); } );
		var row = withHelp( helpKey, el( 'label', { class: 'option' }, [ input, el( 'span', { class: 'label' }, [ el( 'strong', { text: label } ) ] ) ] ) );
		var wrap = el( 'div', {}, [ row ] );
		if ( extraNode ) { wrap.appendChild( extraNode ); }
		return wrap;
	}

	renderPostTypes();
	whereFieldset.appendChild( whereRow( 'all', s.modeAll, 'mode_all' ) );
	whereFieldset.appendChild( whereRow( 'selected', s.modeSelected, 'mode_selected', postTypesBox ) );
	whereFieldset.appendChild( whereRow( 'off', s.modeOff, 'mode_off' ) );

	var newFieldset = el( 'fieldset' );
	newFieldset.appendChild( el( 'legend', { text: s.newCommentsLegend } ) );
	var blockNewInput = el( 'input', { type: 'checkbox' } );
	blockNewInput.checked = state.blockNew;
	blockNewInput.addEventListener( 'change', function () { state.blockNew = blockNewInput.checked; } );
	newFieldset.appendChild( withHelp( 'block_new', el( 'label', { class: 'option' }, [ blockNewInput, el( 'span', { class: 'label', text: s.blockNewLabel } ) ] ) ) );

	// ---- Exceptions: roles that can always comment ------------------------
	var exceptFieldset = el( 'fieldset' );
	exceptFieldset.appendChild( el( 'legend', {}, [ document.createTextNode( s.exceptLegend + ' ' ), proBadge() ] ) );
	exceptFieldset.appendChild( el( 'p', { class: 'intro', text: s.exceptIntro } ) );
	state.exemptRoles = ( data.exemptRoles || [] ).slice();
	var exceptGrid = withHelp( 'exceptions', el( 'div', { class: 'checkgrid' } ) );
	Object.keys( data.availableRoles || {} ).forEach( function ( slug ) {
		var cb = el( 'input', { type: 'checkbox', value: slug } );
		cb.checked = state.exemptRoles.indexOf( slug ) !== -1;
		cb.addEventListener( 'change', function () {
			var idx = state.exemptRoles.indexOf( slug );
			if ( cb.checked && idx === -1 ) { state.exemptRoles.push( slug ); }
			else if ( ! cb.checked && idx !== -1 ) { state.exemptRoles.splice( idx, 1 ); }
		} );
		exceptGrid.appendChild( el( 'label', { class: 'option' }, [ cb, el( 'span', { class: 'label', text: data.availableRoles[ slug ] } ) ] ) );
	} );
	exceptFieldset.appendChild( exceptGrid );
	lockPro( exceptFieldset );

	var existingFieldset = el( 'fieldset' );
	existingFieldset.appendChild( el( 'legend', { text: s.existingLegend } ) );
	var visGroup = 'rb-dc-visibility';
	var dateBox = el( 'div', { class: 'indent' } );

	var ruleBetweenInput = el( 'input', { type: 'radio', name: 'rb-dc-date-rule', value: 'between' } );
	var ruleBeforeInput  = el( 'input', { type: 'radio', name: 'rb-dc-date-rule', value: 'before' } );
	ruleBetweenInput.checked = state.dateRule === 'between';
	ruleBeforeInput.checked  = state.dateRule === 'before';
	[ ruleBetweenInput, ruleBeforeInput ].forEach( function ( input ) {
		input.addEventListener( 'change', function () { if ( input.checked ) { state.dateRule = input.value; } } );
	} );

	var fromInput = el( 'input', { type: 'date' } ), toInput = el( 'input', { type: 'date' } ), beforeInput = el( 'input', { type: 'date' } );
	fromInput.value = state.dateFrom; toInput.value = state.dateTo; beforeInput.value = state.dateBefore;
	fromInput.addEventListener( 'change', function () { state.dateFrom = fromInput.value; } );
	toInput.addEventListener( 'change', function () { state.dateTo = toInput.value; } );
	beforeInput.addEventListener( 'change', function () { state.dateBefore = beforeInput.value; } );

	var betweenRow = withHelp( 'rule_between', el( 'div', {}, [
		el( 'label', { class: 'option' }, [ ruleBetweenInput, el( 'span', { class: 'label', text: s.ruleBetween } ) ] ),
		el( 'div', { class: 'date-row' }, [
			el( 'label', {}, [ document.createTextNode( s.dateFromLabel ), fromInput ] ),
			el( 'label', {}, [ document.createTextNode( s.dateToLabel ), toInput ] ),
		] ),
	] ) );
	var beforeRow = withHelp( 'rule_before', el( 'div', {}, [
		el( 'label', { class: 'option' }, [ ruleBeforeInput, el( 'span', { class: 'label', text: s.ruleBefore } ) ] ),
		el( 'div', { class: 'date-row' }, [ el( 'label', {}, [ document.createTextNode( s.dateBeforeLabel ), beforeInput ] ) ] ),
	] ) );
	dateBox.appendChild( betweenRow );
	dateBox.appendChild( beforeRow );

	function visRow( value, label, helpKey, extraNode, isPro ) {
		var input = el( 'input', { type: 'radio', name: visGroup, value: value } );
		input.checked = state.visibility === value;
		input.addEventListener( 'change', function () { state.visibility = value; dateBox.classList.toggle( 'is-open', state.visibility === 'date_range' ); } );
		var labelChildren = [ el( 'strong', { text: label } ) ];
		if ( isPro ) { labelChildren.push( proBadge() ); }
		var row = withHelp( helpKey, el( 'label', { class: 'option' }, [ input, el( 'span', { class: isPro ? 'label has-badge' : 'label' }, labelChildren ) ] ) );
		var wrap = el( 'div', {}, [ row ] );
		if ( extraNode ) { wrap.appendChild( extraNode ); }
		if ( isPro ) { lockPro( wrap ); }
		return wrap;
	}

	dateBox.classList.toggle( 'is-open', state.visibility === 'date_range' );
	existingFieldset.appendChild( visRow( 'all', s.visAll, 'vis_all' ) );
	existingFieldset.appendChild( visRow( 'none', s.visNone, 'vis_none' ) );
	existingFieldset.appendChild( visRow( 'date_range', s.visDateRange, 'vis_date_range', dateBox, true ) );

	disablePanel.appendChild( whereFieldset );
	disablePanel.appendChild( newFieldset );
	disablePanel.appendChild( exceptFieldset );
	disablePanel.appendChild( existingFieldset );

	var saveStatus = el( 'span', { class: 'status' } );
	var saveBtn = el( 'button', { class: 'save', type: 'button', text: s.save } );
	saveBtn.addEventListener( 'click', function () {
		saveBtn.disabled = true;
		saveStatus.textContent = '';
		saveStatus.className = 'status';

		var body = new URLSearchParams();
		body.set( 'action', 'rb_disable_comments_save' );
		body.set( 'nonce', data.nonce );
		body.set( 'mode', state.mode );
		state.selected.forEach( function ( slug ) { body.append( 'post_types[]', slug ); } );
		body.set( 'block_new', state.blockNew ? '1' : '' );
		body.set( 'existing_visibility', state.visibility );
		body.set( 'date_rule', state.dateRule );
		body.set( 'date_from', state.dateFrom );
		body.set( 'date_to', state.dateTo );
		body.set( 'date_before', state.dateBefore );
		state.exemptRoles.forEach( function ( slug ) { body.append( 'exempt_roles[]', slug ); } );

		postForm( body ).then( function ( json ) {
			saveBtn.disabled = false;
			if ( json && json.success ) {
				saveStatus.textContent = s.saved;
				saveStatus.className = 'status ok';
			} else {
				saveStatus.textContent = s.error;
				saveStatus.className = 'status err';
			}
		} ).catch( function () {
			saveBtn.disabled = false;
			saveStatus.textContent = s.error;
			saveStatus.className = 'status err';
		} );
	} );
	var saveActionsRow = el( 'div', { class: 'actions' }, [ saveBtn, saveStatus ] );
	disablePanel.appendChild( saveActionsRow );

	// ---- Multisite: locked notice (per-site) / enforcement toggle (network admin) --
	if ( data.enforced && ! data.isNetworkAdmin ) {
		[ whereFieldset, newFieldset, exceptFieldset, existingFieldset ].forEach( function ( container ) {
			container.querySelectorAll( 'input, select, textarea, button' ).forEach( function ( node ) { node.disabled = true; } );
		} );
		saveActionsRow.style.display = 'none';
		disablePanel.insertBefore(
			el( 'div', { class: 'locked-banner' }, [
				el( 'strong', { text: s.lockedTitle } ),
				el( 'p', { text: s.lockedBody } ),
			] ),
			disablePanel.firstChild
		);
	}

	if ( data.isNetworkAdmin ) {
		disablePanel.insertBefore( el( 'p', { class: 'intro', text: s.netIntro } ), disablePanel.firstChild );

		var enforceFieldset = el( 'fieldset' );
		enforceFieldset.appendChild( el( 'legend', {}, [ document.createTextNode( s.netEnforceLegend + ' ' ), proBadge() ] ) );
		var enforceInput = el( 'input', { type: 'checkbox' } );
		enforceInput.checked = !! data.sitewideEnforce;
		var enforceStatus = el( 'span', { class: 'status' } );
		var enforceSaveBtn = el( 'button', { class: 'secondary', type: 'button', text: s.netSave } );
		enforceSaveBtn.addEventListener( 'click', function () {
			enforceSaveBtn.disabled = true;
			enforceStatus.textContent = ''; enforceStatus.className = 'status';
			var body = new URLSearchParams();
			body.set( 'action', 'rb_disable_comments_save_network_enforce' );
			body.set( 'nonce', data.nonce );
			body.set( 'enforce', enforceInput.checked ? '1' : '' );
			postForm( body ).then( function ( json ) {
				enforceSaveBtn.disabled = false;
				if ( json && json.success ) {
					enforceStatus.textContent = s.saved; enforceStatus.className = 'status ok';
				} else {
					enforceStatus.textContent = s.error; enforceStatus.className = 'status err';
				}
			} ).catch( function () {
				enforceSaveBtn.disabled = false;
				enforceStatus.textContent = s.error; enforceStatus.className = 'status err';
			} );
		} );
		enforceFieldset.appendChild( withHelp( 'net_enforce', el( 'label', { class: 'option' }, [ enforceInput, el( 'span', { class: 'label', text: s.netEnforceLabel } ) ] ) ) );
		enforceFieldset.appendChild( el( 'div', { class: 'actions' }, [ enforceSaveBtn, enforceStatus ] ) );
		disablePanel.appendChild( lockPro( enforceFieldset ) );
	}


	// ======================================================================
	// Delete panel
	// ======================================================================
	deletePanel.appendChild( el( 'p', { class: 'intro', text: s.delIntro } ) );

	var delTypesFieldset = el( 'fieldset' );
	delTypesFieldset.appendChild( el( 'legend', { text: s.delTypesLegend } ) );
	var delTypesGrid = withHelp( 'del_types', el( 'div', { class: 'checkgrid' } ) );
	Object.keys( data.postTypes || {} ).forEach( function ( slug ) {
		var cb = el( 'input', { type: 'checkbox', value: slug } );
		cb.addEventListener( 'change', function () {
			var idx = state.delTypes.indexOf( slug );
			if ( cb.checked && idx === -1 ) { state.delTypes.push( slug ); }
			else if ( ! cb.checked && idx !== -1 ) { state.delTypes.splice( idx, 1 ); }
		} );
		delTypesGrid.appendChild( el( 'label', { class: 'option' }, [ cb, el( 'span', { class: 'label', text: data.postTypes[ slug ] } ) ] ) );
	} );
	delTypesFieldset.appendChild( delTypesGrid );

	var delStatusFieldset = el( 'fieldset' );
	delStatusFieldset.appendChild( el( 'legend', { text: s.delStatusLegend } ) );
	var delStatusGrid = withHelp( 'del_status', el( 'div', { class: 'checkgrid' } ) );
	[ [ 'approve', s.delApprove ], [ 'hold', s.delHold ], [ 'spam', s.delSpam ], [ 'trash', s.delTrash ] ].forEach( function ( pair ) {
		var value = pair[0], label = pair[1];
		var cb = el( 'input', { type: 'checkbox', value: value } );
		cb.addEventListener( 'change', function () {
			var idx = state.delStatuses.indexOf( value );
			if ( cb.checked && idx === -1 ) { state.delStatuses.push( value ); }
			else if ( ! cb.checked && idx !== -1 ) { state.delStatuses.splice( idx, 1 ); }
		} );
		delStatusGrid.appendChild( el( 'label', { class: 'option' }, [ cb, el( 'span', { class: 'label', text: label } ) ] ) );
	} );
	delStatusFieldset.appendChild( delStatusGrid );

	// ---- Optional date filter (PRO) ------------------------------------------
	// Same look as the Disable tab's date options, but every mode is always
	// visible — you pick a rule (Any / Between / Before / After) and fill in
	// its date(s). Touching a date input auto-selects its rule, so you never
	// have to click a radio first and then a calendar.
	state.delDateRule   = 'any';
	state.delDateFrom   = '';
	state.delDateTo     = '';
	state.delDateBefore = '';
	state.delDateAfter  = '';

	var delDateFieldset = withHelp( 'del_date', el( 'fieldset' ) );
	delDateFieldset.appendChild( el( 'legend', {}, [ document.createTextNode( s.delDateLegend + ' ' ), proBadge() ] ) );
	var delDateGroup = 'rb-dc-del-date-rule';
	var delRadios = {};

	function delRadio( rule ) {
		var input = el( 'input', { type: 'radio', name: delDateGroup, value: rule } );
		input.checked = rule === 'any';
		input.addEventListener( 'change', function () { if ( input.checked ) { state.delDateRule = rule; } } );
		delRadios[ rule ] = input;
		return input;
	}

	function delDateInput( rule, stateKey ) {
		var input = el( 'input', { type: 'date' } );
		input.addEventListener( 'change', function () {
			state[ stateKey ] = input.value;
			if ( input.value && delRadios[ rule ] ) {
				delRadios[ rule ].checked = true;
				state.delDateRule = rule;
			}
		} );
		return input;
	}

	function delDateOption( rule, labelText, fields ) {
		var wrap = el( 'div', {}, [
			el( 'label', { class: 'option' }, [ delRadio( rule ), el( 'span', { class: 'label', text: labelText } ) ] ),
		] );
		if ( fields.length ) {
			wrap.appendChild( el( 'div', { class: 'date-row' }, fields.map( function ( f ) {
				return el( 'label', {}, [ document.createTextNode( f[0] ), delDateInput( rule, f[1] ) ] );
			} ) ) );
		}
		return wrap;
	}

	delDateFieldset.appendChild( delDateOption( 'any', s.delDateAny, [] ) );
	delDateFieldset.appendChild( delDateOption( 'between', s.delRuleBetween, [ [ s.dateFromLabel, 'delDateFrom' ], [ s.dateToLabel, 'delDateTo' ] ] ) );
	delDateFieldset.appendChild( delDateOption( 'before', s.delRuleBefore, [ [ s.dateBeforeLabel, 'delDateBefore' ] ] ) );
	delDateFieldset.appendChild( delDateOption( 'after', s.delRuleAfter, [ [ s.dateAfterLabel, 'delDateAfter' ] ] ) );
	lockPro( delDateFieldset );

	// ---- WooCommerce: filter by comment type (regular comments vs product reviews) ----
	state.delCommentType = '';
	var delTypeFieldset = null;
	if ( data.wooActive ) {
		delTypeFieldset = withHelp( 'del_type', el( 'fieldset' ) );
		delTypeFieldset.appendChild( el( 'legend', {}, [ document.createTextNode( s.delTypeLegend + ' ' ), proBadge() ] ) );
		var delCtGroup = 'rb-dc-del-comment-type';
		[ [ '', s.delTypeAll ], [ 'comment', s.delTypeComments ], [ 'review', s.delTypeReviews ] ].forEach( function ( pair, idx ) {
			var input = el( 'input', { type: 'radio', name: delCtGroup, value: pair[0] } );
			input.checked = idx === 0;
			input.addEventListener( 'change', function () { if ( input.checked ) { state.delCommentType = pair[0]; } } );
			delTypeFieldset.appendChild( el( 'label', { class: 'option' }, [ input, el( 'span', { class: 'label', text: pair[1] } ) ] ) );
		} );
		lockPro( delTypeFieldset );
	}

	var permanentInput = el( 'input', { type: 'checkbox' } );
	permanentInput.addEventListener( 'change', function () { state.delPermanent = permanentInput.checked; } );
	var permanentRow = withHelp( 'del_permanent', el( 'label', { class: 'option' }, [ permanentInput, el( 'span', { class: 'label', text: s.delPermanent } ) ] ) );

	deletePanel.appendChild( delTypesFieldset );
	deletePanel.appendChild( delStatusFieldset );
	if ( delTypeFieldset ) { deletePanel.appendChild( delTypeFieldset ); }
	deletePanel.appendChild( delDateFieldset );
	deletePanel.appendChild( el( 'fieldset', {}, [ permanentRow ] ) );

	var delStatus = el( 'span', { class: 'status' } );
	var countBtn = el( 'button', { class: 'secondary', type: 'button', text: s.delCount } );
	var deleteBtn = el( 'button', { class: 'danger', type: 'button', text: s.delButton } );
	var lastCount = null;

	function buildDeleteBody( action ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', data.nonce );
		state.delTypes.forEach( function ( t ) { body.append( 'post_types[]', t ); } );
		state.delStatuses.forEach( function ( st ) { body.append( 'statuses[]', st ); } );
		body.set( 'permanent', state.delPermanent ? '1' : '' );
		if ( state.delCommentType ) {
			body.set( 'comment_type', state.delCommentType );
		}
		if ( state.delDateRule !== 'any' ) {
			body.set( 'date_rule', state.delDateRule );
			body.set( 'date_from', state.delDateFrom );
			body.set( 'date_to', state.delDateTo );
			body.set( 'date_before', state.delDateBefore );
			body.set( 'date_after', state.delDateAfter );
		}
		return body;
	}

	countBtn.addEventListener( 'click', function () {
		if ( ! state.delTypes.length || ! state.delStatuses.length ) {
			delStatus.textContent = s.delError; delStatus.className = 'status err';
			return;
		}
		countBtn.disabled = true;
		delStatus.textContent = s.delCounting; delStatus.className = 'status';

		postForm( buildDeleteBody( 'rb_disable_comments_count' ) ).then( function ( json ) {
			countBtn.disabled = false;
			if ( json && json.success ) {
				lastCount = json.data.count;
				delStatus.textContent = s.delCountResult.replace( '%d', lastCount );
				delStatus.className = 'status ok';
			} else {
				delStatus.textContent = ( json && json.data && json.data.message ) ? json.data.message : s.error;
				delStatus.className = 'status err';
			}
		} ).catch( function () {
			countBtn.disabled = false;
			delStatus.textContent = s.error; delStatus.className = 'status err';
		} );
	} );

	deleteBtn.addEventListener( 'click', function () {
		if ( ! state.delTypes.length || ! state.delStatuses.length ) {
			delStatus.textContent = s.delError; delStatus.className = 'status err';
			return;
		}
		var confirmMsg = state.delPermanent
			? s.delConfirm.replace( '%d', lastCount !== null ? lastCount : '?' )
			: s.delTrashConfirm.replace( '%d', lastCount !== null ? lastCount : '?' );
		if ( ! window.confirm( confirmMsg ) ) { return; }

		deleteBtn.disabled = true;
		delStatus.textContent = s.delDeleting; delStatus.className = 'status';

		postForm( buildDeleteBody( 'rb_disable_comments_delete' ) ).then( function ( json ) {
			deleteBtn.disabled = false;
			if ( json && json.success ) {
				delStatus.textContent = s.delDone.replace( '%d', json.data.deleted );
				delStatus.className = 'status ok';
				lastCount = 0;
			} else {
				delStatus.textContent = ( json && json.data && json.data.message ) ? json.data.message : s.error;
				delStatus.className = 'status err';
			}
		} ).catch( function () {
			deleteBtn.disabled = false;
			delStatus.textContent = s.error; delStatus.className = 'status err';
		} );
	} );

	deletePanel.appendChild( el( 'div', { class: 'actions' }, [ countBtn, deleteBtn, delStatus ] ) );

	// ======================================================================
	// AI Assistant panel
	// ======================================================================
	aiPanel.appendChild( el( 'p', { class: 'intro', text: s.aiIntro } ) );

	if ( ! data.isPro ) {
		aiPanel.appendChild( el( 'div', { class: 'pro-banner' }, [
			el( 'div', { class: 'pro-banner-text' }, [
				el( 'strong', { text: s.proLockedTitle } ),
				document.createTextNode( s.proCardIntro ),
			] ),
			el( 'a', { class: 'pro-buy-btn', href: data.proBuyUrl, target: '_blank', rel: 'noopener', text: s.proBuyBtn } ),
		] ) );
	}

	var aiToggleFieldset = el( 'fieldset' );
	var aiEnabledInput = el( 'input', { type: 'checkbox' } );
	aiEnabledInput.checked = state.ai.enabled;
	aiEnabledInput.addEventListener( 'change', function () { state.ai.enabled = aiEnabledInput.checked; } );
	aiToggleFieldset.appendChild( withHelp( 'ai_enable', el( 'label', { class: 'option' }, [ aiEnabledInput, el( 'span', { class: 'label', text: s.aiEnable } ) ] ) ) );

	var aiAutoPublishInput = el( 'input', { type: 'checkbox' } );
	aiAutoPublishInput.checked = state.ai.autoPublish;
	aiAutoPublishInput.addEventListener( 'change', function () { state.ai.autoPublish = aiAutoPublishInput.checked; } );
	aiToggleFieldset.appendChild( withHelp( 'ai_auto_publish', el( 'label', { class: 'option' }, [ aiAutoPublishInput, el( 'span', { class: 'label', text: s.aiAutoPublish } ) ] ) ) );
	var aiLockable = el( 'div' );
	aiLockable.appendChild( aiToggleFieldset );

	var aiRulesFieldset = el( 'fieldset' );
	aiRulesFieldset.appendChild( el( 'legend', { text: s.aiRulesLegend } ) );

	// ---- Preset generator: pick a scenario, get a ready-made example
	// rule to start from. Adds to the list rather than replacing it, so
	// picking two presets in a row just gives two starting rules.
	var AI_PRESETS = {
		sales: {
			label: 'Sales',
			rules: [ {
				keywords: 'price, cost, buy, purchase, order, discount',
				tone: 'friendly',
				template: 'Hi {name}, thanks for asking! You can see current pricing and place an order here: {link}',
				link: '', link_label: 'Pricing page',
			} ],
		},
		support: {
			label: 'Support',
			rules: [ {
				keywords: 'help, issue, problem, broken, not working, bug',
				tone: 'professional',
				template: 'Hi {name}, sorry you\'re running into that. Please reach out to our support team so we can look into it: {link}',
				link: '', link_label: 'Support',
			} ],
		},
		availability: {
			label: 'Availability / Shipping',
			rules: [ {
				keywords: 'availability, in stock, shipping, delivery, when will',
				tone: 'friendly',
				template: 'Hi {name}, great question! Check current stock and delivery estimates here: {link}',
				link: '', link_label: 'Availability',
			} ],
		},
		complaint: {
			label: 'Complaints',
			rules: [ {
				keywords: 'refund, complaint, disappointed, unhappy, cancel',
				tone: 'professional',
				template: 'Hi {name}, we\'re sorry to hear that. Please contact us directly so we can make this right: {link}',
				link: '', link_label: 'Contact us',
			} ],
		},
	};
	if ( data.wooActive ) {
		AI_PRESETS.negative_review = {
			label: 'Negative product reviews',
			rules: [ {
				keywords: '',
				tone: 'professional',
				template: 'Hi {name}, we\'re sorry your experience wasn\'t great. We\'d like to make it right — please reach out here: {link}',
				link: '', link_label: 'Contact us',
				scope: 'reviews_only',
				wc_rating_max: 2,
			} ],
		};
	}

	var presetSelect = el( 'select' );
	Object.keys( AI_PRESETS ).forEach( function ( key ) {
		presetSelect.appendChild( el( 'option', { value: key, text: AI_PRESETS[ key ].label } ) );
	} );
	var presetAddBtn = el( 'button', { class: 'text-btn', type: 'button', text: s.aiPresetAdd } );
	presetAddBtn.addEventListener( 'click', function () {
		var preset = AI_PRESETS[ presetSelect.value ];
		if ( ! preset ) { return; }
		preset.rules.forEach( function ( r ) { state.ai.rules.push( Object.assign( {}, r ) ); } );
		renderRulesList();
	} );
	var presetRow = el( 'div', { class: 'preset-row' }, [
		el( 'label', { text: s.aiPresetLabel } ),
		presetSelect,
		presetAddBtn,
	] );
	aiRulesFieldset.appendChild( presetRow );

	var rulesList = el( 'div' );
	aiRulesFieldset.appendChild( rulesList );

	function field( labelText, inputNode, helpKey ) {
		var wrap = el( 'div', { class: 'field' }, [ el( 'label', { text: labelText } ), inputNode ] );
		return helpKey ? withHelp( helpKey, wrap ) : wrap;
	}

	function renderRule( rule, index ) {
		var keywordsInput = el( 'input', { type: 'text', value: rule.keywords || '' } );
		var toneSelect = el( 'select' );
		[ [ 'friendly', s.aiToneFriendly ], [ 'professional', s.aiToneProfessional ], [ 'casual', s.aiToneCasual ] ].forEach( function ( pair ) {
			var opt = el( 'option', { value: pair[0], text: pair[1] } );
			if ( rule.tone === pair[0] ) { opt.selected = true; }
			toneSelect.appendChild( opt );
		} );
		var templateInput = el( 'textarea' );
		templateInput.value = rule.template || '';
		var linkInput = el( 'input', { type: 'url', value: rule.link || '', placeholder: 'https://…' } );
		var linkLabelInput = el( 'input', { type: 'text', value: rule.link_label || '' } );

		keywordsInput.addEventListener( 'input', function () { rule.keywords = keywordsInput.value; } );
		toneSelect.addEventListener( 'change', function () { rule.tone = toneSelect.value; } );
		templateInput.addEventListener( 'input', function () { rule.template = templateInput.value; } );
		linkInput.addEventListener( 'input', function () { rule.link = linkInput.value; } );
		linkLabelInput.addEventListener( 'input', function () { rule.link_label = linkLabelInput.value; } );

		var removeBtn = el( 'button', { class: 'text-btn', type: 'button', text: s.aiRemoveRule } );
		removeBtn.addEventListener( 'click', function () {
			var idx = state.ai.rules.indexOf( rule );
			if ( idx !== -1 ) { state.ai.rules.splice( idx, 1 ); }
			renderRulesList();
		} );

		var rows = [
			el( 'div', { class: 'rule-card-head' }, [ el( 'span', { text: '#' + ( index + 1 ) } ), removeBtn ] ),
			field( s.aiKeywords, keywordsInput, 'ai_keywords' ),
			el( 'div', { class: 'rule-grid' }, [
				field( s.aiTone, toneSelect ),
				field( s.aiLink, linkInput ),
			] ),
			field( s.aiTemplate, templateInput, 'ai_template' ),
			field( s.aiLinkLabel, linkLabelInput ),
		];

		// WooCommerce: scope this rule to reviews/comments and optionally
		// require a low star rating. Only shown when WooCommerce is
		// active — the fields are meaningless (scope='any', rating
		// ignored) on a plain blog.
		if ( data.wooActive ) {
			var scopeSelect = el( 'select' );
			[ [ 'any', s.aiScopeAny ], [ 'reviews_only', s.aiScopeReviews ], [ 'comments_only', s.aiScopeComments ] ].forEach( function ( pair ) {
				var opt = el( 'option', { value: pair[0], text: pair[1] } );
				if ( ( rule.scope || 'any' ) === pair[0] ) { opt.selected = true; }
				scopeSelect.appendChild( opt );
			} );
			scopeSelect.addEventListener( 'change', function () { rule.scope = scopeSelect.value; } );

			var ratingInput = el( 'input', { type: 'number', min: '0', max: '5', step: '1', value: String( rule.wc_rating_max || 0 ) } );
			ratingInput.addEventListener( 'input', function () { rule.wc_rating_max = parseInt( ratingInput.value, 10 ) || 0; } );

			rows.push( el( 'div', { class: 'rule-grid' }, [
				field( s.aiScopeLabel, scopeSelect, 'ai_scope' ),
				field( s.aiRatingLabel, ratingInput, 'ai_rating' ),
			] ) );
		}

		return el( 'div', { class: 'rule-card' }, rows );
	}

	function renderRulesList() {
		rulesList.innerHTML = '';
		state.ai.rules.forEach( function ( rule, index ) {
			rulesList.appendChild( renderRule( rule, index ) );
		} );
	}
	renderRulesList();

	var addRuleBtn = el( 'button', { class: 'text-btn', type: 'button', text: s.aiAddRule } );
	addRuleBtn.addEventListener( 'click', function () {
		state.ai.rules.push( { keywords: '', tone: 'friendly', template: '', link: '', link_label: '', scope: 'any', wc_rating_max: 0 } );
		renderRulesList();
	} );
	aiRulesFieldset.appendChild( addRuleBtn );
	aiLockable.appendChild( aiRulesFieldset );

	var aiStatus = el( 'span', { class: 'status' } );
	var aiSaveBtn = el( 'button', { class: 'save', type: 'button', text: s.aiSave } );
	aiSaveBtn.addEventListener( 'click', function () {
		aiSaveBtn.disabled = true;
		aiStatus.textContent = ''; aiStatus.className = 'status';

		var body = new URLSearchParams();
		body.set( 'action', 'rb_disable_comments_save_ai' );
		body.set( 'nonce', data.nonce );
		body.set( 'ai_enabled', state.ai.enabled ? '1' : '' );
		body.set( 'ai_auto_publish', state.ai.autoPublish ? '1' : '' );
		state.ai.rules.forEach( function ( rule ) {
			body.append( 'rules_keywords[]', rule.keywords || '' );
			body.append( 'rules_tone[]', rule.tone || 'friendly' );
			body.append( 'rules_template[]', rule.template || '' );
			body.append( 'rules_link[]', rule.link || '' );
			body.append( 'rules_link_label[]', rule.link_label || '' );
			body.append( 'rules_scope[]', rule.scope || 'any' );
			body.append( 'rules_wc_rating_max[]', String( rule.wc_rating_max || 0 ) );
		} );

		postForm( body ).then( function ( json ) {
			aiSaveBtn.disabled = false;
			if ( json && json.success ) {
				aiStatus.textContent = s.saved; aiStatus.className = 'status ok';
			} else {
				aiStatus.textContent = s.error; aiStatus.className = 'status err';
			}
		} ).catch( function () {
			aiSaveBtn.disabled = false;
			aiStatus.textContent = s.error; aiStatus.className = 'status err';
		} );
	} );
	aiLockable.appendChild( el( 'div', { class: 'actions' }, [ aiSaveBtn, aiStatus ] ) );
	aiPanel.appendChild( lockPro( aiLockable ) );

	// ======================================================================
	// General settings panel
	// ======================================================================
	var genFieldset = el( 'fieldset' );
	genFieldset.appendChild( el( 'legend', { text: s.genMenuLegend } ) );
	var menuGroup = 'rb-dc-menu-location';
	var subInput = el( 'input', { type: 'radio', name: menuGroup, value: 'submenu' } );
	var topInput = el( 'input', { type: 'radio', name: menuGroup, value: 'top' } );
	subInput.checked = state.menuLocation !== 'top';
	topInput.checked = state.menuLocation === 'top';
	subInput.addEventListener( 'change', function () { if ( subInput.checked ) { state.menuLocation = 'submenu'; } } );
	topInput.addEventListener( 'change', function () { if ( topInput.checked ) { state.menuLocation = 'top'; } } );

	var menuRow = withHelp( 'gen_menu', el( 'div', {}, [
		el( 'label', { class: 'option' }, [ subInput, el( 'span', { class: 'label', text: s.genMenuSub } ) ] ),
		el( 'label', { class: 'option' }, [ topInput, el( 'span', { class: 'label', text: s.genMenuTop } ) ] ),
	] ) );
	genFieldset.appendChild( menuRow );
	generalPanel.appendChild( genFieldset );

	var apiFieldset = el( 'fieldset' );
	apiFieldset.appendChild( el( 'legend', { text: s.genApiLegend } ) );
	var xmlrpcInput = el( 'input', { type: 'checkbox' } );
	xmlrpcInput.checked = state.xmlrpcBlocked;
	xmlrpcInput.addEventListener( 'change', function () { state.xmlrpcBlocked = xmlrpcInput.checked; } );
	apiFieldset.appendChild( withHelp( 'gen_xmlrpc', el( 'label', { class: 'option' }, [ xmlrpcInput, el( 'span', { class: 'label', text: s.genXmlrpc } ) ] ) ) );

	var restInput = el( 'input', { type: 'checkbox' } );
	restInput.checked = state.restBlocked;
	restInput.addEventListener( 'change', function () { state.restBlocked = restInput.checked; } );
	apiFieldset.appendChild( withHelp( 'gen_rest', el( 'label', { class: 'option' }, [ restInput, el( 'span', { class: 'label', text: s.genRest } ) ] ) ) );
	generalPanel.appendChild( apiFieldset );

	// ---- WP-CLI ------------------------------------------------------------
	state.cliEnabled = !! data.cliEnabled;
	var cliFieldset = el( 'fieldset' );
	cliFieldset.appendChild( el( 'legend', {}, [ document.createTextNode( s.genCliLegend + ' ' ), proBadge() ] ) );
	var cliInput = el( 'input', { type: 'checkbox' } );
	cliInput.checked = state.cliEnabled;
	var cliInstructions = el( 'div', { class: 'cli-instructions' } );
	function renderCliInstructions() {
		cliInstructions.innerHTML = '';
		if ( ! data.isPro ) {
			cliInstructions.appendChild( el( 'p', { class: 'status' }, [
				document.createTextNode( s.genCliPro + ' ' ),
				el( 'a', { href: data.proBuyUrl, target: '_blank', rel: 'noopener', text: s.proBuyBtn } ),
			] ) );
			return;
		}
		if ( ! state.cliEnabled ) {
			cliInstructions.appendChild( el( 'p', { class: 'status', text: s.genCliOff } ) );
			return;
		}
		cliInstructions.appendChild( el( 'p', { class: 'intro', text: s.genCliIntro } ) );
		var CLI_COMMANDS = [
			'wp disable-comments-rb status',
			'wp disable-comments-rb set all',
			'wp disable-comments-rb set selected --types=post,page',
			'wp disable-comments-rb set off',
			'wp disable-comments-rb stats',
			'wp disable-comments-rb delete --types=post,page --statuses=spam,trash --permanent --yes',
			'wp disable-comments-rb delete --types=post --statuses=approve --date-rule=before --before=2024-01-01',
		];
		var pre = el( 'pre', { class: 'cli-commands' } );
		pre.textContent = CLI_COMMANDS.join( '\n' );
		cliInstructions.appendChild( pre );
	}
	cliInput.addEventListener( 'change', function () { state.cliEnabled = cliInput.checked; renderCliInstructions(); } );
	cliFieldset.appendChild( withHelp( 'gen_cli', el( 'label', { class: 'option' }, [ cliInput, el( 'span', { class: 'label', text: s.genCliLabel } ) ] ) ) );
	renderCliInstructions();
	cliFieldset.appendChild( cliInstructions );
	generalPanel.appendChild( lockPro( cliFieldset ) );

	// ---- Dashboard widget toggle -------------------------------------------
	state.showDashboardWidget = !! data.showDashboardWidget;
	var dashFieldset = el( 'fieldset' );
	dashFieldset.appendChild( el( 'legend', { text: s.genDashLegend } ) );
	var dashInput = el( 'input', { type: 'checkbox' } );
	dashInput.checked = state.showDashboardWidget;
	dashInput.addEventListener( 'change', function () { state.showDashboardWidget = dashInput.checked; } );
	dashFieldset.appendChild( withHelp( 'gen_dashboard', el( 'label', { class: 'option' }, [ dashInput, el( 'span', { class: 'label', text: s.genDashLabel } ) ] ) ) );
	generalPanel.appendChild( dashFieldset );

	// ---- Comments-page shortcut button toggle -------------------------------
	state.showCommentsPageButton = !! data.showCommentsPageButton;
	var commentsBtnFieldset = el( 'fieldset' );
	commentsBtnFieldset.appendChild( el( 'legend', { text: s.genCommentsBtnLegend } ) );
	var commentsBtnInput = el( 'input', { type: 'checkbox' } );
	commentsBtnInput.checked = state.showCommentsPageButton;
	commentsBtnInput.addEventListener( 'change', function () { state.showCommentsPageButton = commentsBtnInput.checked; } );
	commentsBtnFieldset.appendChild( withHelp( 'gen_comments_btn', el( 'label', { class: 'option' }, [ commentsBtnInput, el( 'span', { class: 'label', text: s.genCommentsBtnLabel } ) ] ) ) );
	generalPanel.appendChild( commentsBtnFieldset );

	// ---- Admin toolbar shortcut toggle ---------------------------------------
	state.showAdminBarMenu = !! data.showAdminBarMenu;
	var adminBarFieldset = el( 'fieldset' );
	adminBarFieldset.appendChild( el( 'legend', { text: s.genAdminBarLegend } ) );
	var adminBarInput = el( 'input', { type: 'checkbox' } );
	adminBarInput.checked = state.showAdminBarMenu;
	adminBarInput.addEventListener( 'change', function () { state.showAdminBarMenu = adminBarInput.checked; } );
	adminBarFieldset.appendChild( withHelp( 'gen_admin_bar', el( 'label', { class: 'option' }, [ adminBarInput, el( 'span', { class: 'label', text: s.genAdminBarLabel } ) ] ) ) );
	generalPanel.appendChild( adminBarFieldset );

	var genStatus = el( 'span', { class: 'status' } );
	var genSaveBtn = el( 'button', { class: 'save', type: 'button', text: s.genSave } );
	genSaveBtn.addEventListener( 'click', function () {
		genSaveBtn.disabled = true;
		genStatus.textContent = ''; genStatus.className = 'status';

		var body = new URLSearchParams();
		body.set( 'action', 'rb_disable_comments_save_general' );
		body.set( 'nonce', data.nonce );
		body.set( 'menu_location', state.menuLocation );
		body.set( 'remove_xmlrpc_comments', state.xmlrpcBlocked ? '1' : '' );
		body.set( 'remove_rest_comments', state.restBlocked ? '1' : '' );
		body.set( 'cli_enabled', state.cliEnabled ? '1' : '' );
		body.set( 'show_dashboard_widget', state.showDashboardWidget ? '1' : '' );
		body.set( 'show_comments_page_button', state.showCommentsPageButton ? '1' : '' );
		body.set( 'show_admin_bar_menu', state.showAdminBarMenu ? '1' : '' );

		postForm( body ).then( function ( json ) {
			genSaveBtn.disabled = false;
			if ( json && json.success ) {
				genStatus.textContent = s.genSaved; genStatus.className = 'status ok';
			} else {
				genStatus.textContent = s.error; genStatus.className = 'status err';
			}
		} ).catch( function () {
			genSaveBtn.disabled = false;
			genStatus.textContent = s.error; genStatus.className = 'status err';
		} );
	} );
	generalPanel.appendChild( el( 'div', { class: 'actions' }, [ genSaveBtn, genStatus ] ) );

	// ---- Export / Import --------------------------------------------------
	var exportImportFieldset = withHelp( 'gen_export', el( 'fieldset' ) );
	exportImportFieldset.appendChild( el( 'legend', {}, [ document.createTextNode( s.genExportLegend + ' ' ), proBadge() ] ) );

	var exportStatus = el( 'span', { class: 'status' } );
	var exportBtn = el( 'button', { class: 'secondary', type: 'button', text: s.genExportBtn } );
	exportBtn.addEventListener( 'click', function () {
		exportBtn.disabled = true;
		var body = new URLSearchParams();
		body.set( 'action', 'rb_disable_comments_export' );
		body.set( 'nonce', data.nonce );
		postForm( body ).then( function ( json ) {
			exportBtn.disabled = false;
			if ( ! json || ! json.success ) {
				exportStatus.textContent = s.error; exportStatus.className = 'status err';
				return;
			}
			var blob = new Blob( [ JSON.stringify( json.data, null, 2 ) ], { type: 'application/json' } );
			var url = URL.createObjectURL( blob );
			var a = document.createElement( 'a' );
			a.href = url;
			a.download = 'disable-comments-rb-settings.json';
			a.click();
			URL.revokeObjectURL( url );
		} ).catch( function () {
			exportBtn.disabled = false;
			exportStatus.textContent = s.error; exportStatus.className = 'status err';
		} );
	} );

	var importInput = el( 'input', { type: 'file', accept: 'application/json' } );
	importInput.style.display = 'none';
	var importChooseBtn = el( 'button', { class: 'secondary', type: 'button', text: s.genImportChoose } );
	importChooseBtn.addEventListener( 'click', function () { importInput.click(); } );

	var importStatus = el( 'span', { class: 'status' } );
	importInput.addEventListener( 'change', function () {
		var file = importInput.files && importInput.files[0];
		if ( ! file ) { return; }
		var reader = new FileReader();
		reader.onload = function () {
			var body = new URLSearchParams();
			body.set( 'action', 'rb_disable_comments_import' );
			body.set( 'nonce', data.nonce );
			body.set( 'payload', String( reader.result ) );
			postForm( body ).then( function ( json ) {
				if ( json && json.success ) {
					importStatus.textContent = s.genImportDone; importStatus.className = 'status ok';
				} else {
					importStatus.textContent = ( json && json.data && json.data.message ) ? json.data.message : s.genImportError;
					importStatus.className = 'status err';
				}
			} ).catch( function () {
				importStatus.textContent = s.genImportError; importStatus.className = 'status err';
			} );
			importInput.value = '';
		};
		reader.readAsText( file );
	} );

	exportImportFieldset.appendChild( el( 'div', { class: 'actions' }, [ exportBtn, exportStatus ] ) );
	exportImportFieldset.appendChild( el( 'div', { class: 'actions' }, [ importChooseBtn, importInput, importStatus ] ) );
	generalPanel.appendChild( lockPro( exportImportFieldset ) );

	// ======================================================================
	// Statistics panel
	// ======================================================================
	var statsLoaded = false;
	function loadStats() {
		if ( statsLoaded ) { return; }
		statsPanel.innerHTML = '';
		statsPanel.appendChild( el( 'p', { class: 'intro', text: s.statsLoading } ) );

		var body = new URLSearchParams();
		body.set( 'action', 'rb_disable_comments_stats' );
		body.set( 'nonce', data.nonce );

		postForm( body ).then( function ( json ) {
			if ( ! json || ! json.success ) { throw new Error( 'bad response' ); }
			renderStats( json.data );
			statsLoaded = true;
		} ).catch( function () {
			statsPanel.innerHTML = '';
			statsPanel.appendChild( el( 'p', { class: 'status err', text: s.statsError } ) );
		} );
	}

	function pill( num, label ) {
		return el( 'div', { class: 'stat-pill' }, [ el( 'span', { class: 'num', text: String( num ) } ), el( 'span', { class: 'lbl', text: label } ) ] );
	}

	function statsSection( headingChildren, noteText, content ) {
		var section = el( 'div', { class: 'stats-section' } );
		section.appendChild( el( 'h3', { class: 'stats-heading' }, headingChildren ) );
		if ( noteText ) {
			section.appendChild( el( 'p', { class: 'stats-note', text: noteText } ) );
		}
		section.appendChild( content );
		return section;
	}

	function renderStats( payload ) {
		statsPanel.innerHTML = '';
		var totals = payload.totals || {};

		// Overview — the headline numbers.
		statsPanel.appendChild( statsSection(
			[ document.createTextNode( s.statsOverview ) ],
			s.statsIntroText,
			el( 'div', { class: 'stats-totals' }, [
				pill( ( totals.active || 0 ) + ( totals.pending || 0 ), s.statsTotal ),
				pill( totals.active || 0, s.statsActive ),
				pill( totals.pending || 0, s.statsPending ),
				pill( totals.spam || 0, s.statsSpam ),
				pill( totals.trash || 0, s.statsTrash ),
				pill( payload.deletedPermanently || 0, s.statsDeleted ),
			] )
		) );

		// By content type — one row per post type.
		var table = el( 'table', { class: 'stats-table' } );
		table.appendChild( el( 'tr', {}, [
			el( 'th', { text: '' } ), el( 'th', { text: s.statsActive } ), el( 'th', { text: s.statsPending } ),
			el( 'th', { text: s.statsSpam } ), el( 'th', { text: s.statsTrash } ),
		] ) );
		( payload.byType || [] ).forEach( function ( row ) {
			table.appendChild( el( 'tr', {}, [
				el( 'td', { text: row.label } ), el( 'td', { text: String( row.active || 0 ) } ),
				el( 'td', { text: String( row.pending || 0 ) } ), el( 'td', { text: String( row.spam || 0 ) } ),
				el( 'td', { text: String( row.trash || 0 ) } ),
			] ) );
		} );
		statsPanel.appendChild( statsSection( [ document.createTextNode( s.statsByType ) ], s.statsTableIntro, table ) );

		// WooCommerce product reviews (PRO).
		if ( payload.wooActive && payload.reviews ) {
			var r = payload.reviews;
			var reviewsSection = statsSection(
				data.isPro ? [ document.createTextNode( s.statsReviews ) ] : [ document.createTextNode( s.statsReviews + ' ' ), proBadge() ],
				'',
				el( 'div', { class: 'stats-totals' }, [
					pill( r.active || 0, s.statsActive ),
					pill( r.pending || 0, s.statsPending ),
					pill( r.spam || 0, s.statsSpam ),
					pill( r.trash || 0, s.statsTrash ),
				] )
			);
			statsPanel.appendChild( data.isPro ? reviewsSection : lockPro( reviewsSection ) );
		}
	}

	// ======================================================================
	// Help card — same visual style as the main card. Two mini-tabs:
	// Hints (fixed step outline + a box that updates from [data-help-key]
	// delegation) and FAQ (static, always-visible reference).
	// ======================================================================
	var hintBox = el( 'div', { class: 'hint-box' }, [
		el( 'p', { class: 'placeholder', text: '—' } ),
	] );
	var hintText = hintBox.querySelector( 'p' );
	var bulb = el( 'span', { class: 'bulb' } );
	bulb.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.6.5 1 1.2 1.1 2l.1.7h4.8l.1-.7c.1-.8.5-1.5 1.1-2A6 6 0 0 0 12 3z"/></svg>';
	hintBox.insertBefore( bulb, hintBox.firstChild );

	var hintsTabBtn = el( 'button', { class: 'help-tab active', type: 'button', text: 'Hints' } );
	var faqTabBtn   = el( 'button', { class: 'help-tab', type: 'button', text: 'FAQ' } );
	var aboutTabBtn = el( 'button', { class: 'help-tab', type: 'button', text: 'About' } );
	var helpTabsBar = el( 'div', { class: 'help-tabs' }, [ hintsTabBtn, faqTabBtn, aboutTabBtn ] );

	var hintsPanel = el( 'div', { class: 'help-panel active' }, [
		el( 'ol', {}, [
			el( 'li', { text: 'Choose WHERE comments are affected — everywhere, specific content types, or nowhere.' } ),
			el( 'li', { text: 'Decide separately whether NEW comments are blocked and whether EXISTING ones stay visible.' } ),
			el( 'li', { text: 'Hover any option to see exactly what it does here.' } ),
			el( 'li', { text: 'Hit Save — changes apply immediately, no page reload needed.' } ),
		] ),
	] );

	var FAQ_ITEMS = [
		{ q: 'Does hiding a comment delete it?', a: '"Hide all of them" and "Hide by date" only hide comments from the front end — nothing is removed from the database. Only the Delete tab actually removes comments.' },
		{ q: 'Can I close comments on old posts but keep new ones open?', a: 'Yes — use "Disable for selected content types" with the type left off the disabled list, then use "Hide by date" → "Before a date" to hide only the old thread.' },
		{ q: 'Will AI Assistant replies get moderated?', a: 'By default, yes — they land in Pending. Turn on "Publish immediately" in the AI Assistant tab once you trust the wording.' },
		{ q: 'What happens if I permanently delete comments by mistake?', a: 'Permanent deletion bypasses Trash and can\'t be undone from within WordPress — that\'s why Count is separate from Delete, and why Delete asks for confirmation with the exact number.' },
		{ q: 'Does the network enforcement lock apply to Delete and AI Assistant too?', a: 'No — only the Disable tab (where/how comments are turned off) can be enforced network-wide. Delete, AI Assistant, and General stay per-site.' },
	];
	var faqPanel = el( 'div', { class: 'help-panel' } );
	FAQ_ITEMS.forEach( function ( item ) {
		faqPanel.appendChild( el( 'div', { class: 'faq-item' }, [
			el( 'p', { class: 'faq-q', text: item.q } ),
			el( 'p', { class: 'faq-a', text: item.a } ),
		] ) );
	} );

	// ---- About panel: what the plugin does + where to go for more ----
	var ABOUT_FEATURES = [
		'Disable comments everywhere, for selected content types, or not at all',
		'Independent controls for blocking new comments vs. hiding existing ones',
		'Hide existing comments by date — between two dates, or everything before one',
		'Role-based exceptions so editors/admins can still comment where the public can\'t',
		'Bulk delete or trash comments by content type + status + date, with a Count-first safety step',
		'WooCommerce: filter Delete by product reviews vs. regular comments; Statistics breaks reviews out separately',
		'AI Assistant: keyword-triggered template replies, with ready-made presets (Sales, Support, Availability, Complaints, and — with WooCommerce — Negative product reviews by star rating)',
		'Statistics tab — live counts by status and content type, plus a running "Deleted" total',
		'Blocks comment spam via XML-RPC and the REST API',
		'Multisite: network-wide enforcement of the Disable settings, from Network Admin',
		'WP-CLI: wp disable-comments-rb status / set / stats / delete — with an on/off switch and built-in usage instructions',
		'Export and import your full configuration as JSON',
		'Site Health integration — your setup shows up in Tools → Site Health → Info',
	];

	var featuresList = el( 'ul', { class: 'about-features' } );
	ABOUT_FEATURES.forEach( function ( f ) {
		featuresList.appendChild( el( 'li', { text: f } ) );
	} );

	var aboutPanel = el( 'div', { class: 'help-panel' }, [
		el( 'p', { class: 'intro', text: 'Disable Comments RB turns off, hides, cleans up, and auto-replies to WordPress comments — with per-site or network-wide control.' } ),
		el( 'h3', { class: 'about-subtitle', text: 'Key features' } ),
		featuresList,
		el( 'div', { class: 'about-links' }, [
			el( 'a', { class: 'about-link', href: 'https://rbplugins.com/wordpress-plugins/disable-comments-rb/', target: '_blank', rel: 'noopener', text: 'Plugin homepage' } ),
			el( 'a', { class: 'about-link', href: 'https://app.upzilla.co/create-ticket', target: '_blank', rel: 'noopener', text: 'Contact support' } ),
			el( 'a', { class: 'about-link primary', href: 'https://robosoft.co/?pk_campaign=wp_dc_about', target: '_blank', rel: 'noopener', text: 'Custom development by RoboSoft' } ),
		] ),
	] );

	[ hintsTabBtn, faqTabBtn, aboutTabBtn ].forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			[ hintsTabBtn, faqTabBtn, aboutTabBtn ].forEach( function ( b ) { b.classList.remove( 'active' ); } );
			[ hintsPanel, faqPanel, aboutPanel ].forEach( function ( p ) { p.classList.remove( 'active' ); } );
			btn.classList.add( 'active' );
			( { 0: hintsPanel, 1: faqPanel, 2: aboutPanel } )[ [ hintsTabBtn, faqTabBtn, aboutTabBtn ].indexOf( btn ) ].classList.add( 'active' );
		} );
	} );

	var helpCard = el( 'div', { class: 'card help', 'aria-label': 'Help' }, [
		el( 'div', { class: 'card-body' }, [
			hintBox,
			helpTabsBar,
			hintsPanel,
			faqPanel,
			aboutPanel,
		] ),
	] );

	function showHelp( key ) {
		if ( key && help[ key ] ) {
			hintText.textContent = help[ key ];
			hintText.classList.remove( 'placeholder' );
		}
	}

	card.appendChild( head );
	card.appendChild( tabsBar );
	card.appendChild( panels );

	// ---- The banner above the settings: the PRO offer (until PRO is unlocked),
	// then RoboSoft's services. Two compact rows; each opens to show its list.
	// Its own Shadow DOM, apart from the settings' one: it sits outside the
	// settings root, and no other plugin's styles or banners can touch it.
	( function () {
		var bannerHost = document.getElementById( 'rb-disable-comments-banner' );
		if ( ! bannerHost || ! bannerHost.attachShadow ) {
			return;
		}
		var root = bannerHost.attachShadow( { mode: 'open' } );
		var css = document.createElement( 'style' );
		css.textContent = ''
			+ '*{box-sizing:border-box;}'
			+ '.banner{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;'
			+ 'font-size:13px;line-height:1.4;text-align:left;background:#12141c;color:#f3f2f7;border-radius:12px;overflow:hidden;}'
			+ '.row + .row{border-top:1px solid rgba(255,255,255,.09);}'
			+ '.row.pro{background:linear-gradient(120deg,#2a1a4a,#12141c 62%);}'
			+ '.head{display:flex;align-items:center;gap:14px;padding:11px 20px;}'
			+ '.dot{width:9px;height:9px;border-radius:50%;background:#f5c542;flex-shrink:0;box-shadow:0 0 0 3px rgba(245,197,66,.18);}'
			+ '.text{flex:1;min-width:0;}'
			+ '.title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#f5c542;}'
			+ '.sub{font-size:13px;font-weight:600;color:#fff;margin-top:2px;}'
			+ '.robo .text{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;}'
			+ '.name{font-size:15px;font-weight:700;color:#fff;}'
			+ '.ask{font-size:13px;color:#e2deee;}'
			+ '.more{background:none;border:0;padding:6px 4px;font:inherit;font-size:12px;font-weight:600;color:#cfc6ea;cursor:pointer;'
			+ 'display:flex;align-items:center;gap:7px;white-space:nowrap;flex-shrink:0;}'
			+ '.more:hover{color:#fff;}'
			+ '.more:focus-visible,.cta:focus-visible{outline:2px solid #f5c542;outline-offset:2px;border-radius:6px;}'
			+ '.more i{width:6px;height:6px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;'
			+ 'transform:rotate(45deg);margin-top:-3px;transition:transform .15s;}'
			+ '.more[aria-expanded=true] i{transform:rotate(-135deg);margin-top:3px;}'
			// both buttons take the width of the wider one
			+ '.cta{width:164px;text-align:center;border-radius:8px;padding:9px 12px;font-size:14px;font-weight:600;'
			+ 'text-decoration:none;white-space:nowrap;flex-shrink:0;background:#f5c542;color:#12141c;border:1px solid #f5c542;}'
			+ '.cta:hover{background:#ffd75e;border-color:#ffd75e;}'
			+ '.cta.quiet{background:rgba(255,255,255,.06);color:#f3f2f7;border-color:rgba(255,255,255,.18);}'
			+ '.cta.quiet:hover{background:rgba(255,255,255,.14);}'
			+ '.body{padding:2px 20px 16px 43px;}'
			+ '.body[hidden]{display:none;}'
			+ '.features{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px 32px;}'
			+ '.features li{font-size:12.5px;line-height:1.5;color:#e7e4ef;padding-left:15px;position:relative;}'
			+ '.features li:before{content:"";position:absolute;left:0;top:7px;width:5px;height:5px;border-radius:50%;background:#f5c542;}'
			+ '.chips{display:flex;flex-wrap:wrap;gap:8px;}'
			+ '.chip{font-size:12px;color:#cfcbe0;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);'
			+ 'border-radius:20px;padding:4px 12px;white-space:nowrap;}'
			+ '@media (max-width:720px){.head{flex-wrap:wrap;}.text{flex-basis:calc(100% - 23px);}.more{margin-left:19px;}'
			+ '.cta{margin-left:auto;}.features{grid-template-columns:1fr;}.body{padding-left:20px;}}';

		// one row: what it is, a switch for its list, its button; the list below
		function section( key, cls, textNodes, showLabel, hideLabel, list, button ) {
			var label = el( 'span', { text: showLabel } );
			var more = el( 'button', { class: 'more', type: 'button', 'aria-expanded': 'false' }, [ label, el( 'i' ) ] );
			var body = el( 'div', { class: 'body' }, [ list ] );
			body.hidden = true;
			function set( open ) {
				body.hidden = ! open;
				more.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				label.textContent = open ? hideLabel : showLabel;
			}
			more.addEventListener( 'click', function () {
				var open = body.hidden;
				set( open );
				try { window.localStorage.setItem( 'rbdc_banner_' + key, open ? '1' : '0' ); } catch ( e ) { /* private mode: not remembered */ }
			} );
			try { set( window.localStorage.getItem( 'rbdc_banner_' + key ) === '1' ); } catch ( e ) { /* stays closed */ }
			return el( 'div', { class: 'row ' + cls }, [
				el( 'div', { class: 'head' }, [ el( 'span', { class: 'dot' } ), el( 'div', { class: 'text' }, textNodes ), more, button ] ),
				body,
			] );
		}

		var rows = [];
		if ( ! data.isPro ) {
			var features = el( 'ul', { class: 'features' } );
			( s.proFeatures || [] ).forEach( function ( f ) { features.appendChild( el( 'li', { text: f } ) ); } );
			rows.push( section( 'pro', 'pro',
				[ el( 'div', { class: 'title', text: s.proCardTitle } ), el( 'div', { class: 'sub', text: s.proBannerIntro } ) ],
				s.proShow, s.proHide, features,
				el( 'a', { class: 'cta', href: data.proBuyUrl, target: '_blank', rel: 'noopener', text: s.proBuyBtn } ) ) );
		}
		var chips = el( 'div', { class: 'chips' } );
		[ s.roboChip1, s.roboChip2, s.roboChip3, s.roboChip4, s.roboChip5 ].forEach( function ( c ) {
			chips.appendChild( el( 'span', { class: 'chip', text: c } ) );
		} );
		rows.push( section( 'robo', 'robo',
			[ el( 'span', { class: 'name', text: 'RoboSoft' } ), el( 'span', { class: 'ask', text: s.roboTitle } ) ],
			s.roboShow, s.roboHide, chips,
			// one bright button per banner: while the PRO offer is shown, this one is the quiet one
			el( 'a', { class: data.isPro ? 'cta' : 'cta quiet', href: data.roboUrl, target: '_blank', rel: 'noopener', text: s.roboBtn } ) ) );
		root.appendChild( css );
		root.appendChild( el( 'div', { class: 'banner' }, rows ) );
	} )();

	var rightColChildren = [ helpCard ];
	var rightCol = el( 'div', { class: 'right-col' }, rightColChildren );
	var layout = el( 'div', { class: 'layout' }, [ card, rightCol ] );

	layout.addEventListener( 'mouseover', function ( e ) {
		var target = e.target.closest ? e.target.closest( '[data-help-key]' ) : null;
		if ( target ) { showHelp( target.getAttribute( 'data-help-key' ) ); }
	} );
	layout.addEventListener( 'focusin', function ( e ) {
		var target = e.target.closest ? e.target.closest( '[data-help-key]' ) : null;
		if ( target ) { showHelp( target.getAttribute( 'data-help-key' ) ); }
	} );

	showHelp( 'mode_selected' );

	// Deep-link from the admin-bar dropdown / Comments-page button:
	// ?rbdc_tab=delete (etc.) activates that tab on load, same as a click.
	try {
		var requestedTab = new URLSearchParams( window.location.search ).get( 'rbdc_tab' );
		var tabButtonBySlug = { disable: disableTab, delete: deleteTab, ai: aiTab, general: generalTab, stats: statsTab };
		if ( requestedTab && tabButtonBySlug[ requestedTab ] ) {
			tabButtonBySlug[ requestedTab ].click();
		}
	} catch ( e ) { /* malformed query string — just show the default tab */ }

	host.textContent = '';
	shadow.appendChild( layout );
} )();
