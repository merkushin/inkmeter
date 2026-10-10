// The writing streak in the block editor: what publishing does for it in the
// pre-publish checks, a line in the Status panel, and a note after publishing,
// celebrating milestones. The server prints window.inkmeterEditor with the
// streak summary and the REST path that refreshes it.
import { panelTitle, prePublishMessage, statusLine, celebration } from './messages.js';

const { registerPlugin } = window.wp.plugins;
const { createElement: el } = window.wp.element;
const { createReduxStore, register, select, dispatch, subscribe, useSelect } = window.wp.data;
const apiFetch = window.wp.apiFetch;

// WordPress 6.6 moved these from wp.editPost to wp.editor.
const editor = window.wp.editor || {};
const editPost = window.wp.editPost || {};
const PrePublishPanel = editor.PluginPrePublishPanel || editPost.PluginPrePublishPanel;
const PostStatusInfo = editor.PluginPostStatusInfo || editPost.PluginPostStatusInfo;

const settings = window.inkmeterEditor;

if ( settings && PrePublishPanel && PostStatusInfo ) {
	const STORE = 'inkmeter/streak';
	register(
		createReduxStore( STORE, {
			reducer: ( state = settings.summary, action ) => ( 'SET_SUMMARY' === action.type ? action.summary : state ),
			actions: { setSummary: ( summary ) => ( { type: 'SET_SUMMARY', summary } ) },
			selectors: { getSummary: ( state ) => state },
		} )
	);

	const Streak = () => {
		const summary = useSelect( ( s ) => s( STORE ).getSummary(), [] );
		const isScheduled = useSelect( ( s ) => s( 'core/editor' ).isEditedPostBeingScheduled(), [] );

		return [
			el(
				PrePublishPanel,
				{ key: 'pre-publish', title: panelTitle(), initialOpen: true },
				el( 'p', null, prePublishMessage( summary, isScheduled ) )
			),
			el(
				PostStatusInfo,
				{ key: 'status' },
				el( 'span', { className: 'inkmeter-editor-status' }, '🔥 ' + statusLine( summary ) )
			),
		];
	};

	registerPlugin( 'inkmeter-streak', { render: Streak } );

	// When a save publishes the post, fetch the new streak and say what changed.
	// Watching saves, not the published flag, keeps an already published post
	// from celebrating as it loads.
	let saving = false;
	let publishedBeforeSave = false;
	subscribe( () => {
		const ed = select( 'core/editor' );
		const isSaving = ed.isSavingPost() && ! ed.isAutosavingPost();
		if ( isSaving && ! saving ) {
			publishedBeforeSave = ed.isCurrentPostPublished();
		}
		const justPublished = saving && ! isSaving && ! publishedBeforeSave && ed.didPostSaveRequestSucceed() && ed.isCurrentPostPublished();
		saving = isSaving;
		if ( ! justPublished ) {
			return;
		}

		const before = select( STORE ).getSummary();
		apiFetch( { path: settings.path } )
			.then( ( after ) => {
				dispatch( STORE ).setSummary( after );
				const note = celebration( before, after );
				if ( note ) {
					dispatch( 'core/notices' ).createNotice( 'success', ( note.milestone ? '🎉 ' : '🔥 ' ) + note.message, {
						id: 'inkmeter-streak',
						type: 'snackbar',
					} );
				}
			} )
			.catch( () => {} );
	}, 'core/editor' );
}
