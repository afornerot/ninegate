//== MODAL LOADER =============================================================
function ModalLoad(idmodal, title, path) {
	$("#" + idmodal + " .modal-header h4").text(title);
	$("#" + idmodal + " #framemodal").attr("src", path);
}

//== SELECT2 INITIALIZATION ===================================================
$(document).ready(function () {
	$(document).on('select2:open', () => {
		setTimeout(() => {
			let input = document.querySelector('.select2-container--open .select2-search__field');
			if (input) input.focus();
		}, 0);
	});
});

$(document).ready(function () {
	$('.select2').select2({
		theme: 'bootstrap-5',
		templateResult: function (data) {
			if (!data.id) return data.text;

			const $result = $('<span>').text(data.text);

			const customClass = $(data.element).attr('class');
			if (customClass) {
				$result.addClass(customClass);
			}

			return $result;
		},
		templateSelection: function (data) {
			if (!data.id) return data.text;

			const $selection = $('<span>').text(data.text);

			const customClass = $(data.element).attr('class');
			if (customClass) {
				$selection.addClass(customClass);
			}

			return $selection;
		}
	});
});

//== TOOLTIP INITIALIZATION ===================================================
$(function () {
	$('[data-bs-toggle="tooltip"]').tooltip();
});

//== REQUIRED FIELD INDICATOR =================================================

// Add asterisk to labels of required fields
$(document).ready(function () {
	$('input[required], select[required], textarea[required]').each(function () {
		var $input = $(this);
		var id = $input.attr('id');
		if (id) {
			var $label = $('label[for="' + id + '"]');
			if ($label.length && $label.text().indexOf('*') === -1) {
				$label.text($label.text() + ' *');
			}
		}
	});
});

// == COLOR PICKER =============================================================
$(document).ready(function () {
	$('.color-input').each(function () {
		var $input = $(this);
		var $wrapper = $('<span class="color-wrapper"></span>');
		$input.wrap($wrapper);

		var $preview = $('<span class="color-preview"></span>');
		$input.before($preview);

		var $picker = $('<input type="color">');
		$input.before($picker);

		function updatePreview() {
			var val = $input.val();
			if (val && /^#[0-9A-Fa-f]{6}$/.test(val)) {
				$preview.css('background-color', val);
				$preview.css('background-image', 'none');
				$picker.val(val);
			} else {
				$preview.css('background-color', 'transparent');
				$preview.css('background-image', 'repeating-linear-gradient(45deg, #ccc, #ccc 5px, #fff 5px, #fff 10px)');
				$picker.val('#000000');
			}
		}

		$preview.on('click', function () {
			$picker[0].click();
		});

		$picker.on('input', function () {
			$input.val(this.value);
			$preview.css('background-color', this.value);
			$preview.css('background-image', 'none');
		});

		$input.on('input', updatePreview);
		updatePreview();
	});
});

//== FONT SELECT PREVIEW ======================================================
$(document).ready(function () {
	// Font select preview
	$('.font-select').each(function () {
		var $select = $(this);
		var currentValue = $select.val() || 'Roboto';

		// Create preview element with longer text and accented characters
		var $preview = $('<span class="font-preview ms-2" style="font-size: 14px; vertical-align: middle;">Bonjour été français</span>');
		$preview.css('font-family', "'" + currentValue + "', sans-serif");

		$select.after($preview);

		$select.on('change', function () {
			var font = $(this).val();
			$preview.css('font-family', "'" + font + "', sans-serif");
		});
	});
});

