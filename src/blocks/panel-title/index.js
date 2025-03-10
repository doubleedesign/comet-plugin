/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { RichText, useBlockProps } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/panel-title', {
		edit: function({ context, attributes, setAttributes }) {
			const variant = context['comet/variant'] ?? 'accordion';
			const blockProps = useBlockProps({
				className: variant === 'tab' ? 'tabs__tab-list__item' : `${variant}__panel__title`,
			});

			const element = createElement(RichText, {
				...blockProps,
				tagName: variant === 'tab' ? 'li' : 'summary',
				value: attributes.content,
				onChange: (content) => setAttributes({ content }),
				placeholder: attributes.placeholder
			});

			if(variant === 'tab') {
				return createElement('ul', { className: 'tabs__tab-list' }, element);
			}

			return element;
		},
		save: function({ attributes }) {
			return createElement(RichText.Content, {
				tagName: 'span',
				value: attributes.content
			});
		}
	});
});
