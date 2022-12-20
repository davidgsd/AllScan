var apiDir='/allscan/astapi/';
var statsDir='/allscan/stats/';
var source, xhttp;
var hb;
var enUrl='';
var reloadRetries=0, reloadTimer;
var statsState=0;

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
	//setTimeout(getStats, 250);
}

function getStats() {
	const table = document.getElementById('favs_' + localNode);
	var rnodes = [];
	switch(statsState) {
		case 0: // init
			// Parse favorites table for node numbers
			for(var r=0, n=table.rows.length-1; r < n; r++) {
				//for(var c=0, m=table.rows[r].cells.length; c < m; c++) {
				rnodes[r] = table.rows[r+1].cells[1].innerHTML;
			}
			var cnt = rnodes.length;
			statMsg('Requesting ASL Stats for '+cnt+' nodes...');
			var parms = 'nodes=' + rnodes.join(',');
			//console.log(rnodes.join(','));
			// Call stats/stats.php with node list
			xhttpStatsInit(statsDir + 'stats.php', parms);
			break;
	}
	setTimeout(getStats, 60000);
}
function xhttpStatsInit(url, parms) {
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = handleXhttpStatsResponse;
	xhttp.open('POST', url, true);
	xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhttp.send(parms);
}
function handleXhttpStatsResponse() {
	if(xhttp.readyState === 4) {
		if(xhttp.status === 200) {
			//statMsg('statsResponse: ' + xhttp.responseText);
			var resp = JSON.parse(xhttp.responseText);
			//varDump(resp);
			var e = resp.event;
			if(resp.data !== undefined)
				var data = resp.data;
			var status = data.status;
			statMsg('statsResponse: event='+e + ', status='+status);
		} else {
			statMsg('Error response from server: ' + xhttp.status);
			statMsg('[F5] to Reload');
		}
	}
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
	reloadTimer = setTimeout(reloadPage, 2000);
}
function handleOfflineEvent() {
	statMsg('Offline event received.');
	reloadRetries=0;
	if(reloadTimer !== undefined) {
		clearTimeout(reloadTimer);
	}
}
function reloadPage() {
	// Verify location valid before reloading
	if(reloadRetries == 0)
		statMsg('Verifying node is accessible...');
	var request = new XMLHttpRequest();
	request.open('GET', window.location, true);
	request.onreadystatechange = function() {
		if(request.status == 200) {
			request.abort();
			window.location.reload();
		} else if(reloadRetries < 8) { // Try again after a delay
			reloadRetries++;
			var s = request.status > 0 ? ' (stat=' + request.status + ')' : '';
			var t = 2 + reloadRetries;
			statMsg(`Node unreachable${s}, will retry in ${t} Secs...`);
			reloadTimer = setTimeout(reloadPage, t * 1000);
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
	const table = document.getElementById(tableID);
	var tbody0 = table.getElementsByTagName('tbody')[0];
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
		const table = document.getElementById('table_' + localNode);
		tbody0 = table.getElementsByTagName('tbody')[0];
		const conncnt = document.getElementById('conncnt');
		tbody0.innerHTML = tablehtml;
		conncnt.value = total_nodes;
	}
}
function handleNodetimesEvent(event) {
	var tabledata = JSON.parse(event.data);
	for(localNode in tabledata) {
		tableID = 'table_' + localNode;
		table = document.getElementById(tableID);
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
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = handleXhttpResponse;
	xhttp.open('POST', url, true);
	xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhttp.send(parms);
}
function handleXhttpResponse() {
	if(xhttp.readyState === 4) {
		if(xhttp.status === 200) {
			statMsg(xhttp.responseText);
		} else {
			statMsg('Error response from server: ' + xhttp.status);
			statMsg('[F5] to Reload');
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

function checkServerAlerts(url) {
	var tag = document.getElementById('test');
	var val = tag.innerHTML;
	val = '&val='+val;
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange=function() {
		if(xhttp.readyState === 4) {
			if(xhttp.status === 200) {
				var xml = xhttp.responseXML;
				var res = getConnStatusObj(xml);
				tag.innerHTML = res.test;
				checkTimer = setTimeout('checkServerAlerts("'+url+'")',2000);
			} else {
				tag.innerHTML = 'err: ReqStat=' + xhttp.status;
				checkTimer = setTimeout('checkServerAlerts("'+url+'")',30000);
			}
		}
	};
	xhttp.open("GET", url+val, true);
	xhttp.send(null);
}
function getConnStatusObj(xml) {
	var rows=xml.getElementsByTagName('status');
	if(rows.length < 1)
		return null;
	var row=rows[0];
	var tags=["test"];
	var res={};
	for(var i=0; i < tags.length; i++) {
		var tag=row.getElementsByTagName(tags[i])[0];
		res[tags[i]]=tag.firstChild.data;
	}
	return res;
}