//== ICON/LOGO INPUT INITIALIZATION ===========================================
$(document).ready(function () {
	// Icon/Logo input initialization
	$('.icon-input').each(function () {
		var $input = $(this);
		var $id = $input.attr('id') || 'id';
		var value = $input.val() || $input.data('icon-empty-preview') || '';
		var endpoint = $input.data('icon-endpoint') || 'icon';
		var label = $input.data('icon-label') || 'Icon';
		var uploadUrl = $input.data('upload-url') || '/user/upload/crop01/' + endpoint + '?reportThumb=' + endpoint;

		// Check if already initialized
		if ($input.parent().hasClass('icon-wrapper')) {
			return;
		}

		// Create wrapper
		var $wrapper = $('<div class="text-center d-flex flex-column align-items-center mb-3 icon-wrapper"></div>');
		$input.wrap($wrapper);

		// Create preview image
		var $preview = $('<img id="' + $id + '_img" class="bigavatar mb-2" style="background-color: var(--bs-dark);">');
		$preview.attr('src', value ? '/' + value : '');
		if (!value) {
			$preview.css('display', 'none');
		}
		$input.parent().prepend($preview);

		// Create button
		var $btn = $('<a class="btn btn-info" style="max-width:100%; margin-bottom:15px;" data-bs-toggle="modal" data-bs-target="#mymodal"></a>');
		$btn.attr('onclick', "ModalLoad('mymodal','" + label + "','" + uploadUrl + "');");
		$btn.attr('title', 'Ajouter ' + label);
		$btn.text('Modifier');
		$input.parent().append($btn);

		// Listen for changes
		$input.on('change', function () {
			var val = $(this).val();
			if (val) {
				$preview.attr('src', '/' + val).show();
			} else {
				$preview.hide();
			}
		});
	});
});

//== EASYMDE MARKDOWN EDITOR ===================================================
$(document).ready(function () {
	$('.easymde-textarea').each(function (index) {
		if ($(this).closest('.modal, .note-widget-edit').length) return;
		var height = this.dataset.markdownHeight;
		new EasyMDE({
			element: this,
			spellChecker: false,
			status: false,
			minHeight: height ? height + 'px' : undefined,
			autosave: { enabled: false, uniqueId: 'mdEditor_' + index, delay: 1000 },
		});
	});
});

//== NOTE WIDGET EDIT ==========================================================
$(document).ready(function () {
	$(document).on('click', '.note-edit-btn', function () {
		var pageWidgetId = $(this).data('page-widget-id');
		var modal = new bootstrap.Modal(document.getElementById('noteModal-' + pageWidgetId));
		modal.show();
	});

	$(document).on('shown.bs.modal', '.modal[id^="noteModal-"]', function () {
		var $modal = $(this);
		var $textarea = $modal.find('.easymde-textarea');
		var $loading = $modal.find('.note-mde-loading');
		if (!$textarea.data('mde')) {
			var mde = new EasyMDE({
				element: $textarea[0],
				spellChecker: false,
				status: false,
				autosave: { enabled: false, uniqueId: 'note_' + $textarea.attr('id'), delay: 1000 },
			});
			$textarea.data('mde', mde);
			$loading.addClass('d-none');
			$textarea.removeClass('d-none');
		}
	});

	$(document).on('click', '.note-save-btn', function () {
		var $btn = $(this);
		var pageWidgetId = $btn.data('page-widget-id');
		var saveUrl = $btn.data('save-url');
		var $modal = $('#noteModal-' + pageWidgetId);
		var $textarea = $modal.find('.easymde-textarea');
		var mde = $textarea.data('mde');
		var content = mde ? mde.value() : $textarea.val();

		$.ajax({
			url: saveUrl,
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ content: content }),
			success: function (response) {
				if (response.success) {
					var modalInstance = bootstrap.Modal.getInstance($modal[0]);
					modalInstance.hide();

					$.get(window.location.href, function (html) {
						var $page = $(html);
						var $newWidget = $page.find('[data-id="' + pageWidgetId + '"]');
						if ($newWidget.length) {
							$('[data-id="' + pageWidgetId + '"]').replaceWith($newWidget);
						}
					});
				}
			}
		});
	});
});

