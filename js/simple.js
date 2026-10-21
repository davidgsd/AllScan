var simple = {
	source: null,
	channels: [],
	channelByNode: {},
	currentNode: '',
	connectedNodes: [],
	canModify: false,
	autodiscDefault: true,
	autodisc: true,
	autodiscBtn: null,
	autodiscStorageKey: '',
	baseUrl: '',
	loginUrl: '',
	localNode: '',
	statmsg: null,
	scanmsg: null,
	statusPanel: null,
	statusLabel: null,
	statusDetail: null,
	warningPanel: null,
	warningDetail: null,
	readonlyPanel: null,
	loginBtn: null,
	disconnectBtn: null,
	disconnectAllBtn: null,
	channelsViewport: null,
	channelsGrid: null,
	scrollUpBtn: null,
	scrollDownBtn: null,
	statsTmr: null,
	statsReq: null,
	statsIndex: 0,
	txCnt: {},
	txTim: {},
	txTT: {},
	txAvg: {},
	retryTmr: null
};

function simpleInit() {
	var cfg = window.simpleConfig || {};
	simple.channels = cfg.channels || [];
	simple.canModify = !!cfg.canModify;
	simple.autodiscDefault = cfg.autodiscDefault !== false;
	simple.baseUrl = cfg.baseUrl || '';
	simple.loginUrl = cfg.loginUrl || (simple.baseUrl + '/user/');
	simple.localNode = cfg.localNode || '';
	simple.statmsg = document.getElementById('statmsg');
	simple.scanmsg = document.getElementById('scanmsg');
	simple.statusPanel = document.getElementById('statusPanel');
	simple.statusLabel = document.getElementById('statusLabel');
	simple.statusDetail = document.getElementById('statusDetail');
	simple.warningPanel = document.getElementById('warningPanel');
	simple.warningDetail = document.getElementById('warningDetail');
	simple.readonlyPanel = document.getElementById('readonlyPanel');
	simple.loginBtn = document.getElementById('loginBtn');
	simple.disconnectBtn = document.getElementById('disconnectBtn');
	simple.autodiscBtn = document.getElementById('autodiscBtn');
	simple.autodiscStorageKey = 'allscan.autodisc.' + simple.localNode;
	initAutodiscPreference();
	simple.disconnectAllBtn = document.getElementById('disconnectAllBtn');
	simple.channelsViewport = document.getElementById('channelsViewport');
	simple.channelsGrid = document.getElementById('channelsGrid');
	simple.scrollUpBtn = document.getElementById('scrollUpBtn');
	simple.scrollDownBtn = document.getElementById('scrollDownBtn');

	if(cfg.startupMessages)
		statMsg(cfg.startupMessages);
	renderChannels();
	bindControls();
	updateReadOnlyPanel();
	setStatus('NO CONNECTION', 'Waiting for node status...', 'idle');
	initEventSource();
	getChannelStats();
}

function bindControls() {
	simple.autodiscBtn.addEventListener('click', toggleAutodiscPreference);
	window.addEventListener('storage', handleAutodiscStorage);
	simple.disconnectBtn.addEventListener('click', function() {
		if(simple.currentNode)
			disconnectNode(simple.currentNode);
	});
	simple.disconnectAllBtn.addEventListener('click', function() {
		disconnectLocalNodes();
	});
	simple.loginBtn.addEventListener('click', function() {
		window.location.href = simple.loginUrl;
	});
	simple.scrollUpBtn.addEventListener('click', function() {
		scrollChannels(-1);
	});
	simple.scrollDownBtn.addEventListener('click', function() {
		scrollChannels(1);
	});
	simple.channelsViewport.addEventListener('scroll', updateScrollButtons);
	window.addEventListener('resize', updateScrollButtons);
	window.addEventListener('beforeunload', closeEventSource);
	window.addEventListener('beforeunload', closeChannelStats);
}

function updateReadOnlyPanel() {
	if(simple.canModify)
		simple.readonlyPanel.classList.add('hidden');
	else
		simple.readonlyPanel.classList.remove('hidden');
}

