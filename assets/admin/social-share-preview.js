(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.emulsifySocialSharePreview = {
    attach(context) {
      once(
        'emulsify-social-share-preview',
        '[data-social-share-preview]',
        context,
      ).forEach((preview) => {
        const controls = {};
        preview
          .querySelectorAll('[data-social-preview-control]')
          .forEach((control) => {
            controls[control.dataset.socialPreviewControl] = control;
          });
        const card = preview.querySelector('[data-social-preview-card]');
        const refresh = () => {
          card.dataset.platform = controls.platform.value;
          card.dataset.device = controls.device.value;
          preview.querySelector('[data-social-preview-platform]').textContent =
            controls.platform.selectedOptions[0].textContent;
          ['title', 'description'].forEach((name) => {
            preview.querySelector(`[data-social-preview-${name}]`).textContent =
              controls[name].value;
          });
          let domain = controls.url.value;
          try {
            domain = new globalThis.URL(domain).host;
          } catch {
            // Incomplete URLs are preview text, never navigation targets.
          }
          preview.querySelector('[data-social-preview-domain]').textContent =
            domain;
        };
        Object.values(controls).forEach((control) => {
          control.addEventListener('input', refresh);
          control.addEventListener('change', refresh);
        });
        refresh();
      });
    },
  };
})(Drupal, once);