//== CAROUSEL WIDGET ===========================================================
$(document).ready(function () {
	// After upload modal closes, auto-save new images as slides then reload
	$(document).on('hidden.bs.modal', '.modal[id^="mymodalupload-pagewidgetfile-"]', function () {
		var $modal = $(this);
		var domain = $modal.data('domain');
		var id = $modal.data('id');
		var $widget = $('#carousel-widget-' + id);
		if (!$widget.length) return;

		var saveUrl = $widget.data('save-url');
		var listFilesUrl = $widget.data('list-files-url');
		var listId = $widget.data('slides-list-id');

		$.get(listFilesUrl, function (html) {
			var $html = $(html);
			var files = [];
			$html.find('a[href*="download"]').each(function () {
				var href = $(this).attr('href');
				var match = href.match(/path=([^&]+)/);
				if (match) files.push(decodeURIComponent(match[1]));
			});
			if (files.length === 0) return;

			var existingImages = [];
			$('#' + listId + ' .carousel-slide-card').each(function () {
				var imgSrc = $(this).find('img').attr('src');
				var pathMatch = imgSrc.match(/path=([^&]+)/);
				if (pathMatch) existingImages.push(decodeURIComponent(pathMatch[1]));
			});

			var slides = getCarouselSlides(listId);
			files.forEach(function (file) {
				if (existingImages.indexOf(file) === -1) {
					slides.push({ image: file, title: '', description: '', link: '', linkTarget: '_blank' });
				}
			});

			saveCarouselSlides(saveUrl, slides);
		});
	});

	// Delete slide from edit modal
	$(document).on('click', '.carousel-slide-delete', function () {
		var $card = $(this).closest('.carousel-slide-card');
		var listId = $card.closest('[id^="carousel-slides-list-"]').attr('id');
		var noSlidesId = listId.replace('carousel-slides-list-', 'carousel-no-slides-');
		$card.fadeOut(200, function () {
			$(this).remove();
			var hasSlides = $('#' + listId + ' .carousel-slide-card').length > 0;
			$('#' + noSlidesId).toggle(!hasSlides);
		});
	});

	// Save slides from edit modal
	$(document).on('click', '.carousel-save-btn', function () {
		var $btn = $(this);
		var saveUrl = $btn.data('save-url');
		var listId = $btn.data('slides-list-id');
		var modalId = $btn.data('modal-id');

		var slides = getCarouselSlides(listId);
		$.ajax({
			url: saveUrl,
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ slides: slides }),
			success: function (response) {
				if (response.success) {
					$('#' + modalId).modal('hide');
					location.reload();
				}
			},
			error: function () {
				alert('Erreur lors de la sauvegarde');
			}
		});
	});

	function getCarouselSlides(listId) {
		var slides = [];
		$('#' + listId + ' .carousel-slide-card').each(function () {
			var $card = $(this);
			var imgSrc = $card.find('img').attr('src');
			var pathMatch = imgSrc.match(/path=([^&]+)/);
			if (pathMatch) {
				slides.push({
					image: decodeURIComponent(pathMatch[1]),
					title: $card.find('.slide-title').val() || '',
					description: $card.find('.slide-description').val() || '',
					link: $card.find('.slide-link').val() || '',
					linkTarget: $card.find('.slide-link-target').is(':checked') ? '_blank' : '_self'
				});
			}
		});
		return slides;
	}

	function saveCarouselSlides(url, slides) {
		$.ajax({
			url: url,
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ slides: slides }),
			success: function (response) {
				if (response.success) {
					location.reload();
				}
			}
		});
	}
});

//== ICON ENTITY PICKER ========================================================
$(document).ready(function () {
	// Initialize icon entity pickers
	$('.icon-entity-wrapper').each(function () {
		var $wrapper = $(this);
		var inputId = $wrapper.data('input-id');
		var $input = $('#' + inputId);
		var $preview = $wrapper.find('.icon-entity-preview');
		var $name = $wrapper.find('.icon-entity-name');
		var $clearBtn = $wrapper.find('.icon-entity-clear');

		// Update preview from current value
		function updatePreview() {
			var iconId = $input.val();
			if (iconId) {
				var $item = $('#iconGrid .icon-picker-item[data-id="' + iconId + '"]');
				if ($item.length) {
					var route = $item.data('route');
					var tags = $item.data('tags');
					$preview.attr('src', '/' + route).show();
					$name.text(tags || route).removeClass('text-muted');
					$clearBtn.show();
					return;
				}
			}
			$preview.hide().attr('src', '');
			$name.text('Aucune icône sélectionnée').addClass('text-muted');
			$clearBtn.hide();
		}

		updatePreview();

		// Clear button
		$clearBtn.on('click', function () {
			$input.val('').trigger('change');
			updatePreview();
		});

		// Listen for changes
		$input.on('change', updatePreview);
	});

	// Icon picker modal - icon click
	$(document).on('click', '.icon-picker-item', function () {
		var $item = $(this);
		var iconId = $item.data('id');
		var route = $item.data('route');
		var tags = $item.data('tags');

		// Find the active input (the one that opened the modal)
		var $activeWrapper = $('.icon-entity-wrapper').filter(function () {
			return $(this).find('.icon-entity-btn').is(':visible');
		});
		if (!$activeWrapper.length) {
			// Fallback: use the first wrapper
			$activeWrapper = $('.icon-entity-wrapper').first();
		}

		var inputId = $activeWrapper.data('input-id');
		var $input = $('#' + inputId);
		var $preview = $activeWrapper.find('.icon-entity-preview');
		var $name = $activeWrapper.find('.icon-entity-name');
		var $clearBtn = $activeWrapper.find('.icon-entity-clear');

		$input.val(iconId).trigger('change');
		$preview.attr('src', '/' + route).show();
		$name.text(tags || route).removeClass('text-muted');
		$clearBtn.show();

		// Close modal
		var modal = bootstrap.Modal.getInstance(document.getElementById('iconPickerModal'));
		if (modal) modal.hide();
	});

	// Icon search filter
	$('#iconSearchInput').on('input', function () {
		var search = $(this).val().toLowerCase();
		$('#iconGrid .icon-picker-item').each(function () {
			var $item = $(this);
			var tags = ($item.data('tags') || '').toString().toLowerCase();
			var route = ($item.data('route') || '').toString().toLowerCase();
			if (!search || tags.indexOf(search) !== -1 || route.indexOf(search) !== -1) {
				$item.show();
			} else {
				$item.hide();
			}
		});
	});

	// Clear search when modal closes
	$('#iconPickerModal').on('hidden.bs.modal', function () {
		$('#iconSearchInput').val('');
		$('#iconGrid .icon-picker-item').show();
	});
});

