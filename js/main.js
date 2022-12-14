var apiDir = '';
var source, xhttp;
var hb;
var clrMsgTimer;
var enUrl='';

function initEventStream(dir, url) {
	apiDir = dir;
	if(typeof(EventSource)!=="undefined") {
		// Start SSE
		source = new EventSource(dir + url);
		source.onerror = handleEventSourceError;
		hb = document.getElementById("hb");
		window.addEventListener("beforeunload",function() {	source.close();	});
		// Handle node data, update whole Conn Status table
		source.addEventListener('nodes', handleNodesEvent, false);
		// Handle nodetimes data, update Conn Status time columns
		source.addEventListener('nodetimes', handleNodetimesEvent, false);
		// Handle connection data
		source.addEventListener('connection', handleConnectionEvent, false);
	} else {
		alert("ERROR: Your browser does not support server-sent events.");
	}
}

function statMsg(msg, timeMs=5000) {
	const e = document.getElementById('statmsg');
	e.innerHTML = (e.innerHTML === '') ? msg : (e.innerHTML + '<br>' + msg);
	clrMsgTimer = setTimeout('clearStatMsg()',timeMs);
}
function clearStatMsg() {
	const e = document.getElementById('statmsg');
	e.innerHTML = '';
}

function handleEventSourceError(event) {
	console.log("Event Source error: " + event);
}

function handleConnectionEvent(event) {
	var statusdata = JSON.parse(event.data);
	//console.log('ConnectionEvent: ' + statusdata.status);
	tableID = 'table_' + statusdata.node;
	//$('#' + tableID + ' tbody:first').html('<tr><td colspan="7">' + statusdata.status + '</td></tr>');
	const table = document.getElementById(tableID);
	var tbody0 = table.getElementsByTagName('tbody')[0];
	tbody0.innerHTML = '<tr><td colspan="7">' + statusdata.status + '</td></tr>';
	statMsg(statusdata.status);
}

function handleNodesEvent(event) {
	//console.log('nodes: ' + event.data);
	var tabledata = JSON.parse(event.data);
	for(var localNode in tabledata) {
		//console.log('node=' + localNode);
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
			if(rowdata.info === "NO CONNECTION") {
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
		tbody0.innerHTML = tablehtml;
	}
}

function handleNodetimesEvent(event) {
	//console.log('nodetimes: ' + event.data);
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
	var perm = document.getElementById('permanent').checked;
	if(remoteNode.length == 0) {
		alert('Please enter the remote node number.');
		return;
	}
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = handleXhttpResponse;
	parms = 'remotenode=' + remoteNode + '&perm=' + perm + '&button=' + button + '&localnode=' + localNode;
	xhttp.open("POST", apiDir + 'connect.php', true);
	xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xhttp.send(parms);
}
function disconnectNode() {
	var localNode = document.getElementById('localnode').value;
	var remoteNode = document.getElementById('node').value;
	var perm = document.getElementById('permanent').checked;
	if(remoteNode.length == 0) {
		alert('Please enter the remote node number.');
		return;
	}
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = handleXhttpResponse;
	parms = 'remotenode=' + remoteNode + '&perm=' + perm + '&button=disconnect' + '&localnode=' + localNode;
	xhttp.open("POST", apiDir + 'connect.php', true);
	xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xhttp.send(parms);
}
function handleXhttpResponse() {
	if(xhttp.readyState === 4) {
		if(xhttp.status === 200) {
			//console.log(xhttp.responseText);
			statMsg(xhttp.responseText);
		} else {
			//console.log('err: ReqStat=' + xhttp.status);
			statMsg('Error response from server: ' + xhttp.status + '<br>Hit [F5] to reload page');
		}
	}
}

function setNodeBox(n) {
	var remoteNode = document.getElementById('node');
	remoteNode.value = n;
}

function enableXhttp(url){
	checkTimer=setTimeout('checkServerAlerts("'+url+'")',1000);
	enUrl=url;
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
