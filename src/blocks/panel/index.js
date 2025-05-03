/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks, RichText } = wp.blockEditor;
	const { createElement, Fragment } = wp.element;

	registerBlockType('comet/panel', {
		attributes: {
			title: {
				type: 'string',
				default: ''
			},
			subtitle: {
				type: 'string',
				default: ''
			}
		},
		edit: ({ attributes, setAttributes, context }) => {
			const { title, subtitle } = attributes;
			const variant = context['comet/variant'];
			const blockProps = useBlockProps({
				className: `${variant}__panel`,
				open: true
			});

			// Define allowed blocks directly in the panel
			const allowedBlocks = [
				'core/group',
				'core/columns',
				'core/heading',
				'core/paragraph',
				'core/list',
				'core/image',
				'core/file'
			];

			// Set up a template with a paragraph block
			const template = [
				['core/paragraph', {
					'placeholder': 'Add panel content here...'
				}]
			];

			return createElement(
				'div',
				blockProps,
				createElement(
					Fragment,
					null,
					// Title section
					createElement(
						'div',
						{
							className: `${variant}__panel__title`
						},
						createElement(
							RichText,
							{
								tagName: 'span',
								className: `${variant}__panel__title__main`,
								value: title,
								onChange: (value) => setAttributes({ title: value }),
								placeholder: 'Add panel title here...'
							}
						),
						// Subtitle section
						createElement(
							RichText,
							{
								tagName: 'small',
								className: `${variant}__panel__title__subtitle`,
								value: subtitle,
								onChange: (value) => setAttributes({ subtitle: value }),
								placeholder: 'Add panel subtitle here...'
							}
						),
					),
					// Content section
					createElement(
						'div',
						{ className: `${variant}__panel__content` },
						createElement(
							InnerBlocks,
							{
								allowedBlocks: allowedBlocks,
								template: template,
								templateLock: false
							}
						)
					)
				)
			);
		},
		save: ({ attributes }) => {
			const { title, subtitle } = attributes;

			return createElement(
				'div',
				useBlockProps.save(),
				createElement(
					Fragment,
					null,
					// Title section
					createElement(
						RichText.Content,
						{
							tagName: 'span',
							className: 'panel-title',
							value: title
						}
					),
					// Subtitle section
					createElement(
						RichText.Content,
						{
							tagName: 'small',
							className: 'panel-subtitle',
							value: subtitle
						}
					),
					// Content section
					createElement(
						'div',
						{ className: 'panel__content' },
						createElement(InnerBlocks.Content)
					)
				)
			);
		}
	});
});
