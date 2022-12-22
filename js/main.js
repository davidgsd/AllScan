var apiDir='/allscan/astapi/';
var statsDir='/allscan/stats/';
var source, xh, hb;
var rldRetries=0, rldTmr;
var statsState=0, statsIdx=0, statsReqCnt=0, favsCnt=0, xhs, statsTmr, ftbl;

function initEventStream(url) {
	if(typeof(EventSource) === 'undefined') {
		alert("ERROR: Your browser does not support server-sent events.");
		return;
	}
	// Start SSE
	source = new EventSource(apiDir + url);
	source.onerror = handleEventSourceError;
	hb = document.getElementById('hb');
	window.addEventListener('beforeunload', function() { source.close(); });
	// Handle node data, update whole Conn Status table
	source.addEventListener('nodes', handleNodesEvent, false);
	// Handle nodetimes data, update Conn Status time columns
	source.addEventListener('nodetimes', handleNodetimesEvent, false);
	// Handle connection data
	source.addEventListener('connection', handleConnectionEvent, false);
	// Handle error responses
	source.addEventListener('errMsg', handleErrMsgEvent, false);
	// Check for offline/online events. If PC/phone goes to sleep or loses connection
	// we should be able to detect when it's restored and re-init the event stream
	window.addEventListener('online', handleOnlineEvent);
	window.addEventListener('offline', handleOfflineEvent);
	// Call a test function...
	//setTimeout(handleOnlineEvent, 5000);
	// Call initAslApi() who will read in nodes in the favorites list and do various API requests to get their 
	// current status eg. last heard, num connected nodes, last keyed, etc.
	ftbl = document.getElementById('favs');
	setTimeout(getStats, 250);
}

