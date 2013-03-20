<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!DOCTYPE html>
<html>
<head>
<?php
                
wp_print_scripts( 'jquery' );
  
?>
<script>
(function() {
var cookies, cookie = '', i,
    bufferMessage = true, emptyBuffer, buffer = [],
    origin = window.location.hash.replace( '#', '' ).split( '/', 3 ).join( '/' ),
    ajaxSetup = { dataType: 'json' };

function receiveMessage( e ) {
	var event = e.originalEvent,
	    data,
	    sendAsString = false;

	if ( origin !== event.origin ) {
		return;
	}


	if ( bufferMessage ) {
		buffer.push( event );
		return;
	}

	if ( 'string' === typeof event.data ) {
		data = JSON.parse( event.data );
		sendAsString = true;
	} else {
		data = event.data;
	}

	var url = <?php echo json_encode( admin_url( 'admin-post.php?action=wimperium_rest' ) ); ?> + '&http_envelope=1&path=' + data.path;

	if ( data.query ) {
		if ( 'string' === typeof data.query ) {
			url += '&' + data.query.replace( /^&/, '' );
		} else {
			url += '&' + jQuery.param( data.query );
		}
	}

	jQuery.ajax( {
		cache: false,
		url : url,
		type : data.method,
		success : sendMessage( data.callback, sendAsString ),
		error : sendError( data.callback, sendAsString ),
		data : 'GET' === data.method ? null : data.body
	} );
}

function sendMessage( callback, sendAsString ) {
	return function( data ) {
		var info;
		if ( sendAsString ) {
			info = JSON.stringify( [ data.body, data.code, callback ] );
		} else {
			info = [ data.body, data.code, callback ];
		}

		window.parent.postMessage( info, origin );
	}
}

function sendError( callback, sendAsString ) {
	return function() {
		var info;
		if ( sendAsString ) {
			info = JSON.stringify( [ 'error', 0, callback ] );
		} else {
			info = [ 'error', 0, callback ];
		}
	
		window.parent.postMessage( info, origin );
	}
}

function emptyBuffer() {
	var event;
	while ( event = buffer.shift() ){
		receiveMessage( event );
	}
}

if ( !origin || ! window.postMessage ) {
	return;
}

jQuery( window ).on( 'message', receiveMessage );

cookies = document.cookie.split( /;\s*/ );
for ( i = 0; i < cookies.length; i++ ) {
	if ( cookies[i].match( /^wimperium-rest=/ ) ) {
		cookies = cookies[i].split( '=' );
		cookie = cookies[1];
		break;
	}
}

if ( cookie ) {
	ajaxSetup['beforeSend'] = function( jqXHR ) {
		jqXHR.setRequestHeader( 'Authorization', 'X-WPCOOKIE ' + cookie );
	};
}

jQuery.ajaxSetup( ajaxSetup );

bufferMessage = false;
emptyBuffer();
})();
</script>
</head>
<html>
