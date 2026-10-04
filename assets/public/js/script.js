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

		// The server enforces one vote per visitor. This list is only a UX cache so the buttons
		// look right before the popup has loaded; the server's answer replaces it.
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
		// A vote request in flight for this task id, so a slower popup reload can't undo it.
		let pendingVote = null;
		const clearVoted = (taskId) => {
			try {
				const voted = getVoted();
				delete voted[ taskId ];
				localStorage.setItem( votedKey, JSON.stringify( voted ) );
			} catch (e) {}
		};
		const renderVoteState = (taskId) => {
			const type = getVoted()[ taskId ];
			// With vote changing on, only the current vote is locked; the other button stays usable.
			const lock = ! type ? $() : ( NIROROADMAP.settings.allow_vote_change ? $( `#nr-${type}` ) : $( ".nr-vote-btn" ) );
			$( ".nr-vote-btn" ).prop( "disabled", false ).removeClass( "nr-voted" );
			lock.prop( "disabled", true );
			if (type) {
				$( `#nr-${type}` ).addClass( "nr-voted" );
			}
		};
		// Counts the server leaves out are hidden by a setting (or only for admins).
		const showCounts = (taskId, data) => {
			$( "#nr-upvote-count" ).text( data.upvotes || 0 ).toggle( data.upvotes !== undefined );
			$( "#nr-downvote-count" ).text( data.downvotes || 0 ).toggle( data.downvotes !== undefined );
			if (data.upvotes !== undefined) {
				$( `#nr-task-${taskId} .nr-task-votes-count` ).text( data.upvotes );
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
			$( "#nr-vote-notice" ).text( "" );
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
					// The popup may have moved on to another card while this was loading.
					if ($( "#nr-modal-id" ).val() !== String( taskId )) {
						return;
					}

					const task = response.data.task;
					$( "#nr-modal-title" ).text( task.title );
					$( "#nr-modal-description" ).removeClass( "nr-loading" ).html( task.description );
					showCounts( taskId, task );

					// The server knows what this visitor voted, even after clearing site data.
					// Skip while their own vote is still being saved: this answer may predate it.
					if (pendingVote !== taskId) {
						if (task.voted) {
							setVoted( taskId, task.voted );
						} else {
							clearVoted( taskId );
						}
						renderVoteState( taskId );
					}
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
				$( "#nr-vote-notice" ).text( "" );
				pendingVote = taskId;

				request( `/tasks/${taskId}/vote`, "POST", { type: type } ).done(
					function (response) {
						pendingVote = null;
						showCounts( taskId, response.data );
						setVoted( taskId, response.data.vote || type );
						renderVoteState( taskId );
					}
				).fail(
					function (xhr) {
						pendingVote = null;
						const data = xhr.responseJSON && xhr.responseJSON.data;
						$( "#nr-vote-notice" ).text( (data && data.message) || NIROROADMAP.settings.vote_failed );

						// Already voted (409): show the vote the server has on record.
						if (data && data.vote) {
							setVoted( taskId, data.vote );
							showCounts( taskId, data );
						}
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