function getStats() {
	// ASL API request limit is 30 reqs/minute. In case more than one node is on this server ideally do no
	// more than one req per 4 secs and increase that time if any requests return an error.
	var rnodes=[], node;
	switch(statsState) {
		case 0: // init
			// Parse favorites table for node numbers
			for(var r=0, n=ftbl.rows.length-1; r < n; r++) {
				//for(var c=0, m=ftbl.rows[r].cells.length; c < m; c++) {
				node = ftbl.rows[r+1].cells[1].innerHTML
				if(node >= 2000 && node < 2000000)
					rnodes[r] = ftbl.rows[r+1].cells[1].innerHTML;
			}
			favsCnt = rnodes.length;
			if(favsCnt == 0) {
				//statsTmr = setTimeout(getStats, 15000);
				return;
			}
			if(statsIdx >= favsCnt)
				statsIdx=0;
			var node = rnodes[statsIdx];
			//statMsg('Requesting ASL Stats for ' + node + '...');
			//var parms = 'node=' + rnodes.join(',');
			var parms = 'node=' + node;
			xhttpStatsInit(statsDir + 'stats.php', parms);
			statsReqCnt++;
			break;
	}
	// statsTmr = setTimeout(getStats, 60000);
}
function xhttpStatsInit(url, parms) {
	xhs = new XMLHttpRequest();
	xhs.onreadystatechange = handleStatsResponse;
	xhs.open('POST', url, true);
	xhs.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhs.send(parms);
}
function handleStatsResponse() {
	if(xhs.readyState === 4) {
		if(xhs.status === 200) {
			//statMsg('statsResponse: ' + xhs.responseText);
			var resp = JSON.parse(xhs.responseText);
			// Data structure: event=stats; status=LogMsg; stats=statsStruct
			var e = resp.event;
			if(resp.data.stats === undefined) {
				if(resp.data.retcode != 429)
					scanMsg(resp.data.status + '. Will retry in 15 Seconds...');
				statsTmr = setTimeout(getStats, 15000);
				return;
			}
			statsIdx++;
			var s = resp.data.stats;
			var row;
			// Update the favs table. Node keyed=maroon background, active=green, show #connections column
			for(var r=0, n=ftbl.rows.length-1; r < n; r++) {
				//for(var c=0, m=ftbl.rows[r].cells.length; c < m; c++) {
				var cells = ftbl.rows[r+1].cells;
				var node = cells[1].innerHTML;
				if(node != s.node)
					continue;
				// Tx=$s->keyed Act=$s->active $s->timeAgo LCnt=$s->linkCnt Bsy%=$s->busyPct TxTm=$s->txtime WT=$s->wt
				// Colors: Tx=maroon Busy=0-50% green LinkCnt:0-50 blue
				// Cols #:Tx/Act/NotAct LCnt:linkCnt (Blue 0-50) Bsy%:busyPct (Green 0-50)
				var c0 = cells[0];
				scanMsg(c0.innerHTML + ': ' + resp.data.status);
				if(s.keyed == 1)
					c0.className = 'tColor';
				else if(s.active == 1)
					c0.className = (s.wt == 1) ? 'wColor' : 'gColor';
				else
					c0.className = '';
				// Color Bsy%t column green, max 50%
				var busy = cells[5];
				var grn = Math.round(Math.log(3 + s.busyPct) * 5);
				if(grn > 50)
					grn = 50;
				busy.innerHTML = s.busyPct;
				busy.style.backgroundColor = (s.busyPct > 5) ? 'hsl(150,50%,'+grn+'%)' : 'transparent';
				// Color LCnt column blue, max 50% = 50 links
				var lcnt = cells[6];
				var blue = Math.round(Math.log(3 + s.linkCnt) * 8);
				if(blue > 30)
					blue = 30;
				lcnt.innerHTML = s.linkCnt;
				lcnt.style.backgroundColor = (s.linkCnt > 3) ? 'hsl(240,40%,'+blue+'%)' : 'transparent';
			}
			statsTmr = setTimeout(getStats, calcReqIntvl());
		} else {
			statMsg('/stats/ HTTP error ' + xhs.status + '. Retrying in 60 seconds...');
			statsTmr = setTimeout(getStats, 60000);
		}
	}
}
function calcReqIntvl() {
	// Do initial stats requests quickly to populate Favs table then reduce request rate so if multiple
	// clients are using the node the ASL stats request limit (30/min) will be less likely to be exceeded
	var t = 1000;
	if(statsReqCnt > 9000)
		t = 10000; // 6/min after 12hrs
	else if(statsReqCnt > 4000)
		t = 6000; // 10/min after 4hrs
	else if(statsReqCnt > 1200)
		t = 4000; // 15/min after 1hr
	else if(statsReqCnt > favsCnt || statsReqCnt > 25)
		t = 3000; // 20/min after initial scan
	return t;
}

function handleEventSourceError(event) {
	if(event !== null && typeof event === 'object')
		event = JSON.stringify(event);
	var msg = (event === '{"isTrusted":true}') ? 'Check internet/LAN connections and power to node' : event;
	msg = 'Event Source error: ' + msg;
	statMsg(msg);
	//console.log(msg);
}

function handleOnlineEvent() {
	statMsg('Online event received. Reloading...');
	rldTmr = setTimeout(reloadPage, 2000);
}
function handleOfflineEvent() {
	statMsg('Offline event received.');
	rldRetries=0;
	if(rldTmr !== undefined) {
		clearTimeout(rldTmr);
	}
}
function reloadPage() {
	// Verify location valid before reloading
	if(rldRetries == 0)
		statMsg('Verifying node is accessible...');
	var request = new XMLHttpRequest();
	request.open('GET', window.location, true);
	request.onreadystatechange = function() {
		if(request.status == 200) {
			request.abort();
			window.location.reload();
		} else if(rldRetries < 8) { // Try again after a delay
			rldRetries++;
			var s = request.status > 0 ? ' (stat=' + request.status + ')' : '';
			var t = 2 + rldRetries;
			statMsg(`Node unreachable${s}, will retry in ${t} Secs...`);
			rldTmr = setTimeout(reloadPage, t * 1000);
		} else {
			statMsg('Node unreachable. Check internet/LAN connections and power to node.');
		}
	};
	request.send();
}
function statMsg(msg) {
	const e = document.getElementById('statmsg');
	if(e.innerHTML.length > 50000)
		e.innerHTML = '';
	e.innerHTML = (e.innerHTML === '') ? msg : (e.innerHTML + '<br>' + msg);
	e.scrollTop = e.scrollHeight;
}
function clearStatMsg() {
	const e = document.getElementById('statmsg');
	e.innerHTML = '';
}
function scanMsg(msg) {
	const e = document.getElementById('scanmsg');
	e.innerHTML = msg;
}

