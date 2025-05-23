<?php
acf_add_local_field_group(array(
	'key'                   => 'group_67be796e8db9c',
	'title'                 => 'Link Group Block',
	'fields'                => array(
		array(
			'key'               => 'field_67be796fd2aad',
			'label'             => 'Links',
			'name'              => 'links',
			'aria-label'        => '',
			'type'              => 'repeater',
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
			'layout'            => 'table',
			'pagination'        => 0,
			'min'               => 0,
			'max'               => 0,
			'collapsed'         => '',
			'button_label'      => 'Add link',
			'rows_per_page'     => 20,
			'sub_fields'        => array(
				array(
					'key'               => 'field_67be7984d2aae',
					'label'             => 'Link',
					'name'              => 'link',
					'aria-label'        => '',
					'type'              => 'link',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'return_format'     => 'array',
					'allow_in_bindings' => 0,
					'parent_repeater'   => 'field_67be796fd2aad',
				),
			),
		),
	),
	'location'              => array(
		array(
			array(
				'param'    => 'block',
				'operator' => '==',
				'value'    => 'comet/link-group',
			),
		),
	),
	'menu_order'            => 0,
	'position'              => 'normal',
	'style'                 => 'default',
	'label_placement'       => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen'        => '',
	'active'                => true,
	'description'           => '',
	'show_in_rest'          => 0,
));
