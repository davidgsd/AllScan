var simple = {
	source: null,
	channels: [],
	channelByNode: {},
	currentNode: '',
	connectedNodes: [],
	canModify: false,
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
	retryTmr: null
};

function simpleInit() {
	var cfg = window.simpleConfig || {};
	simple.channels = cfg.channels || [];
	simple.canModify = !!cfg.canModify;
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
}

function bindControls() {
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
		btn.innerHTML = '<span class="channel-index">' + (i + 1) + '</span>'
			+ '<span class="channel-label">' + escapeHtml(channelLabel(channel)) + '</span>'
			+ '<span class="channel-meta">' + escapeHtml(channelMeta(channel)) + '</span>';
		btn.addEventListener('click', function() {
			connectChannel(channel.node);
		});
		simple.channelByNode[channel.node] = channel;
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
		if(row.mode === 'C')
			connecting = true;
	});

	directNodes = unique(directNodes);
	simple.connectedNodes = directNodes;
	updateState(directNodes, keyed, txKeyed, connecting);
}

function updateState(directNodes, keyed, txKeyed, connecting) {
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
	var label = 'NO CONNECTION';
	var detail = 'Tap a channel to connect.';
	var state = 'idle';

	if(hasExtras) {
		label = 'EXTRA CONNECTIONS';
		detail = activeNode ? channelTitle(activeNode) + ' plus ' + extras.join(', ') : 'Connected to ' + directNodes.join(', ');
		state = 'warning';
	} else if(activeNode) {
		label = txKeyed ? 'TX ACTIVE' : (keyed ? 'RX ACTIVE' : (connecting ? 'CONNECTING' : 'CONNECTED'));
		detail = channelTitle(activeNode);
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
	document.title = label + ' - AllScan Simple';
}

function updateWarning(show, activeNode, extras, directNodes) {
	if(!show) {
		simple.warningPanel.classList.add('hidden');
		return;
	}
	var nodes = activeNode ? extras : directNodes;
	simple.warningDetail.textContent = nodes.length
		? 'Additional connected node(s): ' + nodes.join(', ')
		: 'Additional nodes are connected.';
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
		+ '&autodisc=true');
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

function statMsg(msg) {
	if(!simple.statmsg || !msg)
		return;
	simple.statmsg.innerHTML = msg;
}

function channelTitle(node) {
	var channel = simple.channelByNode[node];
	if(!channel)
		return 'Node ' + node;
	return channelLabel(channel) + ' (' + node + ')';
}

function channelLabel(channel) {
	var label = channel.label || channel.name || channel.node || '';
	if(channel.node) {
		var nodePat = new RegExp('(^|[\\s,;:-]+)' + escapeRegExp(channel.node) + '([\\s,;:-]*$|(?=[\\s,;:-]+))', 'g');
		label = label.replace(nodePat, function(match, prefix) {
			return prefix && prefix.trim() ? prefix : '';
		});
	}
	label = label.replace(/\s{2,}/g, ' ').replace(/[\s,;:-]+$/g, '').trim();
	return label || channel.name || channel.node || '';
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