function handleErrMsgEvent(event) {
	var data = JSON.parse(event.data);
	statMsg('ERROR: ' + data.status);
}
function handleConnectionEvent(event) {
	var data = JSON.parse(event.data);
	statMsg(data.status);
	//console.log('ConnectionEvent: ' + data.status);
	if(!data.node)
		return;
	tableID = 'table_' + data.node;
	//$('#' + tableID + ' tbody:first').html('<tr><td colspan="7">' + data.status + '</td></tr>');
	const cstbl = document.getElementById(tableID);
	var tbody0 = cstbl.getElementsByTagName('tbody')[0];
	tbody0.innerHTML = '<tr><td colspan="7">' + data.status + '</td></tr>';
}
function handleNodesEvent(event) {
	var tabledata = JSON.parse(event.data);
	for(var localNode in tabledata) {
		var tablehtml = '';
		var total_nodes = 0;
		var cos_keyed = 0;
		var tx_keyed = 0;
		for(row in tabledata[localNode].remote_nodes) {
			var rowdata = tabledata[localNode].remote_nodes[row];
			if(rowdata.cos_keyed == 1)
				cos_keyed = 1;
			if(rowdata.tx_keyed == 1)
				tx_keyed = 1;
		}
		if(cos_keyed == 0) {
			if(tx_keyed == 0)
				tablehtml += '<tr class="gColor"><td colspan="1">' + localNode +
					'</td><td colspan="1">Idle</td><td colspan="5"></td></tr>';
			else
				tablehtml += '<tr class="tColor"><td colspan="1">' + localNode +
					'</td><td colspan="1">PTT-Keyed</td><td colspan="5"></td></tr>';
		} else {
			if(tx_keyed == 0)
				tablehtml += '<tr class="lColor"><td colspan="1">' + localNode +
					'</td><td colspan="1">COS-Detected</td><td colspan="5"></td></tr>';
			else
				tablehtml += '<tr class="bColor"><td colspan="1">' + localNode +
					'</td><td colspan="2">COS-Detected, PTT-Keyed</td><td colspan="4"></td></tr>';
		}
		for(row in tabledata[localNode].remote_nodes) {
			var rowdata = tabledata[localNode].remote_nodes[row];
			if(rowdata.info === 'NO CONNECTION') {
				tablehtml += '<tr><td colspan="7">No Connections</td></tr>';
			} else {
				nodeNum = rowdata.node;
				if(nodeNum != 1) {
					total_nodes++
					// Set background color
					if(rowdata.keyed == 'yes') {
						tablehtml += '<tr class="rColor">';
					} else if(rowdata.mode == 'C') {
						tablehtml += '<tr class="cColor">';
					} else {
						tablehtml += '<tr>';
					}
					var id = 't' + localNode + 'c0' + 'r' + row;
					tablehtml += '<td id="' + id + '" class="nodeNum" onClick="setNodeBox(' + nodeNum + ')">' +
						nodeNum + '</td>';
					// Show info or IP
					if(rowdata.info != "") {
						tablehtml += '<td>' + rowdata.info + '</td>';
					} else {
						tablehtml += '<td>' + rowdata.ip + '</td>';
					}
					tablehtml += '<td id="lkey' + row + '">' + rowdata.last_keyed + '</td>';
					tablehtml += '<td>' + rowdata.link + '</td>';
					tablehtml += '<td>' + rowdata.direction + '</td>';
					tablehtml += '<td id="elap' + row +'">' +
						rowdata.elapsed + '</td>';
					// Show mode
					if(rowdata.mode == 'R') {
						tablehtml += '<td>Receive Only</td>';
					} else if(rowdata.mode == 'T') {
						tablehtml += '<td>Transceive</td>';
					} else if(rowdata.mode == 'C') {
						tablehtml += '<td>Connecting</td>';
					} else {
						tablehtml += '<td>' + rowdata.mode + '</td>';
					}
					tablehtml += '</tr>';
				}
			}
		}
		// Display Count
		if(total_nodes > 1) {
			tablehtml += '<tr><td colspan="7">' + total_nodes + ' nodes connected</td></tr>';
		}
		// $('#table_' + localNode + ' tbody:first').html(tablehtml);
		const cstbl = document.getElementById('table_' + localNode);
		tbody0 = cstbl.getElementsByTagName('tbody')[0];
		const conncnt = document.getElementById('conncnt');
		tbody0.innerHTML = tablehtml;
		conncnt.value = total_nodes;
	}
}
function handleNodetimesEvent(event) {
	var tabledata = JSON.parse(event.data);
	for(localNode in tabledata) {
		tableID = 'table_' + localNode;
		cstbl = document.getElementById(tableID);
		for(row in tabledata[localNode].remote_nodes) {
			var rowdata = tabledata[localNode].remote_nodes[row];
			rowID='lkey' + row;
			//$( '#' + tableID + ' #' + rowID).text( rowdata.last_keyed );
			tableRow = document.getElementById(rowID);
			if(tableRow !== null)
				tableRow.innerHTML = rowdata.last_keyed; // Received

			rowID='elap' + row;
			//$( '#' + tableID + ' #' + rowID).text( rowdata.elapsed );
			tableRow = document.getElementById(rowID);
			if(tableRow !== null)
				tableRow.innerHTML = rowdata.elapsed; // Connected
		}
	}
	hb.style.visibility = (hb.style.visibility == 'visible') ? "hidden" : "visible";
}

