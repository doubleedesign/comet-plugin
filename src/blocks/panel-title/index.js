/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { RichText, useBlockProps } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/panel-title', {
		edit: function({ context, attributes, setAttributes }) {
			const variant = context['comet/variant'] ?? 'responsive-panels';

			const blockProps = useBlockProps({
				className: `${variant}__panel__title`,
			});

			return createElement(RichText, {
				...blockProps,
				tagName: 'span',
				value: attributes.content,
				onChange: (content) => setAttributes({ content }),
				placeholder: attributes.placeholder
			});
		},
		save: function({ attributes }) {
			return createElement(RichText.Content, {
				tagName: 'span',
				value: attributes.content
			});
		}
	});
});
