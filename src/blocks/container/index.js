/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/container', {
		edit: () => {
			const blockProps = useBlockProps({
				className: 'container'
			});
			const template = [
				['core/paragraph']
			];

			return createElement('section',
				blockProps,
				createElement(InnerBlocks, {
					template: template
				})
			);
		},
		save: () => {
			return createElement('section',
				useBlockProps.save(),
				createElement(InnerBlocks.Content)
			);
		}
	});
});