function connectNode(button) {
	var localNode = document.getElementById('localnode').value;
	var remoteNode = document.getElementById('node').value;
	if(remoteNode < 1) {
		alert('Please enter a valid remote node number.');
		return;
	}
	var perm = document.getElementById('permanent').checked;
	// Disconnect before Connect checkbox. Only applies if conncnt > 0
	var autodisc = document.getElementById('autodisc').checked;
	var conncnt = document.getElementById('conncnt').value;
	if(conncnt < 1)
		autodisc = false;
	parms = 'remotenode='+remoteNode + '&perm='+perm + '&button='+button + '&localnode='+localNode + '&autodisc='+autodisc;
	xhttpSend(apiDir + 'connect.php', parms);
}
function disconnectNode() {
	var localNode = document.getElementById('localnode').value;
	var remoteNode = document.getElementById('node').value;
	if(remoteNode.length == 0) {
		alert('Please enter the remote node number.');
		return;
	}
	var perm = document.getElementById('permanent').checked;
	parms = 'remotenode='+remoteNode + '&perm='+perm + '&button=disconnect' + '&localnode='+localNode;
	xhttpSend(apiDir + 'connect.php', parms);
}
function astrestart() {
	var localNode = document.getElementById('localnode').value;
	parms = 'localnode='+localNode;
	xhttpSend(apiDir + 'restart.php', parms);
	// Reload page
	statMsg("Reloading in 500mS...");
	setTimeout(function() { window.location.reload(); }, 500);
}
function xhttpSend(url, parms) {
	xh = new XMLHttpRequest();
	xh.onreadystatechange = handleXhttpResponse;
	xh.open('POST', url, true);
	xh.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xh.send(parms);
}
function handleXhttpResponse() {
	if(xh.readyState === 4) {
		if(xh.status === 200) {
			statMsg(xh.responseText);
		} else {
			statMsg('Error response from server: ' + xh.status);
		}
	}
}

function setNodeBox(n) {
	var remoteNode = document.getElementById('node');
	remoteNode.value = n;
}

function varDump(v) {
	const logLines = ["Property (Typeof): Value", `Var (${typeof v}): ${v}`];
	for(const prop in v)
		logLines.push(`${prop} (${typeof v[prop]}): ${v[prop] || "n/a"}`);
	console.log(logLines.join("\n"));
}
