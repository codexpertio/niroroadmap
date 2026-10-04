/**
 * Board / List / Timeline switcher, and the list's column sorting.
 *
 * Every view is already in the page, so switching fetches nothing. The chosen view is kept in the
 * URL (nr_view; a second board adds _2, and so on) so it can be shared, and in localStorage so a
 * visitor comes back to it. Boards without a switcher only use this to show the list on narrow
 * screens, if the site asks for that.
 */
( function ( $ ) {
	'use strict';

	var STORAGE_KEY = 'niroroadmap_view';
	var NARROW = '(max-width: 640px)';
	var slice = Array.prototype.slice;

	function remembered() {
		try {
			return window.localStorage.getItem( STORAGE_KEY ) || '';
		} catch ( e ) {
			return '';
		}
	}

	function remember( view ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, view );
		} catch ( e ) {}
	}

	function Board( el, index ) { // index: among boards that have views, for the URL.
		var suffix = index ? '_' + ( index + 1 ) : '';
		var panels = slice.call( el.querySelectorAll( '[data-nr-view]' ) );
		var buttons = slice.call( el.querySelectorAll( '[data-nr-view-btn]' ) );
		var switcher = el.querySelector( '[data-nr-switch]' );
		var defaultView = el.getAttribute( 'data-nr-default-view' );
		var mobileView = el.getAttribute( 'data-nr-mobile-view' );
		var hasSwitcher = el.hasAttribute( 'data-nr-switcher' );
		var current = defaultView;

		function offered( view ) {
			return panels.some( function ( p ) { return p.getAttribute( 'data-nr-view' ) === view; } );
		}

		// --- Views -----------------------------------------------------------------------------

		function show( view ) {
			current = view;

			panels.forEach(
				function ( panel ) {
					panel.hidden = panel.getAttribute( 'data-nr-view' ) !== view;
				}
			);
			buttons.forEach(
				function ( btn ) {
					btn.setAttribute( 'aria-pressed', btn.getAttribute( 'data-nr-view-btn' ) === view ? 'true' : 'false' );
				}
			);
		}

		function writeUrl() {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}

			var params = new URLSearchParams( window.location.search );

			if ( current === defaultView ) {
				params.delete( 'nr_view' + suffix );
			} else {
				params.set( 'nr_view' + suffix, current );
			}

			var query = params.toString();
			window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash );
		}

		function start() {
			var view = defaultView;

			if ( hasSwitcher ) {
				// Anything in the URL or storage is untrusted: only a view this board really has is used.
				var fromUrl = new URLSearchParams( window.location.search ).get( 'nr_view' + suffix );
				var saved = remembered();

				if ( fromUrl && offered( fromUrl ) ) {
					view = fromUrl;
				} else if ( saved && offered( saved ) ) {
					view = saved;
				} else if ( mobileView && offered( mobileView ) && window.matchMedia( NARROW ).matches ) {
					view = mobileView;
				}
			} else if ( mobileView && offered( mobileView ) && window.matchMedia( NARROW ).matches ) {
				view = mobileView;
			}

			show( view );

			if ( switcher ) {
				switcher.hidden = false;
			}
		}

		buttons.forEach(
			function ( btn ) {
				btn.addEventListener(
					'click',
					function () {
						var view = btn.getAttribute( 'data-nr-view-btn' );

						if ( view === current || ! offered( view ) ) {
							return;
						}

						show( view );
						remember( view );
						writeUrl();
					}
				);
			}
		);

		// --- List: sort by clicking a column heading -------------------------------------------

		var body = el.querySelector( '.nr-list-body' );
		var heads = slice.call( el.querySelectorAll( '.nr-list-table th[aria-sort]' ) );
		var sortState = null; // { by, dir } or null.

		if ( body ) {
			var rows = slice.call( body.children );

			rows.forEach( function ( row, i ) { row._nrOrder = i; } );

			var number = function ( row, selector ) {
				var node = row.querySelector( selector );
				var n = node ? parseInt( node.textContent, 10 ) : NaN;

				return isNaN( n ) ? null : n;
			};

			// The value a row sorts by. Null means "has none", which always goes last.
			var valueOf = {
				title: function ( row ) { return row.querySelector( '.nr-task-title' ).textContent.toLowerCase(); },
				status: function ( row ) { return parseInt( row.getAttribute( 'data-stage-id' ), 10 ) || 0; },
				votes: function ( row ) { return number( row, '.nr-task-votes-count' ); },
				comments: function ( row ) { return number( row, '.nr-task-comments-count' ); },
				target: function ( row ) { return row.getAttribute( 'data-target-sort' ) || null; }
			};

			var sortRows = function () {
				var get = valueOf[ sortState.by ];
				var sign = sortState.dir === 'asc' ? 1 : -1;

				rows.slice().sort(
					function ( a, b ) {
						var x = get( a );
						var y = get( b );

						if ( x === null || y === null ) {
							if ( x !== y ) {
								return x === null ? 1 : -1;
							}
						} else if ( x !== y ) {
							return ( x < y ? -1 : 1 ) * sign;
						}

						return a._nrOrder - b._nrOrder;
					}
				).forEach( function ( row ) { body.appendChild( row ); } );
			};

			var markHeads = function () {
				heads.forEach(
					function ( th ) {
						var btn = th.querySelector( '[data-nr-sort-by]' );
						var on = sortState && btn.getAttribute( 'data-nr-sort-by' ) === sortState.by;

						th.setAttribute( 'aria-sort', on ? ( sortState.dir === 'asc' ? 'ascending' : 'descending' ) : 'none' );
					}
				);
			};

			heads.forEach(
				function ( th ) {
					th.querySelector( '[data-nr-sort-by]' ).addEventListener(
						'click',
						function ( e ) {
							var by = e.currentTarget.getAttribute( 'data-nr-sort-by' );

							if ( sortState && sortState.by === by ) {
								sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
							} else {
								// Counts and dates read best biggest / latest first.
								sortState = { by: by, dir: by === 'votes' || by === 'comments' ? 'desc' : 'asc' };
							}

							sortRows();
							markHeads();
						}
					);
				}
			);

			// The toolbar re-sorts the rows on every change. A search keeps the column sort; picking a sort from its menu replaces it.
			el.addEventListener(
				'nr:sorted',
				function ( e ) {
					if ( ! sortState ) {
						return;
					}

					if ( e.detail && e.detail.changed ) {
						sortState = null;
						markHeads();
					} else {
						sortRows();
					}
				}
			);
		}

		// A board with a single view has nothing to switch; it only needs the list's sorting above.
		if ( defaultView ) {
			start();
		}
	}

	$(
		function () {
			var withViews = 0;

			$( '.nr-board[data-nr-views], .nr-board:has(.nr-list-table)' ).each(
				function ( i, el ) {
					new Board( el, el.hasAttribute( 'data-nr-views' ) ? withViews++ : 0 );
				}
			);
		}
	);
}( window.jQuery ) );
