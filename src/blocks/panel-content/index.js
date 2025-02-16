/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/panel-content', {
		edit: ({ context }) => {
			const variant = context['comet/variant'];
			const blockProps = useBlockProps({
				className: `${variant}__panel__content`
			});
			const allowedBlocks = [
				'core/group',
				'core/columns',
				'core/heading',
				'core/paragraph',
				'core/list',
				'core/image',
				'core/file'
			];
			const template = [
				['core/paragraph', {
					'placeholder': 'Add panel content here...'
				}]
			];

			return createElement('div',
				blockProps,
				createElement(InnerBlocks, {
					allowedBlocks: allowedBlocks,
					template: template,
					templateLock: false
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