function renderChannels() {
	simple.channelByNode = {};
	simple.channelsGrid.innerHTML = '';
	if(!simple.channels.length) {
		simple.channelsGrid.innerHTML = '<div class="empty-channels">No channels configured</div>';
		return;
	}
	simple.channels.forEach(function(channel, i) {
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'channel-btn';
		btn.dataset.node = channel.node;
		btn.disabled = !simple.canModify;
		// AllScan Mods: TouchGUI callsign-first card content v1.46.
		// AllScan Mods: TouchGUI three-row card data v1.52.
		btn.innerHTML = '<span class="channel-side"><span class="channel-index">' + (i + 1) + '</span>'
			+ '<span class="channel-stats"><span class="channel-rx">—</span><span class="channel-lcnt">—</span></span></span>'
			+ '<span class="channel-content"><span class="channel-meta">' + escapeHtml(channelMeta(channel)) + '</span>'
			+ '<span class="channel-description">' + escapeHtml(channelDescription(channel)) + '</span>'
			+ '<span class="channel-note"><span class="channel-note-name">' + escapeHtml(channelNote(channel)) + '</span>'
			+ '<span class="channel-location">' + escapeHtml(channelLocation(channel)) + '</span></span></span>';
		requestAnimationFrame(function() {
			fitChannelMeta(btn);
			fitChannelDescription(btn);
			fitChannelLabel(btn);
		});

		btn.addEventListener('click', function() {
			connectChannel(channel.node);
		});
		simple.channelByNode[channel.node] = channel;
		if(channel.activity)
			applyChannelActivity(btn, channel.activity);
		simple.channelsGrid.appendChild(btn);
	});
	updateScrollButtons();
}

function initEventSource() {
	if(typeof(EventSource) === 'undefined') {
		statMsg('ERROR: Browser does not support server-sent events.');
		return;
	}
	closeEventSource();
	var url = simple.baseUrl + '/astapi/server.php?nodes=' + encodeURIComponent(simple.localNode);
	simple.source = new EventSource(url);
	simple.source.addEventListener('nodes', handleNodesEvent, false);
	simple.source.addEventListener('connection', handleConnectionEvent, false);
	simple.source.addEventListener('errMsg', handleErrMsgEvent, false);
	simple.source.onerror = function() {
		statMsg('Event Source error. Retrying...');
		if(simple.retryTmr !== null)
			clearTimeout(simple.retryTmr);
		simple.retryTmr = setTimeout(initEventSource, 15000);
	};
}

function closeEventSource() {
	if(simple.retryTmr !== null) {
		clearTimeout(simple.retryTmr);
		simple.retryTmr = null;
	}
	if(simple.source) {
		simple.source.close();
		simple.source = null;
	}
}

// AllScan Mods: TouchGUI activity colors, Rx%/LCnt, main-table color mapping and rounded value badges v1.18.
function getChannelStatsNodes() {
	var nodes = [];
	simple.channels.forEach(function(channel) {
		var node = String(channel.node);
		var number = Number(node);
		if(number >= 2000 && number < 3000000 && nodes.indexOf(node) < 0)
			nodes.push(node);
	});
	return nodes;
}

function getChannelStats() {
	var nodes = getChannelStatsNodes();
	if(!nodes.length)
		return;
	if(simple.statsIndex >= nodes.length)
		simple.statsIndex = 0;
	var node = nodes[simple.statsIndex++];
	simple.statsReq = new XMLHttpRequest();
	simple.statsReq.onreadystatechange = function() {
		if(simple.statsReq.readyState !== 4)
			return;
		var delay = Math.max(2000, Math.ceil(30000 / nodes.length));
		var responseData = null;
		if(simple.statsReq.status === 200) {
			try {
				var response = JSON.parse(simple.statsReq.responseText);
				responseData = response.data || null;
			} catch(e) {}
		}
		if(simple.statsReq.status === 429 || (responseData && responseData.retcode == 429)) {
			simple.statsIndex = Math.max(0, simple.statsIndex - 1);
			delay = 15000;
		} else if(responseData && responseData.stats && String(responseData.stats.node) === node) {
			updateChannelActivity(responseData.stats);
		} else if(simple.statsReq.status !== 200 && simple.statsReq.status !== 0) {
			delay = 15000;
		} else if(responseData && responseData.retcode != 404) {
			delay = 15000;
		}
		simple.statsTmr = setTimeout(getChannelStats, delay);
	};
	simple.statsReq.open('POST', simple.baseUrl + '/stats/stats.php', true);
	simple.statsReq.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	simple.statsReq.send('node=' + encodeURIComponent(node));
}

