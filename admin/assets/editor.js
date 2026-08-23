(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.element || !wp.components || !wp.data) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var el = wp.element.createElement;
	var ToggleControl = wp.components.ToggleControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var PluginDocumentSettingPanel =
		(wp.editor && wp.editor.PluginDocumentSettingPanel) ||
		(wp.editPost && wp.editPost.PluginDocumentSettingPanel);

	if (!registerPlugin || !PluginDocumentSettingPanel || !useSelect || !useDispatch) {
		return;
	}

	var config = typeof cacherocketEditor !== 'undefined' ? cacherocketEditor : {};
	var META_KEY = config.metaKey || '_cacherocket_do_not_cache';

	function DoNotCachePanel() {
		var meta = useSelect(function (select) {
			var editor = select('core/editor');
			return editor && editor.getEditedPostAttribute ? editor.getEditedPostAttribute('meta') || {} : {};
		}, []);
		var editPost = useDispatch('core/editor').editPost;
		var checked = !!meta[META_KEY];

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'cacherocket-do-not-cache',
				title: config.panelTitle || 'Cache Rocket',
				className: 'cacherocket-do-not-cache-panel'
			},
			el(ToggleControl, {
				label: config.toggleLabel || 'Do not cache this page',
				help: config.help || '',
				checked: checked,
				onChange: function (value) {
					var next = {};
					next[META_KEY] = !!value;
					editPost({ meta: next });
				}
			})
		);
	}

	registerPlugin('cacherocket-do-not-cache', {
		render: DoNotCachePanel,
		icon: 'performance'
	});
})(window.wp);
