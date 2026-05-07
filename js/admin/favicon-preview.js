(function (Drupal, once) {
  'use strict';

  /**
   * Form element names that should toggle the dirty-state notice and button.
   *
   * @type {string[]}
   */
  const TRACKED_NAMES = [
    'favicon_package_enabled',
    'favicon_background_color',
    'favicon_ios_background_color',
    'favicon_ios_padding',
    'favicon_ios_icon_name',
    'favicon_manifest_short_name',
    'favicon_android_background_color',
    'favicon_android_padding',
    'favicon_source_fid[fids]',
  ];

  /**
   * Normalizes a padding value to the UI's allowed range.
   *
   * @param {string|number|boolean} value
   *   Raw form value.
   *
   * @return {number}
   *   Padding clamped to the 0-40 range.
   */
  function clampPadding(value) {
    const numeric = Number.parseInt(value, 10);
    if (Number.isNaN(numeric)) {
      return 0;
    }
    return Math.max(0, Math.min(40, numeric));
  }

  /**
   * Returns a normalized form value for a named input.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   * @param {string} name
   *   The input name attribute to query.
   * @param {string|boolean} [fallback='']
   *   Default value when the input is not present.
   *
   * @return {string|boolean}
   *   Checkbox values return booleans, everything else returns strings.
   */
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

  /**
   * Applies the selected background color to one or more preview canvases.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   * @param {string} selector
   *   Canvas selector to update.
   * @param {string} colorName
   *   Form input name that stores the color value.
   */
  function applyBackground(form, selector, colorName) {
    const color = inputValue(form, colorName, '#ffffff');
    form.querySelectorAll(selector).forEach((canvas) => {
      canvas.style.setProperty('--preview-background', color);
    });
  }

  /**
   * Applies the selected padding percentage to preview canvases.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   * @param {string} selector
   *   Canvas selector to update.
   * @param {number} padding
   *   Padding percentage.
   */
  function applyPadding(form, selector, padding) {
    form.querySelectorAll(selector).forEach((canvas) => {
      canvas.style.setProperty('--preview-padding', `${padding}%`);
    });
  }

  /**
   * Keeps the hidden theme-color form value aligned with the Android color UI.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   */
  function syncThemeColorValue(form) {
    const source = form.querySelector('[name="favicon_android_background_color"]');
    const target = form.querySelector('[name="favicon_theme_color"]');
    if (source && target) {
      // The PHP layer treats the Android background color as the source of
      // truth for manifest theme-color metadata.
      target.value = source.value || '#ffffff';
    }
  }

  /**
   * Shows the generation hint once a source file is selected or already saved.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   */
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

  /**
   * Syncs the text labels used in the iOS and Android previews.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   */
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

  /**
   * Captures the subset of form state that affects package regeneration.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   *
   * @return {Record<string, string|boolean>}
   *   Normalized tracked values.
   */
  function captureTrackedState(form) {
    const state = {};

    TRACKED_NAMES.forEach((name) => {
      const value = inputValue(form, name, '');
      state[name] = typeof value === 'boolean' ? value : String(value).trim();
    });

    const fileInput = form.querySelector('input[type="file"][name^="files[favicon_source_fid"]');
    state.hasPendingFile = Boolean(fileInput && fileInput.files && fileInput.files.length > 0);

    return state;
  }

  /**
   * Compares two tracked form state snapshots.
   *
   * @param {Record<string, string|boolean>} left
   *   First snapshot.
   * @param {Record<string, string|boolean>} right
   *   Second snapshot.
   *
   * @return {boolean}
   *   TRUE when both snapshots contain the same values.
   */
  function isSameTrackedState(left, right) {
    return Object.keys(left).every((key) => left[key] === right[key])
      && Object.keys(right).every((key) => left[key] === right[key]);
  }

  /**
   * Toggles dirty-state UI based on whether tracked values changed.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   */
  function applyDirtyState(form) {
    const notice = form.querySelector('[data-favicon-dirty-state]');
    const button = form.querySelector('[data-favicon-regenerate-button]');
    if (!notice && !button) {
      return;
    }

    const initialState = form.emulsifyFaviconInitialState;
    if (!initialState) {
      return;
    }

    const isDirty = !isSameTrackedState(initialState, captureTrackedState(form));
    if (notice) {
      if (isDirty) {
        notice.removeAttribute('hidden');
      }
      else {
        notice.setAttribute('hidden', 'hidden');
      }
    }

    if (button) {
      button.value = isDirty
        ? (button.dataset.dirtyLabel || button.value)
        : (button.dataset.defaultLabel || button.value);
    }
  }

  /**
   * Recomputes all preview and helper UI state from current form values.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   */
  function refreshPreviews(form) {
    const iosPadding = clampPadding(inputValue(form, 'favicon_ios_padding', 16));
    const androidPadding = clampPadding(inputValue(form, 'favicon_android_padding', 20));
    // Browser favicons are the tightest preview, so use the smaller platform
    // padding value to avoid overstating available space.
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
    applyDirtyState(form);
  }

  Drupal.behaviors.emulsifyFaviconPreview = {
    /**
     * Attaches live preview syncing to the theme settings form.
     *
     * @param {HTMLElement|Document} context
     *   Drupal behavior context.
     */
    attach(context) {
      once('emulsify-favicon-preview', 'form.system-theme-settings, form[data-drupal-selector="system-theme-settings"]', context).forEach((form) => {
        TRACKED_NAMES.forEach((name) => {
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

        // Capture the initial persisted values after listeners are attached so
        // dirty-state UI compares against the loaded form, not later edits.
        form.emulsifyFaviconInitialState = captureTrackedState(form);
        refreshPreviews(form);
      });
    },
  };
})(Drupal, once);
