jQuery(
	function ($) {

		const request = (path, method, data) => $.ajax(
			{
				url: `${NIROROADMAP.api_base}${path}`,
				method: method,
				headers: {
					'X-WP-Nonce': NIROROADMAP.nonce,
				},
				data: data,
			}
		);

		const updateCounts = () => {
			$( ".nr-kanban-column" ).each(
				function () {
					$( ".nr-stage-count", this ).text( $( ".nr-kanban-item", this ).length );
				}
			);
		};

		// Votes are remembered per browser so a visitor can't vote on the same task twice.
		const votedKey = "niroroadmap_votes";
		const getVoted = () => {
			try {
				return JSON.parse( localStorage.getItem( votedKey ) ) || {};
			} catch (e) {
				return {};
			}
		};
		const setVoted = (taskId, type) => {
			try {
				const voted     = getVoted();
				voted[ taskId ] = type;
				localStorage.setItem( votedKey, JSON.stringify( voted ) );
			} catch (e) {}
		};
		const renderVoteState = (taskId) => {
			const type = getVoted()[ taskId ];
			$( ".nr-vote-btn" ).prop( "disabled", !! type ).removeClass( "nr-voted" );
			if (type) {
				$( `#nr-${type}` ).addClass( "nr-voted" );
			}
		};

		// Drag and drop between stages (editors only)
		$( ".task-editor .nr-kanban-list" ).sortable(
			{
				items: ".nr-kanban-item",
				connectWith: ".task-editor .nr-kanban-list",
				placeholder: "nr-ui-state-highlight",
				delay: 150,
				start: function (event, ui) {
					ui.placeholder.height( ui.item.outerHeight() );
					ui.item.addClass( "nr-dragging" );
				},
				stop: function (event, ui) {
					ui.item.removeClass( "nr-dragging" );

					const taskId = ui.item.attr( "id" ).replace( "nr-task-", "" );
					const list   = ui.item.parent();

					request( `/tasks/${taskId}/move`, "POST", { stage: list.closest( ".nr-kanban-column" ).data( "stage" ) } );
					request( "/tasks/order", "POST", { order: list.sortable( "toArray", { attribute: "id" } ) } );

					updateCounts();
				}
			}
		);

		// macOS Quick Look-style zoom: the popup grows out of (and shrinks back into) the clicked card.
		const reduceMotion = window.matchMedia( "(prefers-reduced-motion: reduce)" ).matches;
		const zoomFrames   = (card) => {
			const modal = document.getElementById( "nr-modal" );
			const from  = card[0].getBoundingClientRect();
			const to    = modal.getBoundingClientRect();

			if ( ! modal.animate) {
				return null;
			}
			if (reduceMotion || ! from.width) {
				return [ { opacity: 0 }, { opacity: 1 }, { opacity: 1 } ];
			}

			const dx = (from.left + from.width / 2) - (to.left + to.width / 2);
			const dy = (from.top + from.height / 2) - (to.top + to.height / 2);

			return [
				{ transform: `translate(${dx}px, ${dy}px) scale(${from.width / to.width}, ${from.height / to.height})`, opacity: 0 },
				{ opacity: 1, offset: 0.35 },
				{ transform: "none", opacity: 1 },
			];
		};

		const overlay = $( "#nr-modal-overlay" );
		let hideTimer;

		const openModal = (card) => {
			const taskId = card.attr( "id" ).replace( "nr-task-", "" );
			const column = card.closest( ".nr-kanban-column" );
			const tags   = card.data( "tags" ) || [];

			$( "#nr-modal-id" ).val( taskId );
			$( "#nr-modal-stage" ).css( "--nr-stage-color", column.css( "--nr-stage-color" ) );
			$( "#nr-modal-stage-name" ).text( $.trim( column.find( ".nr-stage-name" ).text() ) );
			$( "#nr-modal-title" ).text( card.find( ".nr-task-title" ).text() );
			$( "#nr-modal-tags" ).empty().append( tags.map( (tag) => $( "<li class='nr-tag'>" ).text( tag ) ) );
			$( "#nr-upvote-count" ).text( card.find( ".nr-task-votes-count" ).text() );
			$( "#nr-downvote-count" ).text( "–" );
			$( "#nr-modal-description" ).addClass( "nr-loading" ).text( "Loading…" );
			renderVoteState( taskId );

			const modal = document.getElementById( "nr-modal" );
			modal.getAnimations && modal.getAnimations().forEach( (a) => a.cancel() );

			// Only the backdrop fades; the popup itself stays solid while it zooms.
			clearTimeout( hideTimer );
			overlay.show();
			overlay[0].offsetWidth; // Commit display before the backdrop transition starts.
			overlay.addClass( "nr-open" );
			$( "body" ).addClass( "nr-modal-scroll-lock" );

			const frames = zoomFrames( card );
			if (frames) {
				modal.animate( frames, { duration: 380, easing: "cubic-bezier(0.2, 0.9, 0.25, 1)" } );
			}
			$( "#nr-close-modal" ).trigger( "focus" );

			request( `/tasks/${taskId}`, "GET" ).done(
				function (response) {
					const task = response.data.task;
					$( "#nr-modal-title" ).text( task.title );
					$( "#nr-modal-description" ).removeClass( "nr-loading" ).html( task.description );
					$( "#nr-upvote-count" ).text( task.upvotes || 0 );
					$( "#nr-downvote-count" ).text( task.downvotes || 0 );
				}
			).fail(
				function () {
					$( "#nr-modal-description" ).text( "Could not load details." );
				}
			);

			$( "#nr-modal-overlay" ).data( "opener", card );
		};

		const closeModal = () => {
			if ( ! overlay.hasClass( "nr-open" )) {
				return;
			}

			const opener = $( "#nr-modal-overlay" ).data( "opener" );
			const frames = opener ? zoomFrames( opener ) : null;

			if (frames) {
				document.getElementById( "nr-modal" ).animate( [ frames[2], { opacity: 1, offset: 0.65 }, frames[0] ], { duration: 280, easing: "cubic-bezier(0.4, 0, 0.8, 0.4)", fill: "forwards" } );
			}

			overlay.removeClass( "nr-open" );
			hideTimer = setTimeout( () => overlay.hide(), 280 );
			$( "body" ).removeClass( "nr-modal-scroll-lock" );
			if (opener) {
				opener.trigger( "focus" );
			}
		};

		// Open modal
		$( ".nr-kanban-columns" ).on(
			"click",
			".nr-kanban-item",
			function () {
				if ( ! $( this ).hasClass( "ui-sortable-helper" )) {
					openModal( $( this ) );
				}
			}
		).on(
			"keydown",
			".nr-kanban-item",
			function (e) {
				if (e.key === "Enter" || e.key === " ") {
					e.preventDefault();
					openModal( $( this ) );
				}
			}
		);

		// Handle upvote/downvote
		$( ".nr-vote-btn" ).on(
			"click",
			function () {
				const voteBtn = $( this );
				const taskId  = $( "#nr-modal-id" ).val();
				const type    = voteBtn.data( "type" );

				$( ".nr-vote-btn" ).prop( "disabled", true );

				request( `/tasks/${taskId}/vote`, "POST", { type: type } ).done(
					function (response) {
						$( ".nr-vote-count", voteBtn ).text( response.data.votes );
						if (type === "upvote") {
							$( `#nr-task-${taskId} .nr-task-votes-count` ).text( response.data.votes );
						}
						setVoted( taskId, type );
						renderVoteState( taskId );
					}
				).fail(
					function () {
						renderVoteState( taskId );
					}
				);
			}
		);

		// Close modal
		$( "#nr-modal-overlay" ).on(
			"click",
			function (e) {
				if (e.target.id === "nr-modal-overlay") {
					closeModal();
				}
			}
		);
		$( "#nr-close-modal" ).on( "click", closeModal );
		$( document ).on(
			"keydown",
			function (e) {
				if (e.key === "Escape" && $( "#nr-modal-overlay" ).is( ":visible" )) {
					closeModal();
				}
			}
		);

	}
);
