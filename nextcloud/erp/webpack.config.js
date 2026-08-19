const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	'erp-main': { import: './src/main.js', filename: 'erp-main.js' },
}

module.exports = webpackConfig