function closeChannelStats() {
	if(simple.statsTmr !== null)
		clearTimeout(simple.statsTmr);
	if(simple.statsReq)
		simple.statsReq.abort();
}

function updateChannelActivity(stats) {
	var node = String(stats.node);
	var now = Math.floor(Date.now() / 1000);
	if(!simple.txCnt[node] || stats.keyups < simple.txCnt[node] || stats.txtime < simple.txTT[node]) {
		simple.txCnt[node] = stats.keyups;
		simple.txTim[node] = 0;
		simple.txTT[node] = stats.txtime;
		simple.txAvg[node] = 0;
	}
	var keyupDelta = stats.keyups - simple.txCnt[node];
	var txDelta = stats.txtime - simple.txTT[node];
	var elapsed = now - simple.txTim[node];
	if(txDelta > 2 * elapsed || keyupDelta > elapsed / 3)
		keyupDelta = txDelta = 0;
	simple.txTim[node] = now;
	var txPercent = elapsed ? Math.min(100, Math.round(100 * txDelta / elapsed)) : 0;
	if(stats.keyed == 1)
		simple.txAvg[node] = 100;
	else if(simple.txAvg[node] + txPercent > 2)
		simple.txAvg[node] = Math.round(simple.txAvg[node] / 2 + txPercent / 2);
	else
		simple.txAvg[node] = 0;
	simple.txCnt[node] = stats.keyups;
	simple.txTT[node] = stats.txtime;
	var channel = simple.channelByNode[node];
	if(!channel)
		return;
	channel.activity = {active: stats.active == 1, wt: stats.wt == 1, txAverage: simple.txAvg[node], rxPct: stats.busyPct, linkCnt: stats.linkCnt};
	var button = simple.channelsGrid.querySelector('[data-node="' + node + '"]');
	if(button)
		applyChannelActivity(button, channel.activity);
}

function applyChannelActivity(button, activity) {
	var keyed = activity.txAverage > 5;
	var txColor = 'hsl(0,100%,' + convertActivityRange(activity.txAverage, 15, 29) + '%)';
	var activeColor = activity.wt ? 'hsl(150,50%,20%)' : 'hsl(150,50%,15%)';
	var rxPct = Number(activity.rxPct);
	var linkCount = Number(activity.linkCnt);
	var hasRx = activity.rxPct !== undefined && activity.rxPct !== null && activity.rxPct !== '-' && isFinite(rxPct);
	var hasLinks = activity.linkCnt !== undefined && activity.linkCnt !== null && activity.linkCnt !== '-' && isFinite(linkCount);
	var rxColor = hasRx && rxPct > 2 ? 'hsl(125,40%,' + convertActivityRange(2 * rxPct, 10, 30) + '%)' : '';
	var linkColor = hasLinks && linkCount > 2 ? 'hsl(240,40%,' + convertActivityRange(3 * linkCount, 15, 30) + '%)' : '';
	var index = button.querySelector('.channel-index');
	var rx = button.querySelector('.channel-rx');
	var links = button.querySelector('.channel-lcnt');
	if(index)
		index.style.backgroundColor = keyed ? txColor : (activity.active ? activeColor : 'transparent');
	if(rx)
		rx.textContent = hasRx ? String(activity.rxPct) + '%' : '—';
	if(rx)
		rx.style.backgroundColor = rxColor;
	if(links)
		links.textContent = activity.linkCnt === undefined || activity.linkCnt === null ? '—' : String(activity.linkCnt);
	if(links)
		links.style.backgroundColor = linkColor;
	button.dataset.activity = keyed ? 'keyed' : (activity.active ? (activity.wt ? 'active-wt' : 'active') : 'idle');
	button.style.backgroundColor = '';
}

