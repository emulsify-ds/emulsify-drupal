(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.emulsifySocialSharePreview = {
    attach(context) {
      const selector = '[data-social-share-preview]';
      const previews = Array.from(context.querySelectorAll(selector));
      if (context.matches && context.matches(selector)) {
        previews.unshift(context);
      }
      once('emulsify-social-share-preview', previews).forEach((preview) => {
        const controls = {};
        preview
          .querySelectorAll('[data-social-preview-control]')
          .forEach((control) => {
            controls[control.dataset.socialPreviewControl] = control;
          });
        const card = preview.querySelector('[data-social-preview-card]');
        const altControl = preview
          .closest('form')
          ?.querySelector('[name="social_share_image_alt"]');
        const refresh = () => {
          card.dataset.platform = controls.platform.value;
          card.dataset.device = controls.device.value;
          preview.querySelector('[data-social-preview-platform]').textContent =
            controls.platform.selectedOptions[0].textContent;
          for (const name of ['title', 'description']) {
            for (const element of preview.querySelectorAll(
              `[data-social-preview-${name}]`,
            )) {
              element.textContent = controls[name].value.trim();
              element.hidden = controls[name].value.trim() === '';
            }
          }
          let domain =
            controls.url.value.trim() ||
            preview.dataset.socialPreviewExampleUrl;
          try {
            domain = new globalThis.URL(domain).host;
          } catch {
            // Incomplete URLs are preview text, never navigation targets.
          }
          preview
            .querySelectorAll('[data-social-preview-domain]')
            .forEach((element) => {
              element.textContent = domain;
            });
          const alt = altControl
            ? altControl.value.trim() || preview.dataset.socialPreviewDefaultAlt
            : preview.dataset.socialPreviewImageAlt;
          preview.querySelectorAll('img').forEach((image) => {
            image.alt = alt;
          });
          const metadata = {
            'og:title': controls.title.value.trim(),
            'twitter:title': controls.title.value.trim(),
            'og:description': controls.description.value.trim(),
            'twitter:description': controls.description.value.trim(),
            'og:url': controls.url.value.trim(),
            'og:image:alt': preview.querySelector('img') ? alt : '',
            'twitter:image:alt': preview.querySelector('img') ? alt : '',
          };
          preview
            .querySelectorAll('[data-social-meta-value]')
            .forEach((element) => {
              const value = metadata[element.dataset.socialMetaValue];
              if (value !== undefined) {
                element.textContent = value || element.dataset.emptyValue;
              }
            });
        };
        [...Object.values(controls), altControl]
          .filter(Boolean)
          .forEach((control) => {
            control.addEventListener('input', refresh);
            control.addEventListener('change', refresh);
            // Existing site enhancements can emit jQuery-only change events.
            if (globalThis.jQuery) {
              globalThis
                .jQuery(control)
                .on('change.emulsifySocialPreview', refresh);
            }
          });
        refresh();
      });
    },
  };
})(Drupal, once);
