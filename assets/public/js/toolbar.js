/**
 * Search, sort and filter toolbar for roadmap boards.
 *
 * Works on the items already in the page; nothing is fetched. An item is a board card, a list row
 * or a timeline entry, so the same search and filters apply in every view. Each board with a
 * toolbar is handled on its own, so several boards on one page don't affect each other. State lives in the URL
 * (nr_q, nr_sort, nr_tag, nr_product; a second board adds _2, and so on).
 */
( function ( $ ) {
	'use strict';

	var i18n = ( window.NIROROADMAP_TOOLBAR && window.NIROROADMAP_TOOLBAR.i18n ) || {};
	var slice = Array.prototype.slice;

	/**
	 * Lower-case text with accents removed, plus a map from each folded character back to its index
	 * in the original, so a match can be highlighted in the text the visitor actually sees.
	 */
	function fold( str ) {
		var text = '';
		var map = [];

		for ( var i = 0; i < str.length; i++ ) {
			var folded = str.charAt( i ).normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase();

			for ( var j = 0; j < folded.length; j++ ) {
				text += folded.charAt( j );
				map.push( i );
			}
		}

		return { text: text, map: map };
	}

	/**
	 * The number shown in a card, read from the page so a vote cast in the popup counts.
	 */
	function readCount( card, selector ) {
		var el = card.node.querySelector( selector );
		var n = el ? parseInt( el.textContent, 10 ) : NaN;

		return isNaN( n ) ? 0 : n;
	}

	function Board( el, index ) {
		var suffix = index ? '_' + ( index + 1 ) : '';
		var search = el.querySelector( '[data-nr-search]' );
		var sortSelect = el.querySelector( '[data-nr-sort]' );
		var productSelect = el.querySelector( '[data-nr-product]' );
		var chips = slice.call( el.querySelectorAll( '[data-nr-tag]' ) );
		var resetBtn = el.querySelector( '[data-nr-reset]' );
		var status = el.querySelector( '[data-nr-status]' );
		var toggle = el.querySelector( '[data-nr-toggle]' );
		var tagsWrap = el.querySelector( '[data-nr-tags]' );
		var tagsToggle = el.querySelector( '[data-nr-tags-toggle]' );
		var tagsPanel = tagsWrap ? tagsWrap.querySelector( '.nr-tags-panel' ) : null;
		var tagsCount = el.querySelector( '[data-nr-tags-count]' );
		var toolbar = el.querySelector( '.nr-toolbar' );
		// Where items are sorted: a board column, the list's body, or one group of the timeline.
		var lists = slice.call( el.querySelectorAll( '.nr-kanban-list, .nr-list-body, .nr-timeline-items' ) );
		var defaultSort = el.getAttribute( 'data-nr-default-sort' ) || 'manual';
		var isEditor = document.body.classList.contains( 'task-editor' );
		var timer = null;
		var announced = false;
		var appliedSort; // The sort the items are in now, so the list can tell a new sort from a new search.

		var cards = slice.call( el.querySelectorAll( '.nr-kanban-item, .nr-view-item' ) ).map(
			function ( node, i ) {
				var title = node.querySelector( '.nr-task-title' );
				var text = title ? title.textContent : '';
				var names = [];

				try {
					names = JSON.parse( node.getAttribute( 'data-tags' ) || '[]' );
				} catch ( e ) {}

				return {
					node: node,
					title: title,
					text: text,
					id: node.getAttribute( 'data-task' ),
					index: i, // Manual order: where the server put it.
					foldedTitle: fold( text ),
					foldedAll: fold( text + ' ' + names.join( ' ' ) ).text,
					date: parseInt( node.getAttribute( 'data-date' ), 10 ) || 0,
					tags: ( node.getAttribute( 'data-tag-slugs' ) || '' ).split( ' ' ).filter( Boolean ),
					products: ( node.getAttribute( 'data-product-ids' ) || '' ).split( ' ' ).filter( Boolean ),
					pinned: node.getAttribute( 'data-pinned' ) === '1',
					marked: false
				};
			}
		);

		var state = { q: '', sort: defaultSort, tags: [], product: '' };

		// --- URL -------------------------------------------------------------------------------

		function optionValues( select ) {
			return select ? slice.call( select.options ).map( function ( o ) { return o.value; } ) : [];
		}

		function readUrl() {
			var params = new URLSearchParams( window.location.search );
			var sort = params.get( 'nr_sort' + suffix );
			var product = params.get( 'nr_product' + suffix );
			var known = chips.map( function ( c ) { return c.getAttribute( 'data-nr-tag' ); } );

			// Anything in the URL is untrusted: only values this board really offers are kept.
			state.q = search ? ( params.get( 'nr_q' + suffix ) || '' ).slice( 0, 200 ) : '';
			state.sort = sort && optionValues( sortSelect ).indexOf( sort ) !== -1 ? sort : defaultSort;
			state.product = product && optionValues( productSelect ).indexOf( product ) !== -1 ? product : '';
			state.tags = ( params.get( 'nr_tag' + suffix ) || '' ).split( ',' ).filter( function ( t ) { return known.indexOf( t ) !== -1; } );
		}

		function writeUrl() {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}

			var params = new URLSearchParams( window.location.search );

			function put( key, value, empty ) {
				if ( value && value !== empty ) {
					params.set( key + suffix, value );
				} else {
					params.delete( key + suffix );
				}
			}

			put( 'nr_q', state.q.trim(), '' );
			put( 'nr_sort', state.sort, defaultSort );
			put( 'nr_tag', state.tags.join( ',' ), '' );
			put( 'nr_product', state.product, '' );

			var query = params.toString();
			window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash );
		}

		// --- Rendering -------------------------------------------------------------------------

		function highlight( card, terms ) {
			if ( ! card.title ) {
				return;
			}

			var ranges = [];

			terms.forEach(
				function ( term ) {
					var from = 0;
					var at;

					while ( ( at = card.foldedTitle.text.indexOf( term, from ) ) !== -1 ) {
						ranges.push( [ card.foldedTitle.map[ at ], card.foldedTitle.map[ at + term.length - 1 ] + 1 ] );
						from = at + term.length;
					}
				}
			);

			if ( ! ranges.length ) {
				if ( card.marked ) {
					card.title.textContent = card.text;
					card.marked = false;
				}
				return;
			}

			// Merge overlapping ranges, then rebuild the title from text nodes and <mark>s (never innerHTML).
			ranges.sort( function ( a, b ) { return a[0] - b[0]; } );
			var merged = [ ranges[0] ];
			ranges.slice( 1 ).forEach(
				function ( r ) {
					var last = merged[ merged.length - 1 ];
					if ( r[0] <= last[1] ) {
						last[1] = Math.max( last[1], r[1] );
					} else {
						merged.push( r );
					}
				}
			);

			card.title.textContent = '';
			var pos = 0;
			merged.forEach(
				function ( r ) {
					if ( r[0] > pos ) {
						card.title.appendChild( document.createTextNode( card.text.slice( pos, r[0] ) ) );
					}
					var mark = document.createElement( 'mark' );
					mark.className = 'nr-mark';
					mark.textContent = card.text.slice( r[0], r[1] );
					card.title.appendChild( mark );
					pos = r[1];
				}
			);
			if ( pos < card.text.length ) {
				card.title.appendChild( document.createTextNode( card.text.slice( pos ) ) );
			}
			card.marked = true;
		}

		function compare( sort ) {
			return function ( a, b ) {
				// A pinned item leads its column whatever the sort.
				if ( a.pinned !== b.pinned ) {
					return a.pinned ? -1 : 1;
				}

				var diff = 0;

				if ( sort === 'votes' ) {
					diff = readCount( b, '.nr-task-votes-count' ) - readCount( a, '.nr-task-votes-count' );
				} else if ( sort === 'commented' ) {
					diff = readCount( b, '.nr-task-comments-count' ) - readCount( a, '.nr-task-comments-count' );
				} else if ( sort === 'newest' ) {
					diff = b.date - a.date;
				} else if ( sort === 'oldest' ) {
					diff = a.date - b.date;
				}

				// Ties, and "Manual order", keep the order the team set.
				return diff || a.index - b.index;
			};
		}

		function apply() {
			var terms = fold( state.q.trim() ).text.split( /\s+/ ).filter( Boolean );
			var shownIds = {};
			var allIds = {};

			cards.forEach(
				function ( card ) {
					var ok = true;

					if ( terms.length ) {
						ok = terms.every( function ( t ) { return card.foldedAll.indexOf( t ) !== -1; } );
					}
					if ( ok && state.tags.length ) {
						ok = state.tags.some( function ( t ) { return card.tags.indexOf( t ) !== -1; } );
					}
					if ( ok && state.product ) {
						ok = card.products.indexOf( state.product ) !== -1;
					}

					card.node.hidden = ! ok;
					allIds[ card.id ] = true;
					if ( ok ) {
						shownIds[ card.id ] = true;
					}
					highlight( card, ok ? terms : [] );
				}
			);

			// Sort inside each column. Hidden cards go along, so "Manual order" can restore them.
			lists.forEach(
				function ( list ) {
					var mine = cards.filter( function ( c ) { return c.node.parentNode === list; } ).sort( compare( state.sort ) );
					var visible = 0;

					mine.forEach(
						function ( c ) {
							list.appendChild( c.node );
							if ( ! c.node.hidden ) {
								visible++;
							}
						}
					);

					var badge = list.parentNode.querySelector( '.nr-stage-count' );
					if ( badge ) {
						badge.textContent = visible;
					}

					// A timeline group with nothing left to show goes away, with its heading.
					var group = list.closest( '[data-nr-group]' );
					if ( group ) {
						group.hidden = ! visible;
						group.querySelector( '.nr-group-count' ).textContent = visible;
					}
				}
			);

			// The list and the timeline say so themselves when a search leaves nothing.
			slice.call( el.querySelectorAll( '[data-nr-nomatch]' ) ).forEach(
				function ( note ) {
					var panel = note.closest( '[data-nr-view]' );

					note.hidden = ! ( panel.querySelector( '.nr-view-item' ) && ! panel.querySelector( '.nr-view-item:not([hidden])' ) );
				}
			);

			// An item appears once in every view, so count each only once.
			var shown = Object.keys( shownIds ).length;
			var total = Object.keys( allIds ).length;

			// Tells the list whether its column sorting still describes the order: a search leaves it alone, a new sort replaces it.
			el.dispatchEvent( new CustomEvent( 'nr:sorted', { detail: { changed: appliedSort !== state.sort } } ) );
			appliedSort = state.sort;

			var filtered = terms.length > 0 || state.tags.length > 0 || state.product !== '';
			var reorderOff = filtered || state.sort !== 'manual';

			el.classList.toggle( 'nr-filtered', filtered );
			el.classList.toggle( 'nr-reorder-off', reorderOff );

			// Dragging a filtered or sorted column would save the wrong order, so editors can't.
			if ( isEditor && $.fn.sortable ) {
				$( el ).find( '.nr-kanban-list.ui-sortable' ).sortable( 'option', 'disabled', reorderOff );
			}

			if ( resetBtn ) {
				resetBtn.hidden = ! ( filtered || state.sort !== defaultSort );
			}

			if ( status ) {
				status.textContent = filtered && shown === 0
					? ( i18n.no_match || 'No matching items' )
					: ( i18n.showing || 'Showing %1$s of %2$s' ).replace( '%1$s', shown ).replace( '%2$s', total );

				// Only now does it become a live region: the first count, written at load, isn't announced.
				if ( ! announced ) {
					announced = true;
					status.setAttribute( 'role', 'status' );
					status.setAttribute( 'aria-live', 'polite' );
				}
			}
		}

		function syncControls() {
			if ( search ) {
				search.value = state.q;
			}
			if ( sortSelect ) {
				sortSelect.value = state.sort;
			}
			if ( productSelect ) {
				productSelect.value = state.product;
			}
			chips.forEach(
				function ( chip ) {
					chip.setAttribute( 'aria-pressed', state.tags.indexOf( chip.getAttribute( 'data-nr-tag' ) ) !== -1 ? 'true' : 'false' );
				}
			);
			if ( tagsCount ) {
				tagsCount.hidden = ! state.tags.length;
				tagsCount.textContent = state.tags.length ? '(' + state.tags.length + ')' : '';
			}
		}

		// The tags dropdown. On phones CSS shows the chips inline and hides the button, so this only matters on wide screens.
		function setTagsOpen( open, restoreFocus ) {
			if ( ! tagsToggle || ! tagsPanel ) {
				return;
			}

			tagsPanel.hidden = ! open;
			tagsToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			// Open under the button's left edge; if that would run off the screen, line up with its right edge instead.
			if ( open ) {
				tagsPanel.classList.remove( 'nr-align-right' );

				if ( tagsPanel.getBoundingClientRect().right > document.documentElement.clientWidth - 8 ) {
					tagsPanel.classList.add( 'nr-align-right' );
				}
			}

			if ( ! open && restoreFocus ) {
				tagsToggle.focus();
			}
		}

		function changed() {
			apply();
			writeUrl();
		}

		// --- Events ----------------------------------------------------------------------------

		if ( search ) {
			search.addEventListener(
				'input',
				function () {
					clearTimeout( timer );
					timer = setTimeout(
						function () {
							state.q = search.value;
							changed();
						},
						150
					);
				}
			);

			search.addEventListener(
				'keydown',
				function ( e ) {
					if ( e.key === 'Escape' && search.value ) {
						e.preventDefault();
						e.stopPropagation();
						search.value = '';
						state.q = '';
						changed();
					}
				}
			);
		}

		if ( sortSelect ) {
			sortSelect.addEventListener( 'change', function () { state.sort = sortSelect.value; changed(); } );
		}

		if ( productSelect ) {
			productSelect.addEventListener( 'change', function () { state.product = productSelect.value; changed(); } );
		}

		chips.forEach(
			function ( chip ) {
				chip.addEventListener(
					'click',
					function () {
						var slug = chip.getAttribute( 'data-nr-tag' );
						var at = state.tags.indexOf( slug );

						if ( at === -1 ) {
							state.tags.push( slug );
						} else {
							state.tags.splice( at, 1 );
						}

						syncControls();
						changed();
					}
				);
			}
		);

		if ( tagsToggle && tagsPanel ) {
			tagsToggle.addEventListener(
				'click',
				function () {
					setTagsOpen( tagsPanel.hidden, false );
				}
			);

			// Escape closes it and gives focus back; a click or Tab elsewhere just closes it.
			tagsWrap.addEventListener(
				'keydown',
				function ( e ) {
					if ( e.key === 'Escape' && ! tagsPanel.hidden ) {
						e.stopPropagation();
						setTagsOpen( false, true );
					}
				}
			);
			tagsWrap.addEventListener(
				'focusout',
				function ( e ) {
					if ( ! tagsPanel.hidden && e.relatedTarget && ! tagsWrap.contains( e.relatedTarget ) ) {
						setTagsOpen( false, false );
					}
				}
			);
			document.addEventListener(
				'click',
				function ( e ) {
					if ( ! tagsPanel.hidden && ! tagsWrap.contains( e.target ) ) {
						setTagsOpen( false, false );
					}
				}
			);
		}

		if ( resetBtn ) {
			resetBtn.addEventListener(
				'click',
				function () {
					state = { q: '', sort: defaultSort, tags: [], product: '' };
					appliedSort = null;
					syncControls();
					changed();
					( search || sortSelect ).focus();
				}
			);
		}

		if ( toggle && toolbar ) {
			toggle.addEventListener(
				'click',
				function () {
					var open = ! toolbar.classList.contains( 'nr-controls-open' );

					toolbar.classList.toggle( 'nr-controls-open', open );
					toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				}
			);
		}

		// An editor's drag changes the manual order. Remember it, so switching sorts and back doesn't
		// rebuild the columns from where the cards were when the page loaded.
		$( el ).on(
			'sortstop',
			function () {
				var byNode = new Map();

				cards.forEach( function ( c ) { byNode.set( c.node, c ); } );
				slice.call( el.querySelectorAll( '.nr-kanban-item, .nr-view-item' ) ).forEach(
					function ( node, i ) {
						if ( byNode.has( node ) ) {
							byNode.get( node ).index = i;
						}
					}
				);
			}
		);

		// --- Start -----------------------------------------------------------------------------

		readUrl();
		syncControls();
		apply();

		// A link shared with filters set should show them without the reader hunting for the panel.
		if ( toggle && toolbar && ( state.tags.length || state.product || state.sort !== defaultSort ) ) {
			toolbar.classList.add( 'nr-controls-open' );
			toggle.setAttribute( 'aria-expanded', 'true' );
		}
	}

	// After the main script has set up drag and drop, so there is something to switch off.
	$(
		function () {
			$( '.nr-board[data-nr-toolbar]' ).each( function ( i, el ) { new Board( el, i ); } );
		}
	);
}( window.jQuery ) );