function convertActivityRange(value, min, max) {
	if(value > 100)
		value = 100;
	else if(value < 0)
		value = 0;
	return Math.round(value * (max - min) / 100 + min);
}

function handleConnectionEvent(event) {
	var data = JSON.parse(event.data);
	if(data.status)
		statMsg(data.status);
}

function handleErrMsgEvent(event) {
	var data = JSON.parse(event.data);
	statMsg('ERROR: ' + data.status);
}

function handleNodesEvent(event) {
	var tabledata = JSON.parse(event.data);
	var nodeData = tabledata[simple.localNode];
	if(!nodeData)
		return;
	var directNodes = [];
	var keyed = false;
	var txKeyed = false;
	var connecting = false;
	var connectingNode = '';

	nodeData.remote_nodes.forEach(function(row) {
		if(row.cos_keyed == 1)
			keyed = true;
		if(row.tx_keyed == 1)
			txKeyed = true;
		if(row.info === 'NO CONNECTION')
			return;
		if(row.node == 1)
			return;
		if(row.node)
			directNodes.push(String(row.node));
		if(row.mode === 'C') {
			connecting = true;
			connectingNode = String(row.node);
		}
	});

	directNodes = unique(directNodes);
	simple.connectedNodes = directNodes;
	updateState(directNodes, keyed, txKeyed, connecting, connectingNode);
}

function updateState(directNodes, keyed, txKeyed, connecting, connectingNode) {
	var activeNode = '';
	directNodes.some(function(node) {
		if(simple.channelByNode[node]) {
			activeNode = node;
			return true;
		}
		return false;
	});
	simple.currentNode = activeNode;
	updateChannelButtons(activeNode);

	var extras = directNodes.filter(function(node) {
		return node !== activeNode;
	});
	var hasExternalOnly = !activeNode && directNodes.length > 0;
	var hasExtras = extras.length > 0 || hasExternalOnly;
	var label = 'Idle';
	var state = 'idle';
	var detail = hasExternalOnly ? 'Only unlisted node(s) connected.' : 'Tap a channel to connect.';

	var statusNode = connectingNode || activeNode;
	if(statusNode) {
		if(txKeyed && keyed)
			label = 'COS & PTT Keyed';
		else if(txKeyed)
			label = 'PTT Keyed';
		else if(keyed)
			label = 'COS Keyed';
		else
			label = connecting ? 'Connecting' : 'Connected';
		detail = channelTitle(statusNode);
		state = txKeyed ? 'tx' : (keyed ? 'rx' : (connecting ? 'connecting' : 'connected'));
	}

	setStatus(label, detail, state);
	updateWarning(hasExtras, activeNode, extras, directNodes);
	simple.disconnectBtn.disabled = !simple.canModify || !activeNode || hasExtras;
}

function setStatus(label, detail, state) {
	simple.statusLabel.textContent = label;
	simple.statusDetail.textContent = detail;
	simple.statusPanel.className = 'status-panel state-' + state;
	document.title = label + ' - AllScan TouchGUI';
}

function updateWarning(show, activeNode, extras, directNodes) {
	if(!show) {
		simple.warningPanel.classList.add('hidden');
		return;
	}
	simple.warningDetail.textContent = directNodes.length
		? 'Connected node(s): ' + directNodes.join(', ')
		: 'Nodes are connected.';
	simple.warningPanel.classList.remove('hidden');
	simple.disconnectAllBtn.disabled = !simple.canModify;
}

function updateChannelButtons(activeNode) {
	var buttons = simple.channelsGrid.querySelectorAll('.channel-btn');
	buttons.forEach(function(btn) {
		var node = btn.dataset.node;
		btn.classList.toggle('active', node === activeNode);
		btn.classList.toggle('connected-extra', simple.connectedNodes.indexOf(node) >= 0 && node !== activeNode);
	});
}

