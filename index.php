<?php
function formatBytes($size, $precision = 6, $b = 'B')
{
    $base = @log($size, 1024);
    $suffixes = array('', 'K'.$b, 'M'.$b, 'G'.$b, 'T'.$b);
    $ret = round(pow(1024, $base - floor($base)), $precision).' '.$suffixes[floor($base)];
    return $ret === 'NAN ' ? '' : $ret;
}

function req( $method, $params = [] ) {
	$ch = curl_init();

	$p = [];
	$p['jsonrpc'] = "2.0";
	$p['id'] = "qwer";
	$p['method'] = "aria2.".$method;
	$p['params'] = $params;

	curl_setopt($ch, CURLOPT_URL, 'http://localhost:6800/jsonrpc');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $p ) );
	curl_setopt($ch, CURLOPT_POST, 1);

	$headers = array();
	$headers[] = 'Content-Type: application/json';
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	$result = curl_exec($ch);
	if (curl_errno($ch)) {
	    echo 'Error:' . curl_error($ch);
	}
	curl_close($ch);

	return json_decode($result, 1);
}


if( isset( $_GET['stop'] ) ) {
	shell_exec("killall aria2c");
	header("Location: index.php");
	exit();	
}

if( isset( $_GET['start'] ) ) {
	$ret = shell_exec("aria2c --enable-rpc=true --daemon=true");
	sleep(2);
	$res = req('changeGlobalOption', [ [ 'dir' => __dir__.'/files', 'max-connection-per-server' => '16', 'split' => '16', 'min-split-size' => '1M' ] ] );
	header("Location: index.php");
	exit();
}

if( isset( $_GET['new'] ) ) {
	$urls = [ $_POST['url'] ];

	$ret = req('addUri', [ $urls ] );

	$id = @$ret['result'];
	if( $id ) {
		$ret = req('changeOption', [ $id, ['max-connection-per-server' => $_POST['connections'] ] ] );
	}

	echo json_encode( $ret );

	exit();
}

if( isset( $_GET['changeOption'] ) ) {

	$ret = req('changeOption', [ $_GET['changeOption'], $_POST ] );

	echo json_encode($ret);

	exit();
}


if( isset( $_GET['changeGlobalOption'] ) ) {

	$ret = req('changeGlobalOption', [ $_POST ] );

	echo json_encode($ret);

	exit();
}

if( isset( $_GET['pause'] ) ) {
	$ret = req('pause', [ $_GET['pause'] ] );	
	exit();
}

if( isset( $_GET['unpause'] ) ) {
	$ret = req('unpause', [ $_GET['unpause'] ] );	
	exit();
}

if( isset( $_GET['getOption'] ) ) {
	$ret = req('getOption', [ $_GET['getOption'] ] );
	echo json_encode($ret);
	exit();
}

if( isset( $_GET['getGlobalOption'] ) ) {
	$ret = req('getGlobalOption', [] );
	echo json_encode($ret);
	exit();
}

if( isset( $_GET['remove'] ) ) {
	$ret = req('remove', [ $_GET['remove'] ] );
	$ret = req('forceRemove', [ $_GET['remove'] ] );
	echo json_encode($ret);
	exit();
}

function pushArray( &$ref, $array ) {
	foreach( $array as $v ) {
		$ref[] = $v;
	}
}

if( isset( $_GET['refresh'] ) ) {
	echo json_encode( makeList( $_GET['refresh'] ) );
	exit();
}

