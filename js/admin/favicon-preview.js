(function (Drupal, once) {
  'use strict';

  function clampPadding(value) {
    const numeric = Number.parseInt(value, 10);
    if (Number.isNaN(numeric)) {
      return 0;
    }
    return Math.max(0, Math.min(40, numeric));
  }

  function inputValue(form, name, fallback = '') {
    const input = form.querySelector(`[name="${name}"]`);
    if (!input) {
      return fallback;
    }
    if (input.type === 'checkbox') {
      return input.checked;
    }
    return input.value || fallback;
  }

  function applyBackground(form, selector, colorName) {
    const color = inputValue(form, colorName, '#ffffff');
    form.querySelectorAll(selector).forEach((canvas) => {
      canvas.style.setProperty('--preview-background', color);
    });
  }

  function applyPadding(form, selector, padding) {
    form.querySelectorAll(selector).forEach((canvas) => {
      canvas.style.setProperty('--preview-padding', `${padding}%`);
    });
  }

  function syncThemeColorValue(form) {
    const source = form.querySelector('[name="favicon_android_background_color"]');
    const target = form.querySelector('[name="favicon_theme_color"]');
    if (source && target) {
      target.value = source.value || '#ffffff';
    }
  }

  function applyGenerationHint(form) {
    const hint = form.querySelector('[data-favicon-generation-hint]');
    if (!hint) {
      return;
    }

    const selectedFids = (inputValue(form, 'favicon_source_fid[fids]', '') || '').trim();
    const fileInput = form.querySelector('input[type="file"][name^="files[favicon_source_fid"]');
    const hasPendingFile = Boolean(fileInput && fileInput.files && fileInput.files.length > 0);
    const shouldShow = selectedFids !== '' || hasPendingFile;

    if (shouldShow) {
      hint.removeAttribute('hidden');
    }
    else {
      hint.setAttribute('hidden', 'hidden');
    }
  }

  function applyPreviewLabels(form) {
    const iosIconName = (inputValue(form, 'favicon_ios_icon_name', '') || '').trim()
      || (inputValue(form, 'favicon_manifest_name', '') || '').trim()
      || 'Site name';
    const androidIconName = (inputValue(form, 'favicon_manifest_short_name', '') || '').trim()
      || (inputValue(form, 'favicon_manifest_name', '') || '').trim()
      || 'Site name';

    form.querySelectorAll('[data-preview-label="ios"]').forEach((label) => {
      label.textContent = iosIconName;
    });
    form.querySelectorAll('[data-preview-label="android"]').forEach((label) => {
      label.textContent = androidIconName;
    });
  }

  function refreshPreviews(form) {
    const iosPadding = clampPadding(inputValue(form, 'favicon_ios_padding', 16));
    const androidPadding = clampPadding(inputValue(form, 'favicon_android_padding', 20));
    const browserPadding = Math.min(iosPadding, androidPadding);

    applyBackground(form, '[data-preview-canvas="browser"]', 'favicon_background_color');
    applyBackground(form, '[data-preview-canvas="ios"]', 'favicon_ios_background_color');
    applyBackground(form, '[data-preview-canvas="android"], [data-preview-canvas="maskable"]', 'favicon_android_background_color');

    applyPadding(form, '[data-preview-canvas="browser"]', browserPadding);
    applyPadding(form, '[data-preview-canvas="ios"]', iosPadding);
    applyPadding(form, '[data-preview-canvas="android"]', androidPadding);
    applyPadding(form, '[data-preview-canvas="maskable"]', Math.max(androidPadding, 20));

    syncThemeColorValue(form);
    applyPreviewLabels(form);
    applyGenerationHint(form);
  }

  Drupal.behaviors.emulsifyFaviconPreview = {
    attach(context) {
      once('emulsify-favicon-preview', 'form.system-theme-settings, form[data-drupal-selector="system-theme-settings"]', context).forEach((form) => {
        const watchedNames = [
          'favicon_background_color',
          'favicon_ios_background_color',
          'favicon_ios_padding',
          'favicon_ios_icon_name',
          'favicon_manifest_short_name',
          'favicon_android_background_color',
          'favicon_android_padding',
        ];

        watchedNames.forEach((name) => {
          const input = form.querySelector(`[name="${name}"]`);
          if (!input) {
            return;
          }
          input.addEventListener('input', () => refreshPreviews(form));
          input.addEventListener('change', () => refreshPreviews(form));
        });

        form.querySelectorAll('input[type="file"][name^="files[favicon_source_fid"]').forEach((input) => {
          input.addEventListener('change', () => refreshPreviews(form));
        });

        refreshPreviews(form);
      });
    },
  };
})(Drupal, once);
