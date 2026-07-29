(function () {
	'use strict';

	document.querySelectorAll('.cr-field--toggle .cr-switch input[type="checkbox"]').forEach(function (input) {
		input.addEventListener('change', function () {
			var field = input.closest('.cr-field');
			if (!field) return;
			field.classList.toggle('is-on', input.checked);
		});
	});

	var selectAll = document.getElementById('cr-db-select-all');
	if (selectAll) {
		selectAll.addEventListener('change', function () {
			document.querySelectorAll('input[name="cacherocket_db_actions[]"]').forEach(function (box) {
				box.checked = selectAll.checked;
			});
		});
	}
})();
