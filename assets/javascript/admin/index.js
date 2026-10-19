// Screen Options checkbox for the streak panel. WordPress' utils script provides
// setUserSetting(), which saves the choice for the current user.
const toggle = document.getElementById( 'inkmeter-panel-toggle' );
const panel = document.getElementById( 'inkmeter-panel' );

if ( toggle && panel ) {
	toggle.addEventListener( 'change', () => {
		panel.hidden = ! toggle.checked;

		if ( typeof window.setUserSetting === 'function' ) {
			window.setUserSetting( 'inkmeter_panel', toggle.checked ? 'on' : 'off' );
		}
	} );
}

// Details opens the goal, the stats and the past year; the choice is kept per user.
const detailsToggle = document.getElementById( 'inkmeter-details-toggle' );
const details = document.getElementById( 'inkmeter-panel-details' );

if ( detailsToggle && details ) {
	detailsToggle.addEventListener( 'click', () => {
		const isOpen = 'true' !== detailsToggle.getAttribute( 'aria-expanded' );
		detailsToggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		details.hidden = ! isOpen;

		if ( typeof window.setUserSetting === 'function' ) {
			window.setUserSetting( 'inkmeter_details', isOpen ? 'open' : 'closed' );
		}
	} );
}

// The panel's Inkmeter Pro link can be dismissed for good, also per user.
const proLink = document.getElementById( 'inkmeter-pro-link' );
const dismiss = document.getElementById( 'inkmeter-pro-link-dismiss' );

if ( proLink && dismiss ) {
	dismiss.addEventListener( 'click', () => {
		proLink.hidden = true;

		if ( typeof window.setUserSetting === 'function' ) {
			window.setUserSetting( 'inkmeter_pro_link', 'off' );
		}
	} );
}
