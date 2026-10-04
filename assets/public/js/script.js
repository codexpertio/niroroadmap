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

		// Comments. Loaded only when a popup opens; all text is built with .text() except the
		// comment body, which the server has already escaped and filtered to <a>, <p>, <br>.
		const commentsBox = $( "#nr-comments" );
		const C           = NIROROADMAP.settings.comments || {};
		let commentsState = null; // { taskId, page, totalPages, state }

		const announce = (message) => $( "#nr-comments-live" ).text( "" ).text( message );

		const setCardCount = (taskId, count) => {
			$( `#nr-task-${taskId} .nr-task-comments-count` ).text( count );
		};

		const setCommentsCount = (count) => {
			$( "#nr-comments-count" ).text( count ? `(${count})` : "" );
		};

		const renderComment = (c) => {
			const li   = $( "<li class='nr-comment'>" ).attr( "id", `nr-comment-${c.id}` ).attr( "data-id", c.id );
			const body = $( "<div class='nr-comment-body'>" );
			const head = $( "<div class='nr-comment-head'>" );

			if (c.avatar) {
				li.append( $( "<img class='nr-comment-avatar' width='36' height='36' loading='lazy' alt=''>" ).attr( "src", c.avatar ) );
			}

			head.append( $( "<strong class='nr-comment-author'>" ).text( c.author ) );
			if (c.team) {
				head.append( $( "<span class='nr-badge-team'>" ).text( C.team ) );
			}
			head.append( $( "<time class='nr-comment-date'>" ).attr( "datetime", c.date ).text( c.date_human ) );
			if (c.pending) {
				head.append( $( "<span class='nr-comment-pending'>" ).text( C.awaiting ) );
			}

			body.append( head, $( "<div class='nr-comment-content'>" ).html( c.content ) );

			// One level of replies only.
			if ( ! c.parent && commentsState && commentsState.state.can_comment) {
				body.append( $( "<button type='button' class='nr-link-btn nr-comment-reply'>" ).text( C.reply ).attr( "data-id", c.id ).attr( "data-author", c.author ) );
			}

			if ( ! c.parent) {
				body.append( $( "<ol class='nr-comment-replies'>" ).append( (c.replies || []).map( renderComment ) ) );
			}

			return li.append( body );
		};

		// Add a comment to the list, unless it's already there (a later page can repeat one we just posted).
		const insertComment = (c) => {
			if ($( `#nr-comment-${c.id}` ).length) {
				return;
			}

			const li = renderComment( c );

			if (c.parent && $( `#nr-comment-${c.parent}` ).length) {
				$( `#nr-comment-${c.parent} > .nr-comment-body > .nr-comment-replies` ).append( li );
			} else if (NIROROADMAP.settings.comments_newest) {
				$( "#nr-comments-list" ).prepend( li );
			} else {
				$( "#nr-comments-list" ).append( li );
			}
		};

		const resetReply = () => {
			$( "#nr-comment-parent" ).val( 0 );
			$( "#nr-replying" ).prop( "hidden", true );
		};

		const showCommentForm = (state) => {
			const form  = $( "#nr-comment-form" );
			const note  = $( "#nr-comments-note" );
			const guest = ! state.logged_in;

			form.prop( "hidden", ! state.can_comment );
			note.text( "" );

			if ( ! state.open) {
				note.text( C.closed );
			} else if ( ! state.can_comment) {
				const login = $( "<a>" ).text( C.login ).attr( "href", NIROROADMAP.settings.login_url + (NIROROADMAP.settings.login_url.indexOf( "?" ) === -1 ? "?" : "&") + "redirect_to=" + encodeURIComponent( window.location.href ) );
				note.append( login );
			}

			form.find( ".nr-guest-field" ).prop( "hidden", ! guest );
			form.find( ".nr-required" ).filter( "#nr-name-required, #nr-email-required" ).prop( "hidden", ! state.require_name_email );
			$( "#nr-comment-text" ).attr( "maxlength", state.max_length );
		};

		const loadComments = (taskId, page) => {
			const first = page === 1;

			if (first) {
				commentsState = { taskId: taskId, page: 1, totalPages: 1, state: { can_comment: false } };
				$( "#nr-comments-list" ).empty();
				$( "#nr-comments-more" ).prop( "hidden", true );
				$( "#nr-comment-form" ).prop( "hidden", true );
				$( "#nr-comment-error" ).text( "" );
				setCommentsCount( 0 );
				resetReply();
				commentsBox.prop( "hidden", false );
				$( "#nr-comments-note" ).text( C.loading );
			}

			request( `/tasks/${taskId}/comments`, "GET", { page: page } ).done(
				function (response) {
					// The popup may have moved on to another card while this was loading.
					if ($( "#nr-modal-id" ).val() !== String( taskId )) {
						return;
					}

					const data = response.data;

					commentsState = { taskId: taskId, page: data.page, totalPages: data.total_pages, state: data.state };

					if (first) {
						showCommentForm( data.state );
					}

					data.comments.forEach( (c) => insertComment( c ) );
					data.pending.forEach( (c) => insertComment( c ) );

					setCommentsCount( data.count );
					setCardCount( taskId, data.count );
					$( "#nr-comments-more" ).prop( "hidden", data.page >= data.total_pages );

					if (first && ! data.count && ! data.pending.length) {
						$( "#nr-comments-note" ).text( data.state.open ? C.none : C.closed );
					} else if (first && data.state.open && data.state.can_comment) {
						$( "#nr-comments-note" ).text( "" );
					}
				}
			).fail(
				function (xhr) {
					// 404 means comments are off for this item: show nothing at all.
					if (xhr.status === 404) {
						commentsBox.prop( "hidden", true );
					} else if ($( "#nr-modal-id" ).val() === String( taskId )) {
						$( "#nr-comments-note" ).text( C.load_failed );
					}
				}
			);
		};

		$( "#nr-comments-more" ).on(
			"click",
			function () {
				if (commentsState && commentsState.page < commentsState.totalPages) {
					loadComments( commentsState.taskId, commentsState.page + 1 );
				}
			}
		);

		$( "#nr-comments-list" ).on(
			"click",
			".nr-comment-reply",
			function () {
				$( "#nr-comment-parent" ).val( $( this ).data( "id" ) );
				$( "#nr-replying-to" ).text( C.replying_to.replace( "%s", $( this ).data( "author" ) ) );
				$( "#nr-replying" ).prop( "hidden", false );
				$( "#nr-comment-text" ).trigger( "focus" );
			}
		);
		$( "#nr-reply-cancel" ).on(
			"click",
			function () {
				resetReply();
				$( "#nr-comment-text" ).trigger( "focus" );
			}
		);

		$( "#nr-comment-form" ).on(
			"submit",
			function (e) {
				e.preventDefault();

				const form   = $( this );
				const error  = $( "#nr-comment-error" );
				const submit = $( "#nr-comment-submit" );
				const taskId = commentsState && commentsState.taskId;
				const state  = commentsState && commentsState.state;

				error.text( "" );

				if ( ! taskId) {
					return;
				}

				if ( ! $.trim( $( "#nr-comment-text" ).val() )) {
					error.text( C.empty );
					$( "#nr-comment-text" ).trigger( "focus" );
					return;
				}

				if (state && ! state.logged_in && state.require_name_email && ( ! $.trim( $( "#nr-comment-name" ).val() ) || ! $.trim( $( "#nr-comment-email" ).val() ) )) {
					error.text( C.need_name );
					$( "#nr-comment-name" ).trigger( "focus" );
					return;
				}

				submit.prop( "disabled", true ).text( C.sending );

				request( `/tasks/${taskId}/comments`, "POST", form.serialize() ).done(
					function (response) {
						const data = response.data;

						if (data.comment) {
							if ($( "#nr-modal-id" ).val() === String( taskId )) {
								insertComment( data.comment );
							}
						}

						if (data.count !== undefined) {
							setCommentsCount( data.count );
							setCardCount( taskId, data.count );
						}

						$( "#nr-comments-note" ).text( "" );
						// Keep the guest's name and email for their next comment; clear only what they wrote.
						$( "#nr-comment-text" ).val( "" );
						resetReply();
						announce( data.message || C.posted );
					}
				).fail(
					function (xhr) {
						const data = xhr.responseJSON && xhr.responseJSON.data;
						error.text( (data && data.message) || C.failed );
					}
				).always(
					function () {
						submit.prop( "disabled", false ).text( C.submit );
					}
				);
			}
		);

		// Suggest an idea. The dialog exists only when a board on this page offers it.
		const suggestOverlay = $( "#nr-suggest-overlay" );

		if (suggestOverlay.length) {
			const S         = NIROROADMAP.settings.suggest || {};
			const suggest   = $( "#nr-suggest" );
			const sForm     = $( "#nr-suggest-form" );
			const sError    = $( "#nr-suggest-error" );
			const sTitle    = $( "#nr-suggest-name-title" );
			let sOpener     = null;
			let sOpenedAt   = 0;
			let sHideTimer  = null;
			let sSearchTimer = null;
			let sQuery      = "";

			// Everything keyboard-focusable inside the dialog, for the focus trap.
			const sFocusable = () => suggest.find( "button, input, select, textarea, a[href]" ).filter(
				function () {
					return ! this.disabled && this.type !== "hidden" && this.tabIndex !== -1 && $( this ).is( ":visible" );
				}
			);

			const openSuggest = (button) => {
				sOpener = button;
				clearTimeout( sHideTimer );

				if (sForm.length) {
					sForm[0].reset();
					sForm.prop( "hidden", false );

					// A board that shows one product files ideas under it; otherwise the visitor picks.
					const product = String( button.attr( "data-product" ) || "" );
					const fixed   = product !== "" && product !== "0";
					$( "#nr-suggest-product-fixed" ).val( fixed ? product : "" ).prop( "disabled", ! fixed );
					$( "#nr-suggest-product" ).prop( "disabled", fixed );
					$( "#nr-suggest-product-field" ).prop( "hidden", fixed );
				}

				sError.text( "" );
				$( "#nr-suggest-similar" ).prop( "hidden", true );
				$( "#nr-suggest-done" ).prop( "hidden", true );
				sOpenedAt = Date.now();

				suggestOverlay.show();
				suggestOverlay[0].offsetWidth; // Commit display before the backdrop transition starts.
				suggestOverlay.addClass( "nr-open" );
				$( "body" ).addClass( "nr-modal-scroll-lock" );

				( sForm.length ? sTitle : $( "#nr-suggest-close" ) ).trigger( "focus" );
			};

			const closeSuggest = () => {
				if ( ! suggestOverlay.hasClass( "nr-open" )) {
					return;
				}

				clearTimeout( sSearchTimer );
				suggestOverlay.removeClass( "nr-open" );
				sHideTimer = setTimeout( () => suggestOverlay.hide(), 280 );
				$( "body" ).removeClass( "nr-modal-scroll-lock" );

				if (sOpener) {
					sOpener.trigger( "focus" );
				}
			};

			$( document ).on(
				"click",
				".nr-suggest-btn",
				function () {
					openSuggest( $( this ) );
				}
			);

			$( "#nr-suggest-close, #nr-suggest-done-close" ).on( "click", closeSuggest );
			suggestOverlay.on(
				"click",
				function (e) {
					if (e.target.id === "nr-suggest-overlay") {
						closeSuggest();
					}
				}
			);

			// Escape closes; Tab stays inside the dialog while it's open.
			suggestOverlay.on(
				"keydown",
				function (e) {
					if (e.key === "Escape") {
						e.stopPropagation();
						closeSuggest();
						return;
					}

					if (e.key !== "Tab") {
						return;
					}

					const items = sFocusable();
					if ( ! items.length) {
						e.preventDefault();
						return;
					}

					const first = items.first()[0];
					const last  = items.last()[0];

					if (e.shiftKey && (document.activeElement === first || ! suggest[0].contains( document.activeElement ))) {
						e.preventDefault();
						last.focus();
					} else if ( ! e.shiftKey && document.activeElement === last) {
						e.preventDefault();
						first.focus();
					}
				}
			);

			// While typing a title, point at ideas that already exist.
			const showSimilar = (items) => {
				const list = $( "#nr-suggest-similar-list" ).empty();

				items.forEach(
					(item) => {
						const card = $( `#nr-task-${item.id}` );
						const row  = $( "<li>" );

						if (card.length) {
							row.append( $( "<button type='button' class='nr-link-btn'>" ).text( item.title ).on(
								"click",
								function () {
									closeSuggest();
									setTimeout( () => card.trigger( "click" ), 320 );
								}
							) );
						} else {
							row.text( item.title );
						}

						list.append( row );
					}
				);

				$( "#nr-suggest-similar" ).prop( "hidden", ! items.length );
			};

			sTitle.on(
				"input",
				function () {
					const query = $.trim( this.value );
					clearTimeout( sSearchTimer );

					if (query.length < 3) {
						sQuery = "";
						showSimilar( [] );
						return;
					}

					sSearchTimer = setTimeout(
						() => {
							sQuery = query;
							request( "/tasks/search", "GET", { q: query } ).done(
								function (response) {
									// Ignore answers to an older query, or after the dialog closed.
									if (sQuery === query && suggestOverlay.hasClass( "nr-open" )) {
										showSimilar( response.data.items || [] );
									}
								}
							);
						},
						400
					);
				}
			);

			sForm.on(
				"submit",
				function (e) {
					e.preventDefault();

					const submit = $( "#nr-suggest-submit" );
					sError.text( "" );

					if ($.trim( sTitle.val() ).length < 3) {
						sError.text( S.need_title );
						sTitle.trigger( "focus" );
						return;
					}

					const who = $( "#nr-suggest-name, #nr-suggest-email" ).filter( function () { return $( this ).data( "required" ) === 1; } );
					if (who.length && who.filter( function () { return ! $.trim( this.value ); } ).length) {
						sError.text( S.need_identity );
						who.filter( function () { return ! $.trim( this.value ); } ).first().trigger( "focus" );
						return;
					}

					submit.prop( "disabled", true ).text( S.sending );

					// `elapsed` is how long the dialog was open: a script posting straight to the API sends none.
					request( "/tasks/submit", "POST", sForm.serialize() + "&elapsed=" + ( Date.now() - sOpenedAt ) ).done(
						function (response) {
							sForm.prop( "hidden", true );
							$( "#nr-suggest-done-message" ).text( response.data.message );
							$( "#nr-suggest-done" ).prop( "hidden", false ).trigger( "focus" );
						}
					).fail(
						function (xhr) {
							const data = xhr.responseJSON && xhr.responseJSON.data;
							sError.text( (data && data.message) || S.failed );
						}
					).always(
						function () {
							submit.prop( "disabled", false ).text( S.send );
						}
					);
				}
			);
		}

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

			if (commentsBox.length) {
				loadComments( taskId, 1 );
			}

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