function makeList( $status = 1 ) {
	$list = [];
	if( $status != 2 ) {
		$list = req('tellActive')['result'];

		pushArray( $list, req('tellWaiting', [ 0,10 ] )['result'] );

		if( $status == 1 ) {
			return $list;
		}
	}

	$fin = req('tellStopped', [ 0,10 ] )['result'];
	foreach( $fin as $v ) {
		$v['fin'] = true;
		$list[] = $v;
	}

	return $list;	
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Aria2c Web GUI</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

	<link rel="stylesheet" type="text/css" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

	<style>
	[v-cloak] {
	  display: none;
	}	
	</style>

</head>
<body>
	<div id="app" class="container">
	<h1>Aria2c</h1>

	<a href="?start" class="btn btn-success">Start aria2c daemon</a>
	<a href="?stop" class="btn btn-danger">Stop aria2c daemon</a>
	<a href="?getGlobalOption" class="globalOpts btn btn-primary">Global Configuration</a>
	<br />
	<br />

	<form class="ajaxForm form-inline" method="post" action="?new">
		Url : <input type="text" class="form-control" name="url"> &nbsp;
		Connections : <input type="text" class="form-control" name="connections" value="16" style="width: 100px"> &nbsp;
		<input type="submit" class="btn btn-danger" value="Add">
	</form>

	<br />

	<a class="chStatus btn btn-danger btn-sm" href="?status=0">All Status</a>
	<a class="chStatus btn btn-primary btn-sm" href="?status=1">Active</a>
	<a class="chStatus btn btn-success btn-sm" href="?status=2">Finished</a>  
	<br />
	<br />

	<table v-cloak class="table">
		<tr>
			<th>Name</th>
			<th>Status</th>
			<th>Size</th>
			<th>Downloaded</th>
			<th>Speed</th>
			<th>Connections</th>
			<th>Opts</th>
		</tr>
		<tr v-for="v in lists" :set="file = v.files[0]" :class="v['fin']?'bg-success':''">

			<td>{{baseName(file.path)}}</td>
			<td>
				<a class="ajax" v-if="v.status === 'paused'" :href="'?unpause='+v.gid"><span class="badge badge-danger">{{v.status}}</span></a>
				<a class="ajax" v-if="v.status === 'active'" :href="'?pause='+v.gid"><span class="badge badge-success">{{v.status}}</span></a>
			</td>

			<td>{{formatBytes(file.length)}}</td>
			<td>{{formatBytes(file.completedLength)}}</td>
			<td>{{formatBytes(v.downloadSpeed)}}</td>
			<td>{{(v.connections)}}</td>

			<td>
				<a :gid="v.gid" :title="baseName(file.path)" :href="'?getOption='+v.gid" class="editOpts"><i class="fa fa-cogs text-info"></i></a>
				<a :href="'?remove='+v.gid" class="ajax"><i class="fa fa-trash text-danger"></i></a>
			</td>
		</tr>
	</table>

	<div class="modal fade" id="optsModal" tabindex="-1" role="dialog" aria-labelledby="OptsModalLabel" aria-hidden="true">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="modal-title" id="exampleModalLabel">Edit {{options.name}}</h5>
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">
	        <form class="ajaxForm" method="post" :action="'?changeOption='+options.gid">
	    
				<div class="form-group">
					<label for="email"> Dir : </label>
					<input type="text" class="form-control" name="dir">
				</div>

				<div class="form-group">
					<label for="email"> Max Connections : </label>
					<input type="text" class="form-control" name="max-connection-per-server">
				</div>

				<div class="form-group">
					<label for="email"> Split : </label>
					<input type="text" class="form-control" name="split">
				</div>

				<div class="form-group">
					<label for="email"> Min Split Size : </label>
					<input type="text" class="form-control" name="min-split-size">
				</div>

				<div class="form-group">
					<label for="email"> Http Proxy : </label>
					<input type="text" class="form-control" name="http-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Https Proxy : </label>
					<input type="text" class="form-control" name="https-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Ftp Proxy : </label>
					<input type="text" class="form-control" name="ftp-proxy">
				</div>

				<button type="submit" class="btn btn-primary">Save changes</button>
	        </form>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
	        
	      </div>
	    </div>
	  </div>
	</div>

	<div class="modal fade" id="globaloptsModal" tabindex="-1" role="dialog" aria-labelledby="OptsModalLabel" aria-hidden="true">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="modal-title" id="exampleModalLabel">Edit Global</h5>
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">
	        <form class="ajaxForm" method="post" action="?changeGlobalOption">

				<div class="form-group">
					<label for="email"> Dir : </label>
					<input type="text" class="form-control" name="dir">
				</div>

				<div class="form-group">
					<label for="email"> Max Connections : </label>
					<input type="text" class="form-control" name="max-connection-per-server">
				</div>

				<div class="form-group">
					<label for="email"> Split : </label>
					<input type="text" class="form-control" name="split">
				</div>

				<div class="form-group">
					<label for="email"> Min Split Size : </label>
					<input type="text" class="form-control" name="min-split-size">
				</div>

				<div class="form-group">
					<label for="email"> Http Proxy : </label>
					<input type="text" class="form-control" name="http-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Https Proxy : </label>
					<input type="text" class="form-control" name="https-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Ftp Proxy : </label>
					<input type="text" class="form-control" name="ftp-proxy">
				</div>

				<button type="submit" class="btn btn-primary">Save changes</button>
	        </form>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
	        
	      </div>
	    </div>
	  </div>
	</div>

	<div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="OptsModalLabel" aria-hidden="true">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="modal-title" id="exampleModalLabel">Edit Global</h5>
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">

	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
	        
	      </div>
	    </div>
	  </div>
	</div>

	</div>

</body>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>


<script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>

<script>
function basename(path) {
   return path.split('/').reverse()[0];
}
function formatBytes(a,b){if(0==a)return"0 Bytes";var c=1024,d=b||2,e=["Bytes","KB","MB","GB","TB","PB","EB","ZB","YB"],f=Math.floor(Math.log(a)/Math.log(c));return parseFloat((a/Math.pow(c,f)).toFixed(d))+" "+e[f]}

var app = new Vue({
  el: '#app',
  data: {
    lists : {},
    options : {},
    globalOptions : {},
  },
  methods: {
  	baseName : function(str) {return basename(str);},
  	formatBytes : function(a,b) {return formatBytes(a,b);},
  }
});
var status = 1;
setInterval( interval = function() {
	$.ajax({
	  url: "?refresh="+status,
	  dataType : 'JSON',
	}).done(function( data ) {
	  	app.lists = data;
	});
}, 1000);

interval();


$("#app").on("click", ".chStatus", function() {

	status = parseInt($(this).attr('href').match(/status\=([0-9]+)/)[1]);
	return false;
});

$("#app").on("click", ".editOpts", function() {
	var elm = $(this);
	$.ajax({
	  url: $(this).attr('href'),
	  dataType : 'JSON',
	}).done(function( data ) {
		data.result.name = elm.attr('title');
		data.result.gid = elm.attr('gid');
		app.options = data.result;
	  	$("#optsModal").modal('show');
	  	var form = $("#optsModal");
	  	for( var x in data.result ) {
	  		var val = data.result[x];
	  		form.find("[name='"+x+"']").val( val );
	  	}	  	
	});

	return false;
});

$("#app").on("click", ".globalOpts", function() {
	var elm = $(this);
	$.ajax({
	  url: $(this).attr('href'),
	  dataType : 'JSON',
	}).done(function( data ) {
		//app.globalOptions = data.result;
	  	$("#globaloptsModal").modal('show');
	  	var form = $("#globaloptsModal");
	  	for( var x in data.result ) {
	  		var val = data.result[x];
	  		form.find("[name='"+x+"']").val( val );
	  	}
	});

	return false;
});

$("#app").on("click", ".ajax", function() {
	$.ajax({
	  url: $(this).attr('href'),
	  dataType : 'JSON',
	}).done(function( data ) {
	  	
	});
	return false;
});

$("#app").on("submit", ".ajaxForm" , function() {
	
	$.ajax({
		data : $(this).serialize(),
		url: $(this).attr('action'),
		dataType : 'JSON',
		method : 'POST',
	}).done(function( data ) {
	  	$("#optsModal").modal('hide');
	  	$("#globaloptsModal").modal('hide');

	  	if( data.error ) {
	  		$("#errorModal").modal('show');
	  		$("#errorModal .modal-body").html( '<div class="alert alert-danger">'+data.error.message+'</div>' );
	  	}

	});
	return false;
});
</script>
</html>