function connectChannel(node) {
	if(!simple.canModify)
		return;
	statMsg('Connecting to channel ' + node + '...');
	sendControl('remotenode=' + encodeURIComponent(node)
		+ '&perm=false&button=connect&localnode=' + encodeURIComponent(simple.localNode)
		+ '&autodisc=' + simple.autodisc);
}

function disconnectNode(node) {
	if(!simple.canModify)
		return;
	var label = node === '0' ? 'Disconnecting all nodes...' : 'Disconnecting ' + node + '...';
	statMsg(label);
	sendControl('remotenode=' + encodeURIComponent(node)
		+ '&perm=false&button=disconnect&localnode=' + encodeURIComponent(simple.localNode));
}

function disconnectLocalNodes() {
	if(!simple.canModify || !simple.connectedNodes.length)
		return;
	statMsg('Disconnecting locally connected node(s): ' + simple.connectedNodes.join(', ') + '...');
	sendControl('remotenode=0&perm=false&button=disconnect&localnode=' + encodeURIComponent(simple.localNode));
}

function sendControl(parms) {
	var xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function() {
		if(xhr.readyState !== 4)
			return;
		if(xhr.status === 200)
			statMsg(xhr.responseText);
		else
			statMsg('Control request failed: HTTP ' + xhr.status);
	};
	xhr.open('POST', simple.baseUrl + '/astapi/connect.php', true);
	xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhr.send(parms);
}

function scrollChannels(direction) {
	var amount = Math.max(120, Math.floor(simple.channelsViewport.clientHeight * 0.85));
	simple.channelsViewport.scrollBy({top: direction * amount, behavior: 'smooth'});
}

function updateScrollButtons() {
	var vp = simple.channelsViewport;
	var canScroll = vp.scrollHeight > vp.clientHeight + 2;
	simple.scrollUpBtn.disabled = !canScroll || vp.scrollTop <= 1;
	simple.scrollDownBtn.disabled = !canScroll || vp.scrollTop + vp.clientHeight >= vp.scrollHeight - 1;
}

function formatTouchguiStatusMessage(msg) {
	if(typeof msg !== 'string' || msg.indexOf('ASL Ver:') !== 0)
		return msg;
	var lines = msg.split(/<br\s*\/?\s*>/i);
	lines = lines.map(function(line) { return line.trim(); });
	if(lines.length < 3)
		return msg;
	var settingsIndex = lines.indexOf('Channel driver settings:');
	if(settingsIndex < 0 || settingsIndex === lines.length - 1)
		return msg;
	var settings = lines.slice(settingsIndex + 1).filter(function(line) { return line !== ''; }).map(function(line) {
		return line.replace(/ Level currently set to /, ' ');
	});
	if(!settings.length)
		return msg;
	return lines.slice(0, settingsIndex).join(' - ') + '<br>Channel settings: ' + settings.join(' | ');
}

function statMsg(msg) {
	if(!simple.statmsg || !msg)
		return;
	simple.statmsg.innerHTML = formatTouchguiStatusMessage(msg);
}

// AllScan Mods: TouchGUI connected banner callsign v1.51.
function channelTitle(node) {
	var channel = simple.channelByNode[node];
	if(!channel)
		return 'Node ' + node;
	var nodeCall = String(node);
	if(channel.call)
		nodeCall += ' - ' + String(channel.call).trim();
	return channelLabel(channel) + ' (' + nodeCall + ')';
}