//== WIDGET SELECT GRID ========================================================
$(document).ready(function () {
	$(document).on('click', '.widget-select-card', function () {
		var $card = $(this);
		var value = $card.data('value');
		var $grid = $card.closest('#widget-select-grid');
		var $input = $grid.prev('input[type="hidden"]');

		$grid.find('.widget-select-card').removeClass('selected');
		$card.addClass('selected');
		$input.val(value);
	});
});
//== BUREAU WIDGET =============================================================
$(document).ready(function () {
	// Category filter - single select
	$(document).on('click', '.category-filter', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $nav = $btn.closest('.itemlink-category-nav');
		var catId = $btn.data('cat');

		$nav.find('.category-filter, .category-all').removeClass('active');
		$btn.addClass('active');

		var $widget = $nav.parent();
		$widget.find('.itemlink-category-section').each(function () {
			var $section = $(this);
			if ($section.data('cat-section') == catId) {
				$section.show();
			} else {
				$section.hide();
			}
		});
	});

	// "Toutes" button
	$(document).on('click', '.category-all', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $nav = $btn.closest('.itemlink-category-nav');

		$nav.find('.category-filter, .category-all').removeClass('active');
		$btn.addClass('active');

		var $widget = $nav.parent();
		$widget.find('.itemlink-category-section').show();
		$widget.find('.itemlink-category-title').show();
	});

	// Search filter
	$(document).on('input', '.bureau-search', function () {
		var search = $(this).val().toLowerCase().trim();
		var $widget = $(this).parent();
		var $sections = $widget.find('.itemlink-category-section');

		if (search) {
			$sections.find('.itemlink-category-title').hide();
		} else {
			$sections.find('.itemlink-category-title').show();
		}

		$sections.find('.itemlink-item').each(function () {
			var $item = $(this);
			var title = ($item.data('title') || '').toString();
			if (!search || title.indexOf(search) !== -1) {
				$item.show();
			} else {
				$item.hide();
			}
		});
	});

	// Size toggle
	var sizeLabels = {
		'small': 'fa-th',
		'medium': 'fa-th-large',
		'large': 'fa-th-large',
		'list': 'fa-list'
	};
	var sizeOrder = ['small', 'medium', 'large', 'list'];

	$('.bureau-size-toggle').each(function () {
		var $btn = $(this);
		var bureauId = $btn.data('bureau-id');
		var saved = localStorage.getItem('bureau-size-' + bureauId);
		if (saved && sizeLabels[saved]) {
			applySize($btn, saved);
		}
	});

	$(document).on('click', '.bureau-size-toggle', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var current = $btn.data('current');
		var nextIdx = (sizeOrder.indexOf(current) + 1) % sizeOrder.length;
		var next = sizeOrder[nextIdx];

		applySize($btn, next);

		var bureauId = $btn.data('bureau-id');
		localStorage.setItem('bureau-size-' + bureauId, next);
	});

	function applySize($btn, size) {
		var $widget = $btn.closest('.page-widget-body');

		$btn.data('current', size);
		var icon = sizeLabels[size];
		$btn.html('<i class="fas ' + icon + '"></i>');

		$widget.find('.itemlink-items').each(function () {
			var $grid = $(this);
			$grid.removeClass('itemlink-small itemlink-medium itemlink-large itemlink-list');
			$grid.addClass('itemlink-' + size);
		});
	}

	// Favorite toggle - simple page reload
	$(document).on('click', '.itemlink-fav-btn', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $btn = $(this);
		var itemId = $btn.data('item-id');

		$.ajax({
			url: '/user/widget/bureau/favorite/' + itemId,
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({}),
			success: function () {
				location.reload();
			}
		});
	});
});

