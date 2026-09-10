jQuery(
	function ($) {

		$( '.niroroadmap-settings-form' ).on(
			'reset',
			function (e) {
				e.preventDefault();
				niroroadmap_modal();

				$.ajax(
					{
						url: `${NIROROADMAP_PLUGIN_ADMIN.api_base}/option`,
						type: 'DELETE',
						dataType: 'JSON',
						data: {
							key: $( this ).data( 'option_key' )
						},
						headers: {
							'X-WP-Nonce': NIROROADMAP_PLUGIN_ADMIN.nonce,
						},
						success: (resp) => {
							console.log( 'Settings deleted:', resp );
							location.reload();
						},
						error: (err) => {
							console.error( 'Failed to delete settings', err );
							niroroadmap_modal( false );
						},
					}
				);
			}
		);

		$( '.niroroadmap-settings-form' ).submit(
			function (e) {
				e.preventDefault();
				niroroadmap_modal();

				let formData = $( this ).serializeArray();
				let data     = {};

				// Convert serialized data array into an object
				$.each(
					formData,
					function () {
						if (data[this.name]) {
							if ( ! data[this.name].push) {
								data[this.name] = [data[this.name]];
							}
							data[this.name].push( this.value || '' );
						} else {
							data[this.name] = this.value || '';
						}
					}
				);

				$.ajax(
					{
						url: `${NIROROADMAP_PLUGIN_ADMIN.api_base}/option`,
						type: 'POST',
						dataType: 'JSON',
						data: {
							key: $( this ).data( 'option_key' ),
							value: data
						},
						headers: {
							'X-WP-Nonce': NIROROADMAP_PLUGIN_ADMIN.nonce,
						},
						success: (resp) => {
							console.log( 'Settings saved:', resp );
							niroroadmap_modal( false );
						},
						error: (err) => {
							console.error( 'Failed to save settings', err );
							niroroadmap_modal( false );
						},
					}
				);
			}
		);
	}
);