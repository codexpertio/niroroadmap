/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/**
 * Sidebar controls.
 */
import { PanelBody, SelectControl, ToggleControl, CheckboxControl } from '@wordpress/components';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
const FILTERS = [
	{ key: 'search', label: __( 'Search box', 'niroroadmap' ) },
	{ key: 'tag', label: __( 'Tags', 'niroroadmap' ) },
	{ key: 'product', label: __( 'Product', 'niroroadmap' ) },
];

export default function Edit( { attributes, setAttributes } ) {
	// '' follows the site setting; otherwise a comma list, or 'none'.
	const customFilters = '' !== attributes.filters;
	const activeFilters = 'none' === attributes.filters ? [] : attributes.filters.split( ',' ).filter( Boolean );

	const toggleFilter = ( key, on ) => {
		const next = FILTERS.map( ( f ) => f.key ).filter( ( k ) => ( k === key ? on : activeFilters.includes( k ) ) );
		setAttributes( { filters: next.length ? next.join( ',' ) : 'none' } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Views', 'niroroadmap' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Opens in', 'niroroadmap' ) }
						value={ attributes.view }
						options={ [
							{ label: __( 'Use the site setting', 'niroroadmap' ), value: '' },
							{ label: __( 'Board', 'niroroadmap' ), value: 'board' },
							{ label: __( 'List', 'niroroadmap' ), value: 'list' },
							{ label: __( 'Timeline', 'niroroadmap' ), value: 'timeline' },
						] }
						onChange={ ( view ) => setAttributes( { view } ) }
						help={ __( 'The timeline needs "Show an item\'s target date or quarter" in the settings.', 'niroroadmap' ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'View switcher', 'niroroadmap' ) }
						value={ attributes.switcher }
						options={ [
							{ label: __( 'Use the site setting', 'niroroadmap' ), value: '' },
							{ label: __( 'Show', 'niroroadmap' ), value: 'yes' },
							{ label: __( 'Hide', 'niroroadmap' ), value: 'no' },
						] }
						onChange={ ( switcher ) => setAttributes( { switcher } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Group the timeline', 'niroroadmap' ) }
						value={ attributes.group }
						options={ [
							{ label: __( 'Use the site setting', 'niroroadmap' ), value: '' },
							{ label: __( 'By quarter', 'niroroadmap' ), value: 'quarter' },
							{ label: __( 'By month', 'niroroadmap' ), value: 'month' },
							{ label: __( 'Now / Next / Later', 'niroroadmap' ), value: 'nownext' },
						] }
						onChange={ ( group ) => setAttributes( { group } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody title={ __( 'Search, sort and filter', 'niroroadmap' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Toolbar', 'niroroadmap' ) }
						value={ attributes.toolbar }
						options={ [
							{ label: __( 'Use the site setting', 'niroroadmap' ), value: '' },
							{ label: __( 'Show', 'niroroadmap' ), value: 'yes' },
							{ label: __( 'Hide', 'niroroadmap' ), value: 'no' },
						] }
						onChange={ ( toolbar ) => setAttributes( { toolbar } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Initial sort', 'niroroadmap' ) }
						value={ attributes.sort }
						options={ [
							{ label: __( 'Use the site setting', 'niroroadmap' ), value: '' },
							{ label: __( 'Manual order', 'niroroadmap' ), value: 'manual' },
							{ label: __( 'Most votes', 'niroroadmap' ), value: 'votes' },
							{ label: __( 'Newest', 'niroroadmap' ), value: 'newest' },
							{ label: __( 'Oldest', 'niroroadmap' ), value: 'oldest' },
							{ label: __( 'Most commented', 'niroroadmap' ), value: 'commented' },
						] }
						onChange={ ( sort ) => setAttributes( { sort } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Choose the filters for this board', 'niroroadmap' ) }
						checked={ customFilters }
						onChange={ ( on ) => setAttributes( { filters: on ? 'search,tag,product' : '' } ) }
						__nextHasNoMarginBottom
					/>
					{ customFilters &&
						FILTERS.map( ( f ) => (
							<CheckboxControl
								key={ f.key }
								label={ f.label }
								checked={ activeFilters.includes( f.key ) }
								onChange={ ( on ) => toggleFilter( f.key, on ) }
								__nextHasNoMarginBottom
							/>
						) ) }
				</PanelBody>
				<PanelBody title={ __( 'Suggestions', 'niroroadmap' ) }>
					<SelectControl
						label={ __( '"Suggest an idea" button', 'niroroadmap' ) }
						value={ attributes.submissions }
						options={ [
							{ label: __( 'Use the site setting', 'niroroadmap' ), value: '' },
							{ label: __( 'Show', 'niroroadmap' ), value: 'yes' },
							{ label: __( 'Hide', 'niroroadmap' ), value: 'no' },
						] }
						onChange={ ( submissions ) => setAttributes( { submissions } ) }
						help={ __( 'Visitors’ ideas stay pending until you publish them.', 'niroroadmap' ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<p { ...useBlockProps() }>
				{ __( 'Roadmap – choose a product in the settings if you want', 'niroroadmap' ) }
			</p>
		</>
	);
}
