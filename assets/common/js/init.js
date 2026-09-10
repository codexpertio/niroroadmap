const niroroadmap_modal = ( show = true ) => {
	const modal         = document.getElementById( 'niroroadmap-modal' );
	if ( show ) {
		modal.style.display = '';
	} else {
		modal.style.display = 'none';
	}
}