// AllScan Mods: TouchGUI matching callsign removal v1.18.
function channelLabel(channel) {
	var callsign = channel.call ? String(channel.call).trim() : '';
	function cleanLabel(text) {
		text = String(text || '');
		if(channel.node) {
			var nodePat = new RegExp('(^|[\\s,;:-]+)' + escapeRegExp(channel.node) + '([\\s,;:-]*$|(?=[\\s,;:-]+))', 'g');
			text = text.replace(nodePat, function(match, prefix) {
				return prefix && prefix.trim() ? prefix : '';
			});
		}
		if(callsign) {
			var callPat = new RegExp('(^|[\\s,;:.()]+)' + escapeRegExp(callsign) + '(?=$|[\\s,;:.()]+)', 'gi');
			text = text.replace(callPat, function(match, prefix) {
				return prefix && prefix.trim() ? prefix : '';
			});
		}
		return text.replace(/\s{2,}/g, ' ').replace(/^[\s,;:.()]+|[\s,;:.()]+$/g, '').trim();
	}
	var label = cleanLabel(channel.label || channel.name || '');
	if(!label)
		label = cleanLabel(channel.desc);
	if(!label)
		label = cleanLabel(channel.loc);
	return label || channel.node || '';
}
// AllScan Mods: TouchGUI row text width fit v1.53.
function fitChannelMeta(button) {
	var meta = button.querySelector('.channel-meta');
	if(!meta || !meta.clientWidth)
		return;
	meta.style.fontSize = '';
	meta.style.transform = '';
	var textWidth = meta.scrollWidth;
	if(textWidth > meta.clientWidth) {
		var fontSize = parseFloat(window.getComputedStyle(meta).fontSize);
		var isPortrait = window.matchMedia && window.matchMedia('(orientation: portrait)').matches;
		var minFontSize = isPortrait ? 10 : Math.max(12, fontSize * 0.9);
		meta.style.fontSize = Math.max(minFontSize, fontSize * meta.clientWidth / textWidth) + 'px';
		textWidth = meta.scrollWidth;
		if(textWidth > meta.clientWidth) {
			var scaleFactor = isPortrait ? 0.90 : 0.96;
			meta.style.transform = 'scaleX(' + (meta.clientWidth / textWidth * scaleFactor) + ')';
		}
	}
}

