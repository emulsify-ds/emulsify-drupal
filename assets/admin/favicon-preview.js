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
    'favicon_ios_background_custom',
    'favicon_ios_background_color',
    'favicon_ios_padding',
    'favicon_ios_icon_name',
    'favicon_manifest_short_name',
    'favicon_android_background_custom',
    'favicon_android_background_color',
    'favicon_android_padding',
    'favicon_source_fid[fids]',
    'favicon_dark_mode',
    'favicon_dark_background_enabled',
    'favicon_dark_background_color',
    'favicon_dark_source_fid[fids]',
    'favicon_dark_source_svg',
    'favicon_dark_source_filename',
  ];

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
   * Applies inherited platform colors and synchronizes Android theme metadata.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   */
  function syncBackgroundColors(form) {
    const browserColor = inputValue(
      form,
      'favicon_background_color',
      '#ffffff',
    );
    ['ios', 'android'].forEach((platform) => {
      const color = form.querySelector(
        `[name="favicon_${platform}_background_color"]`,
      );
      if (
        color &&
        !inputValue(form, `favicon_${platform}_background_custom`, true)
      ) {
        color.value = browserColor;
      }
    });
    const target = form.querySelector('[name="favicon_theme_color"]');
    if (target) {
      // The PHP layer treats the Android background color as the source of
      // truth for manifest theme-color metadata.
      target.value = inputValue(
        form,
        'favicon_android_background_color',
        browserColor,
      );
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

    const selectedFids = (
      inputValue(form, 'favicon_source_fid[fids]', '') || ''
    ).trim();
    const fileInput = form.querySelector(
      'input[type="file"][name^="files[favicon_source_fid"]',
    );
    const hasPendingFile = Boolean(
      fileInput && fileInput.files && fileInput.files.length > 0,
    );
    const shouldShow = selectedFids !== '' || hasPendingFile;

    if (shouldShow) {
      hint.removeAttribute('hidden');
    } else {
      hint.setAttribute('hidden', 'hidden');
    }
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

    ['favicon_source_fid', 'favicon_dark_source_fid'].forEach((name) => {
      const fileInput = form.querySelector(
        `input[type="file"][name^="files[${name}"]`,
      );
      state[`${name}PendingFile`] = Boolean(
        fileInput && fileInput.files && fileInput.files.length > 0,
      );
    });

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
    return (
      Object.keys(left).every((key) => left[key] === right[key]) &&
      Object.keys(right).every((key) => left[key] === right[key])
    );
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

    const isDirty = !isSameTrackedState(
      initialState,
      captureTrackedState(form),
    );
    if (notice) {
      if (isDirty) {
        notice.removeAttribute('hidden');
      } else {
        notice.setAttribute('hidden', 'hidden');
      }
    }

    if (button) {
      button.value = isDirty
        ? button.dataset.dirtyLabel || button.value
        : button.dataset.defaultLabel || button.value;
    }
  }

  /**
   * Updates generation helper UI state from current form values.
   *
   * @param {HTMLFormElement} form
   *   The theme settings form.
   */
  function refreshPreviews(form) {
    // Images, framing, and labels describe saved output. Unsaved changes only
    // update the generation controls and dirty-state notice.
    syncBackgroundColors(form);
    applyGenerationHint(form);
    applyDirtyState(form);
  }

  Drupal.behaviors.emulsifyFaviconPreview = {
    /**
     * Attaches generation helper behavior to the theme settings form.
     *
     * @param {HTMLElement|Document} context
     *   Drupal behavior context.
     */
    attach(context) {
      const selector =
        'form.system-theme-settings, form[data-drupal-selector="system-theme-settings"]';
      const forms = Array.from(context.querySelectorAll(selector));
      const parentForm = context.closest?.(selector);
      if (parentForm) {
        forms.push(parentForm);
      }
      once('emulsify-favicon-preview', forms).forEach((form) => {
        const refresh = () => refreshPreviews(form);
        // Delegation also handles managed-file inputs replaced through AJAX.
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        if (globalThis.jQuery) {
          globalThis.jQuery(form).on('change.emulsifyFaviconPreview', refresh);
        }

        // Capture the initial persisted values after listeners are attached so
        // dirty-state UI compares against the loaded form, not later edits.
        syncBackgroundColors(form);
        form.emulsifyFaviconInitialState = captureTrackedState(form);
      });
      // An AJAX replacement can change file IDs without replacing the form.
      forms.forEach(refreshPreviews);
    },
  };
})(Drupal, once);