//== LINK WIDGET ==============================================================
$(document).ready(function () {
	// Add link to edit modal
	$(document).on('click', '[id^="addLinkBtn-"]', function () {
		var btnId = $(this).attr('id');
		var listId = btnId.replace('addLinkBtn-', 'link-list-');
		var widgetId = listId.replace('link-list-', '');
		var index = $('#' + listId + ' .link-card').length;

		var cardHtml = '<div class="card mb-2 link-card">' +
			'<div class="card-body p-2">' +
			'<div class="row g-2 align-items-start">' +
				// Col 1: Icon
				'<div class="col-md-1 text-center">' +
					'<img class="link-icon-preview" src="" style="width: 48px; height: 48px; object-fit: contain; display: none;">' +
					'<input type="hidden" class="link-icon-id" value="">' +
					'<div class="d-flex gap-1 justify-content-center mt-1">' +
						'<button type="button" class="btn btn-sm btn-outline-primary link-icon-btn" title="Choisir une icône">' +
							'<i class="fas fa-icons"></i>' +
						'</button>' +
						'<button type="button" class="btn btn-sm btn-outline-danger link-icon-clear" title="Retirer" style="display: none;">' +
							'<i class="fas fa-times"></i>' +
						'</button>' +
					'</div>' +
				'</div>' +
				// Col 2: Title / URL / Summary / Colors / Flag
				'<div class="col-md-4">' +
					'<input type="text" class="form-control form-control-sm link-title mb-2" placeholder="Titre *" value="">' +
					'<input type="url" class="form-control form-control-sm link-url mb-2" placeholder="URL *" value="">' +
					'<input type="text" class="form-control form-control-sm link-summary mb-2" placeholder="Résumé" value="">' +
					'<div class="row g-2">' +
						'<div class="col-6">' +
							'<label class="form-label small">Fond</label>' +
							'<input type="text" class="form-control form-control-sm color-input link-bgcolor" value="">' +
						'</div>' +
						'<div class="col-6">' +
							'<label class="form-label small">Texte</label>' +
							'<input type="text" class="form-control form-control-sm color-input link-color" value="">' +
						'</div>' +
					'</div>' +
					'<div class="form-check mt-2">' +
						'<input type="checkbox" class="form-check-input link-new-tab" checked>' +
						'<label class="form-check-label small">Ouvrir dans un nouvel onglet</label>' +
					'</div>' +
				'</div>' +
				// Col 3: Description
				'<div class="col-md-7">' +
					'<textarea class="form-control form-control-sm link-description" rows="5" placeholder="Description"></textarea>' +
				'</div>' +
			'</div>' +
			'<div class="d-flex justify-content-end mt-2">' +
				'<button class="btn btn-sm btn-outline-danger link-delete" title="Supprimer">' +
					'<i class="fas fa-trash"></i> Supprimer' +
				'</button>' +
			'</div>' +
			'</div>' +
		'</div>';

		$('#' + listId).append(cardHtml);
		$('#' + listId.replace('link-list-', 'link-no-links-')).hide();

		// Init color preview on new inputs
		$('.color-input').each(function() {
			var $input = $(this);
			if ($input.parent().hasClass('color-wrapper')) return;
			var $wrapper = $('<span class="color-wrapper"></span>');
			$input.wrap($wrapper);
			var $preview = $('<span class="color-preview"></span>');
			$input.before($preview);
			var $picker = $('<input type="color">');
			$input.before($picker);
			$picker.on('input', function() { $input.val(this.value); $preview.css('background-color', this.value).css('background-image', 'none'); });
			$input.on('input', function() { var v = $input.val(); if (v && /^#[0-9A-Fa-f]{6}$/.test(v)) { $preview.css('background-color', v).css('background-image', 'none'); $picker.val(v); } });
			$preview.on('click', function() { $picker[0].click(); });
		});
	});

	// Delete link
	$(document).on('click', '.link-delete', function () {
		$(this).closest('.link-card').fadeOut(200, function () {
			$(this).remove();
			var listId = $(this).closest('[id^="link-list-"]').attr('id');
			if ($('#' + listId + ' .link-card').length === 0) {
				$('#' + listId.replace('link-list-', 'link-no-links-')).show();
			}
		});
	});

	// Track which link card is being edited for icon
	var $currentIconTarget = null;

	// Icon picker button - mark as active, store target, and open modal
	$(document).on('click', '.link-icon-btn', function () {
		$currentIconTarget = $(this).closest('.link-card');
		var el = document.getElementById('iconPickerModalLink');
		if (el) {
			var modal = bootstrap.Modal.getOrCreateInstance(el);
			modal.show();
		}
	});

	// Icon picker - click on icon item
	$(document).on('click', '.icon-picker-link-item', function () {
		var $item = $(this);
		var iconId = $item.data('id');
		var route = $item.data('route');

		if ($currentIconTarget) {
			$currentIconTarget.find('.link-icon-id').val(iconId);
			$currentIconTarget.find('.link-icon-preview').attr('src', '/' + route).show();
			$currentIconTarget.find('.link-icon-clear').show();
		}

		// Close icon picker modal
		var el = document.getElementById('iconPickerModalLink');
		var modal = bootstrap.Modal.getOrCreateInstance(el);
		modal.hide();
	});

	// Icon search filter
	$(document).on('input', '#iconSearchInputLink', function () {
		var search = $(this).val().toLowerCase().trim();
		$('#iconGridLink .icon-picker-link-item').each(function () {
			var $item = $(this);
			var tags = ($item.data('tags') || '').toString().toLowerCase();
			if (!search || tags.indexOf(search) !== -1) {
				$item.show();
			} else {
				$item.hide();
			}
		});
	});

	// Icon clear button
	$(document).on('click', '.link-icon-clear', function () {
		$(this).siblings('.link-icon-id').val('');
		$(this).siblings('.link-icon-preview').attr('src', '').hide();
		$(this).hide();
	});

	// Save links
	$(document).on('click', '.link-save-btn', function () {
		var $btn = $(this);
		var saveUrl = $btn.data('save-url');

		var links = [];
		var hasError = false;

		$('.link-card').each(function (index) {
			var $card = $(this);
			var title = $card.find('.link-title').val() || '';
			var url = $card.find('.link-url').val() || '';
			var iconId = $card.find('.link-icon-id').val() || '';

			// Validate required fields
			if (!title.trim()) {
				$card.find('.link-title').addClass('is-invalid');
				hasError = true;
			} else {
				$card.find('.link-title').removeClass('is-invalid');
			}
			if (!url.trim()) {
				$card.find('.link-url').addClass('is-invalid');
				hasError = true;
			} else {
				$card.find('.link-url').removeClass('is-invalid');
			}
			if (!iconId) {
				$card.find('.link-icon-btn').addClass('btn-danger');
				hasError = true;
			} else {
				$card.find('.link-icon-btn').removeClass('btn-danger');
			}

			links.push({
				title: title,
				url: url,
				summary: $card.find('.link-summary').val() || '',
				description: $card.find('.link-description').val() || '',
				iconId: iconId || null,
				bgcolor: $card.find('.link-bgcolor').val() || '',
				color: $card.find('.link-color').val() || '',
				newTab: $card.find('.link-new-tab').is(':checked')
			});
		});

		if (hasError) {
			showToast('Veuillez remplir titre, URL et icône pour chaque lien', 'danger');
			return;
		}

		$.ajax({
			url: saveUrl,
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ links: links }),
			success: function (response) {
				if (response.success) {
					location.reload();
				}
			}
		});
	});
});