// AllScan Mods: TouchGUI responsive description sizing v1.55.
function fitChannelText(element, maxFontSize, minFontSize) {
	var label = element;
	if(!label || !label.clientWidth)
		return;
	label.style.fontSize = '';
	label.style.transform = '';
	label.style.whiteSpace = '';
	label.style.overflowWrap = '';
	label.style.display = '';
	label.style.maxHeight = '';
	label.style.overflow = '';
	label.style.whiteSpace = 'nowrap';
	var fontSize = parseFloat(window.getComputedStyle(label).fontSize);
	var targetFontSize = maxFontSize ? Math.max(fontSize, maxFontSize) : fontSize;
	if(maxFontSize)
		label.style.fontSize = targetFontSize + 'px';
	var textWidth = label.scrollWidth;
	if(textWidth > label.clientWidth) {
		var isSmallScreen = window.matchMedia && window.matchMedia('(max-width:700px)').matches;
		// v1.54 kept the aqua row from growing past its base; v1.55 bounds its new auto-fit range.
		var minSize = minFontSize || Math.min(isSmallScreen ? 12 : 14, fontSize);
		// AllScan Mods: TouchGUI measured aqua row fit v1.57.
		label.style.fontSize = Math.max(minSize, targetFontSize * label.clientWidth / textWidth) + 'px';
		if(label.scrollWidth > label.clientWidth) {
			// AllScan Mods: TouchGUI aqua near-fit migration v1.59.
			if(maxFontSize && label.scrollWidth <= label.clientWidth * 1.12) {
				label.style.transform = 'scaleX(' + (label.clientWidth / label.scrollWidth * 0.98) + ')';
				label.style.overflow = 'visible';
				// AllScan Mods: TouchGUI near-fit overflow visibility v1.60.
			} else {
				label.style.whiteSpace = 'normal';
				label.style.overflowWrap = 'anywhere';
				label.style.display = 'block';
			}
		}
	} else if(!maxFontSize) {
		label.style.whiteSpace = '';
	}
}
function fitChannelDescription(button) {
	fitChannelText(button.querySelector('.channel-description'), 18, 13);
}
// AllScan Mods: TouchGUI adaptive title fit v1.45.
// AllScan Mods: TouchGUI name/location row fit v1.63.
function fitChannelLabel(button) {
	var note = button.querySelector('.channel-note-name');
	var location = button.querySelector('.channel-location');
	if(!note)
		return;
	fitChannelText(note);
	if(!location)
		return;
	location.style.position = 'absolute';
	location.style.visibility = 'hidden';
	fitChannelText(location);
	// AllScan Mods: TouchGUI US/USA note-location collapse v1.69.
	var noteText = String(note.textContent || '').trim().toLowerCase();
	var locationText = String(location.textContent || '').trim().toLowerCase();
	var normalizedNote = noteText.replace(/[^a-z0-9]/g, '');
	var normalizedLocation = locationText.replace(/[^a-z0-9]/g, '');
	if((normalizedNote === 'us' && normalizedLocation === 'usa') || (normalizedNote === 'usa' && normalizedLocation === 'us')) {
		note.textContent = location.textContent;
		fitChannelText(note);
		noteText = locationText;
	}
	if(!note.clientWidth || !noteText || !locationText || noteText === locationText || note.style.whiteSpace === 'normal')
		return;
	location.style.position = 'static';
	location.style.visibility = 'visible';
}
function fitTouchguiCardText() {
	document.querySelectorAll('.channel-btn').forEach(function(button) {
		fitChannelMeta(button);
		fitChannelDescription(button);
		fitChannelLabel(button);
	});
}
window.addEventListener('resize', fitTouchguiCardText);
// AllScan Mods: TouchGUI name and location card rows v1.63.
// AllScan Mods: TouchGUI description/location duplicate cleanup v1.68.
function channelDescription(channel) {
	var description = String(channel.desc || '').trim();
	var location = String(channel.loc || '').trim();
	var country = function(value) { return String(value || '').replace(/[^a-z]/gi, '').toUpperCase(); };
	var descMatch = description.match(/(?:^|[^a-z])(U\.?S\.?A?\.?)\.?$/i);
	var locationMatch = location.match(/(?:^|[^a-z])(U\.?S\.?A?\.?)\.?($|[^a-z])/i);
	var descCountry = descMatch ? country(descMatch[1]) : '';
	var locationCountry = locationMatch ? country(locationMatch[1]) : '';
	if ((descCountry === 'US' || descCountry === 'USA') && (locationCountry === 'US' || locationCountry === 'USA'))
		description = description.replace(/[\s,;:-]*U\.?S\.?A?\.?\.?$/i, '');
	return description.replace(/\s{2,}/g, ' ').replace(/^[\s,;:.()]+|[\s,;:.()]+$/g, '').trim();
}
function channelLocation(channel) {
	return String(channel.loc || '').trim();
}
// AllScan Mods: TouchGUI name/description similarity v1.64.
function channelNameMatchesDescription(name, description) {
	var normalizedName = String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
	var normalizedDescription = String(description || '').toLowerCase().replace(/[^a-z0-9]/g, '');
	if (normalizedName === normalizedDescription || (normalizedName === 'us' && normalizedDescription === 'usa') || (normalizedName === 'usa' && normalizedDescription === 'us'))
		return !!normalizedName;
	if(normalizedName.length < 6 || normalizedDescription.length < 6)
		return false;
	if(normalizedName.indexOf(normalizedDescription) >= 0 || normalizedDescription.indexOf(normalizedName) >= 0)
		return true;
	var previous = new Array(normalizedDescription.length + 1).fill(0);
	for(var i = 0; i < normalizedName.length; i++) {
		var current = [0];
		for(var j = 0; j < normalizedDescription.length; j++) {
			current[j + 1] = normalizedName.charAt(i) === normalizedDescription.charAt(j)
				? previous[j] + 1
				: Math.max(previous[j + 1], current[j]);
		}
		previous = current;
	}
	var sharedLength = previous[normalizedDescription.length];
	return sharedLength >= 6 && sharedLength / normalizedName.length >= 0.75;
}
// AllScan Mods: TouchGUI adjacent card-row overlap cleanup v1.68.
function channelNote(channel) {
	var text = String(channel.label || '');
	var fields = [channel.node, channel.call, channel.desc, channel.loc]
		.filter(function(value) { return value !== undefined && value !== null && String(value).trim() && String(value).trim() !== '-'; })
		.map(function(value) { return String(value).trim(); })
		.sort(function(a, b) { return b.length - a.length; });
	fields.forEach(function(value) {
		var fieldPat = new RegExp('(^|[\\s,;:-]+)' + escapeRegExp(value) + '(?=$|[\\s,;:-]+)', 'gi');
		text = text.replace(fieldPat, function(match, prefix) {
			return prefix && prefix.trim() ? prefix : '';
		});
	});
	text = text.replace(/\s{2,}/g, ' ').replace(/^[\s,;:.()]+|[\s,;:.()]+$/g, '').trim();
	var descriptionWords = String(channel.desc || '').match(/[a-z0-9]+/gi) || [];
	var noteWords = [];
	var wordPattern = /[a-z0-9]+/gi;
	var wordMatch;
	while((wordMatch = wordPattern.exec(text)) !== null)
		noteWords.push({value: wordMatch[0], end: wordPattern.lastIndex});
	var overlap = 0;
	for(var count = Math.min(3, descriptionWords.length, noteWords.length); count > 0; count--) {
		var matches = true;
		for(var i = 0; i < count; i++) {
			var descWord = descriptionWords[descriptionWords.length - count + i].replace(/[^a-z0-9]/gi, '').toUpperCase();
			var noteWord = noteWords[i].value.replace(/[^a-z0-9]/gi, '').toUpperCase();
			if((descWord === 'US' || descWord === 'USA') && (noteWord === 'US' || noteWord === 'USA'))
				continue;
			if(descWord !== noteWord) { matches = false; break; }
		}
		if(matches) { overlap = count; break; }
	}
	if(overlap) {
		text = text.slice(noteWords[overlap - 1].end).replace(/^[\s,;:.()/-]+/, '').trim();
	}
	var location = channelLocation(channel);
	if(!text || channelNameMatchesDescription(text, channel.desc) || channelNameMatchesDescription(text, channel.loc))
		return location;
	return text;
}

