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

	// When list view is open and Accordion Panel block is expanded/collapsed, expand/collapse the actual panel
	wp.domReady(() => {
		const { select, dispatch } = wp.data;
		const listViewIsOpen = select('core/editor').isListViewOpened();

		if (listViewIsOpen) {
			const panelBlocks = select('core/editor').getBlocks().filter(block => block.name === 'comet/panel');
		}
	});


	// When block inspector is opened
	wp.data.subscribe(() => {
		const { select } = wp.data;
		const { getSelectedBlock } = select('core/editor');

		if (getSelectedBlock()) {
			relabelBlockOption('Stack on mobile', 'Stack when adapting to a narrow container or viewport');
			hideBlockOptionToggleByLabelText('Allow to wrap to multiple lines');
			hideBlockOptionFieldByLabelText('Inner blocks use content width');

			// Gallery block
			hideBlockOptionToggleByLabelText('Randomize order');
			hideBlockOptionToggleByLabelText('Open images in new tab');
		}
	});
});

wp.domReady(() => {
	wp.hooks.addFilter('blocks.registerBlockType', 'comet/fix-block-categories', function (settings, name) {
		if (name.startsWith('ninja-forms/')) {
			settings.category = 'forms';
			settings.parent = ['comet/container', 'comet/panel', 'core/group', 'core/column', 'comet/image-and-text'];
		}

		return settings;
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

