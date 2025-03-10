/* global wp */

/**
 * Customisations for the block editor interface broadly
 * (not individual blocks)
 */
document.addEventListener('DOMContentLoaded', async function() {
	// Disable full-screen mode by default
	const isFullscreenMode = wp.data.select('core/edit-post').isFeatureActive('fullscreenMode');
	if (isFullscreenMode) {
		wp.data.dispatch('core/edit-post').toggleFeature('fullscreenMode');
	}

	// Open list view by default
	wp.domReady(() => {
		const { select, dispatch } = wp.data;
		const listViewIsOpen = select('core/editor').isListViewOpened();

		if (!listViewIsOpen) {
			dispatch('core/editor').setIsListViewOpened(true);
		}
	});

	// When block inspector is opened
	wp.data.subscribe(() => {
		const { select } = wp.data;
		const { getSelectedBlock } = select('core/editor');

		if (getSelectedBlock()) {
			relabelBlockOption('Stack on mobile', 'Stack when adapting to a narrow container or viewport');
			hideBlockOptionToggleByLabelText('Allow to wrap to multiple lines');

			// Gallery block
			hideBlockOptionToggleByLabelText('Randomize order');
			hideBlockOptionToggleByLabelText('Open images in new tab');

			// Flexible Table Block (plugin)
			const panelToggles = document.querySelectorAll('.components-panel__body-toggle');
			panelToggles.forEach((toggle) => {
				if (toggle.textContent === 'Cell settings') {
					toggle.addEventListener('click', function () {
						setTimeout(() => {
							hideBlockOptionFieldByLabelText('Cell font size');
							hideBlockOptionFieldByLabelText('Cell line height');
							hideBlockOptionFieldByLabelText('Cell text color');
							hideBlockOptionFieldByLabelText('Cell padding');
							hideBlockOptionFieldByLabelText('Cell border radius');
							hideBlockOptionFieldByLabelText('Cell border width');
							hideBlockOptionFieldByLabelText('Cell border style');
							hideBlockOptionFieldByLabelText('Cell border color');
						}, 100);
					});
				}
				if (toggle.textContent === 'Caption settings') {
					toggle.addEventListener('click', function () {
						setTimeout(() => {
							hideBlockOptionFieldByLabelText('Caption font size');
							hideBlockOptionFieldByLabelText('Caption line height');
							hideBlockOptionFieldByLabelText('Caption padding');
						}, 100);
					});
				}
			});
		}
	});
});

function hideBlockOptionToggleByLabelText(labelText) {
	const toggles = document.getElementsByClassName('components-toggle-control');
	Object.values(toggles).forEach((toggle) => {
		if (toggle.querySelector('.components-toggle-control__label').textContent.trim() === labelText) {
			toggle.style.display = 'none';
		}
	});
}

function hideBlockOptionFieldByLabelText(labelText) {
	const panels = document.querySelectorAll('.components-panel__body');
	panels.forEach((panel) => {
		const fields = panel.querySelectorAll('.components-input-control');
		fields.forEach((field) => {
			if (field?.querySelector('.components-input-control__label')?.innerText.trim().toLowerCase() === labelText.toLowerCase()) {
				field.style.display = 'none';
			}
		});
	});
}

function relabelBlockOption(labelText, newLabelText) {
	const toggles = document.getElementsByClassName('components-toggle-control');
	Object.values(toggles).forEach((toggle) => {
		if (toggle.querySelector('.components-toggle-control__label').textContent.trim() === labelText) {
			toggle.querySelector('.components-toggle-control__label').textContent = newLabelText;
		}
	});
}

