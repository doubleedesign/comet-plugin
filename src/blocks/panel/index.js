/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/panel', {
		edit: ({ context }) => {
			const variant = context['comet/variant'];
			const blockProps = useBlockProps({
				className: `${variant}__panel`,
				open: true
			});
			const template = [
				['comet/panel-title', {
					'placeholder': 'Add panel title here...',
					'lock': { 'remove': true }
				}],
				['comet/panel-content', {
					'lock': { 'remove': true }
				}]
			];

			return createElement('div',
				blockProps,
				createElement(InnerBlocks, {
					allowedBlocks: ['comet/panel-title', 'comet/panel-content'],
					template: template,
					templateLock: 'insert'
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
