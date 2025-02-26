/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/container', {
		edit: () => {
			const blockProps = useBlockProps({
				className: 'section'
			});
			const template = [
				['core/paragraph']
			];

			return createElement('section',
				blockProps,
				createElement(
					'div',
					{ className: 'container' },
					createElement(InnerBlocks, {
						template: template
					})
				),
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
