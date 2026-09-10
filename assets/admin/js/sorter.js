jQuery(
	function ($) {

		$( '.taxonomy-niroroadmap_status #the-list' ).sortable(
			{
				cursor: 'grabbing',
				axis: 'y',
				containment: '#the-list',
				update: function (event, ui) {
					$.ajax(
						{
							url: `${NIROROADMAP.api_base}/stages/order`,
							method: "POST",
							headers: {
								'X-WP-Nonce': NIROROADMAP.nonce,
							},
							data: {
								order: $( this ).sortable( 'toArray', {attribute: 'id'} )
							},
							success: function (response) {
								console.log( response );
							},
							error: function (error) {
								console.error( error );
							}
						}
					);
				}
			}
		);
	}
);
