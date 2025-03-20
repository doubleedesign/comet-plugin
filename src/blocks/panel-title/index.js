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

			if(variant === 'accordion') {
				return createElement(RichText, {
					...blockProps,
					tagName: 'summary',
					value: attributes.content,
					onChange: (content) => setAttributes({ content }),
					onClick: (event) => {
						event.preventDefault();
						const content = event.target.closest('.accordion__panel').querySelector('.accordion__panel__content');
						content.classList.toggle('show');
					},
					placeholder: attributes.placeholder
				});
			}

			if(variant === 'tab') {
				return createElement('ul', { className: 'tabs__tab-list' }, createElement(RichText, {
					...blockProps,
					tagName: 'span',
					value: attributes.content,
					onChange: (content) => setAttributes({ content }),
					placeholder: attributes.placeholder
				}));
			}
		},
		save: function({ attributes }) {
			return createElement(RichText.Content, {
				tagName: 'span',
				value: attributes.content
			});
		}
	});
});
