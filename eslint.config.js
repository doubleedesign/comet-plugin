import baseConfig from '../../eslint.config.js';

export default [
	...baseConfig,
	{
		ignores: ['vendor/**'],
	},
	{
		files: ['./src/blocks/**/*.dist.js'],
		rules: {
			'@stylistic/object-curly-newline': ['error', {
				ObjectExpression: { multiline: true, minProperties: 4 }, // object literals
				ObjectPattern: 'never', // destructuring
				ImportDeclaration: { multiline: true, minProperties: 4 },
				ExportDeclaration: { multiline: true },
			}],
			'@stylistic/brace-style': [
				'warn',
				'stroustrup',
				{
					allowSingleLine: true
				}
			]
		}
	},
];
