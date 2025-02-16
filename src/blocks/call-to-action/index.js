/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/call-to-action', {
		edit: ({ attributes }) => {
			const blockProps = useBlockProps({
				className: 'call-to-action'
			});
			const template = [
				['core/heading', {
					'lock': { 'remove': true }
				}],
				['core/paragraph', {
					'lock': { 'remove': true }
				}],
				['core/buttons', {
					'lock': { 'remove': true },
					'placeholder': 'Add buttons',
					'allowedBlocks': ['core/button']
				}]
			];

			return createElement('div',
				blockProps,
				createElement(InnerBlocks, {
					allowedBlocks: ['core/heading', 'comet/paragraph', 'core/buttons'],
					template: template
				})
			);
		},
		save: () => {
			return createElement('div',
				useBlockProps.save(),
				createElement(InnerBlocks.Content)
			);
		}
	});
});
