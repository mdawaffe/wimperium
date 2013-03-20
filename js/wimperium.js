Wimperium = (function($) {

	function submit( event ) {
		var $this = $( this );

console.log( this );
console.log( event );
		event.preventDefault();

		WimperiumRest.request( {
			method: 'POST',
			path  : '/posts/',
			body  : {
				title: $this.find( '.title' ).val(),
				content: $this.find( '.content' ).val()
			},
		} ).done( function( response, status ) {
			console.log( response, status );
		} ).fail( function() {
			console.error( "Wimperium Broke :(" );
		} );
	}

	$( document ).on( 'ready', function() {
		$( '.wimperium-post' ).submit( submit );
	} );

	return {
		submit: submit
	};
})(jQuery);
