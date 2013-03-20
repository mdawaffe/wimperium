/*
 * Usage:
 * 	WimperiumRest.request( path );
 * 	WimperiumRest.request( path, request );
 * 	WimperiumRest.request( request );
 *    
 * Arguments:
 * 	path     : the REST URL path to request (will be appended to the rest base URL)
 * 	request  : request parameters: method (string), path (string), query (object), body (object)
 *
 * Returns
 * 	A jQuery promise() whose callbacks accept the following arguments:
 * 		response : the JSON response for your request
 * 		statusCode : the HTTP statusCode for your request
 *
 * Example:
 * 	// For simple GET requests
 * 	WimperiumRest.request( '/posts/1' ).done( function( response, statusCode ){
 * 		/// ...
 * 	} );
 *
 * 	// More Advanced GET request
 * 	WimperiumRest.request( {
 * 		path: '/posts/'
 * 		query: { number: 100 }
 * 	} );
 * 
 * 	// POST request
 * 	WimperiumRest.request( {
 * 		method: 'POST',
 * 		path: '/posts/'
 * 		body: { content: 'This is a post' }
 * 	} );
 */
(function($){
	var proxy,
	origin         = window.location.protocol + '//' + window.location.hostname,
	proxyOrigin    = WimperiumRest.proxyOrigin,

	ready          = false,
	supported      = 'function' === typeof window.postMessage,
	structuredData = true, // supports passing of structured data

	bufferedOps    = [],   // store requests while we wait for the proxy iframe to initialize
	deferreds      = {};

	// Initialize the proxy iframe
	function buildProxy() {
		// Start listening to messages
		$( window ).on( 'message', receive );

		proxy = document.createElement( 'iframe' );
		proxy.src = WimperiumRest.proxyURL + '#' + origin;
		proxy.style.display = 'none';

		// Process any buffered API calls after the iframe proxy is ready
		$( proxy ).bind( 'load', function() {
			var request;
			ready = true;
			while ( request = bufferedOps.shift() ) {
				sendRequest( request );
			}
		});

		// Bring it
		$( document ).ready( function() {
			$( document.body ).append( proxy );
		} );
	}

	// Message event listener
	function receive( e ) {
		var event,
		    data,
		    deferred_id,
		    deferred;

		// look at the real event, not the jQuery mocked one
		event = e.originalEvent;
		if ( event.origin !== proxyOrigin ) {
			return;
		}

		data = structuredData ? event.data : JSON.parse( event.data );

		if ( 'undefined' === typeof data[2] ) {
			return;
		}

		deferred_id = data[2];

		if ( 'undefined' === typeof deferreds[deferred_id] ) {
			return;
		}

		deferred = deferreds[deferred_id];
		delete deferreds[deferred_id];
		deferred.resolve( data[0], data[1] );
	}

	// Calls API
	function perform() {
		var request = buildRequest.apply( null, arguments );

		sendRequest( request );

		return deferreds[request.callback].promise();
	}

	// Buffers API request
	function buffer() {
		var request = buildRequest.apply( null, arguments );

		bufferedOps.push( request );

		return deferreds[request.callback].promise();
	}

	// Submits the API request to the proxy iframe
	function sendRequest( request ) {
		var data = structuredData ? request : JSON.stringify( request );

		proxy.contentWindow.postMessage( data, proxyOrigin );
	}

	// Builds the postMessage request object
	function buildRequest() {
		var args     = jQuery.makeArray( arguments ),
		    request  = args.pop(),
		    path     = args.pop(),
		    deferred = new jQuery.Deferred(),
		    deferred_id;

		if ( 'string' === typeof( request ) ) {
			request = { path: request };
		}

		if ( path ) {
			request.path = path;
		}

		do {
			deferred_id = Math.random();
		} while ( 'undefined' !== typeof deferreds[deferred_id] );

		deferreds[deferred_id] = deferred;

		request.callback = deferred_id;
		return request;
	}

	// Can we pass structured data via postMessage or just strings?
	function check( event ){
		structuredData = 'object' === typeof event.originalEvent.data;
		$( window ).off( 'message', check );
		buildProxy();
	}

	function proxy_request() {
		if ( !supported ) {
			throw( 'Browser does not support window.postMessage' );
		}

		if ( ready ) {
			// Make API request
			return perform.apply( null, arguments );
		} else {
			// Buffer API request
			return buffer.apply( null, arguments );
		}
	}

	function proxy_rebuild() {
		if ( !ready )
			return;

		ready = false;
		$(proxy).remove();

		buildProxy();
	}

	WimperiumRest.request = proxy_request;
	WimperiumRest.rebuild = proxy_rebuild;

	// Step 1: do we have postMessage
	if ( 'function' === typeof window.postMessage ) {
		// Step 2: Check if we can pass structured data or just strings
		$( window ).bind( 'message', check );
		window.postMessage( {}, origin );
	} else {
		supported = false;
	}
})(jQuery);