function channelMeta(channel) {
	var parts = [];
	if(channel.node)
		parts.push(channel.node);
	if(channel.call)
		parts.push(channel.call);
	return parts.join(' - ');
}

function unique(items) {
	var out = [];
	items.forEach(function(item) {
		item = String(item);
		if(item && out.indexOf(item) < 0)
			out.push(item);
	});
	return out;
}

function escapeHtml(txt) {
	return String(txt).replace(/[&<>"']/g, function(c) {
		return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[c];
	});
}

function escapeRegExp(txt) {
	return String(txt).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function initAutodiscPreference() {
	try {
		var value = localStorage.getItem(simple.autodiscStorageKey);
		if(value === 'true' || value === 'false')
			simple.autodisc = value === 'true';
		else {
			simple.autodisc = simple.autodiscDefault;
			localStorage.setItem(simple.autodiscStorageKey, simple.autodisc ? 'true' : 'false');
		}
	} catch(e) {
		simple.autodisc = simple.autodiscDefault;
	}
	updateAutodiscButton();
}

function saveAutodiscPreference() {
	try { localStorage.setItem(simple.autodiscStorageKey, simple.autodisc ? 'true' : 'false'); } catch(e) {}
	updateAutodiscButton();
}

function toggleAutodiscPreference() {
	simple.autodisc = !simple.autodisc;
	saveAutodiscPreference();
}

function updateAutodiscButton() {
	if(!simple.autodiscBtn)
		return;
	simple.autodiscBtn.textContent = 'Disconnect before Connect: ' + (simple.autodisc ? 'ON' : 'OFF');
	simple.autodiscBtn.setAttribute('aria-pressed', simple.autodisc ? 'true' : 'false');
}

function handleAutodiscStorage(event) {
	if(event.key === simple.autodiscStorageKey && (event.newValue === 'true' || event.newValue === 'false')) {
		simple.autodisc = event.newValue === 'true';
		updateAutodiscButton();
	}
}
