const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const { JSDOM } = require('jsdom');
const sass = require('sass');

const root = path.resolve(__dirname, '../..');
const fixture = String.raw`
namespace Drupal\Component\Utility {
  final class Html {
    public static function escape(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
  }
}
namespace Drupal\Core\Render {
  final class Markup {
    public static function create(string $text): string { return $text; }
  }
}
namespace Drupal\Core\StringTranslation {
  trait StringTranslationTrait {
    public function t(string $text, array $args = []): string { return strtr($text, $args); }
  }
}
namespace {
  require dirname($argv[1]) . '/SocialShareMetadata.php';
  require $argv[1];
  $builder = new \Drupal\emulsify\Social\SocialSharePreviewBuilder();
  echo json_encode($builder->build(
    json_decode($argv[3], TRUE, flags: JSON_THROW_ON_ERROR),
    $argv[2],
    ['width' => 1200, 'height' => 630, 'mime' => 'image/png'],
    'Site <strong>name</strong>',
    'Site slogan & details',
    'https://example.test/page',
  ), JSON_THROW_ON_ERROR);
}`;
const savedSettings = {
  social_share_image_alt: 'Artwork "alt" <script>bad()</script>',
  social_share_image_title: 'Title <strong>text</strong>',
  social_share_image_description: 'Description & details',
  social_share_image_url: 'https://saved.example.test/page',
};

function previewDefinition(imageUrl = 'https://example.test/processed/saved.png?name="image"', settings = savedSettings) {
  const result = spawnSync('php', ['-r', fixture, path.join(root, 'src/Social/SocialSharePreviewBuilder.php'), imageUrl, JSON.stringify(settings)], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  return JSON.parse(result.stdout);
}

// Behavior tests use native controls from the Form API definition. The Drupal
// smoke test separately verifies their actual themed rendering and config saves.
function previewMarkup(imageUrl, settings = savedSettings) {
  const definition = previewDefinition(imageUrl, settings);
  const { document } = new JSDOM().window;
  const form = document.createElement('form');
  const alt = document.createElement('input');
  alt.name = 'social_share_image_alt';
  alt.defaultValue = settings.social_share_image_alt ?? '';
  form.appendChild(alt);
  const wrapper = document.createElement('div');
  for (const [name, value] of Object.entries(definition['#attributes'])) {
    wrapper.setAttribute(name, Array.isArray(value) ? value.join(' ') : value === true ? '' : value);
  }
  const addControls = (element) => {
    if (element['#attributes']?.['data-social-preview-control']) {
      const control = document.createElement(element['#type'] === 'textfield' ? 'input' : element['#type']);
      control.dataset.socialPreviewControl = element['#attributes']['data-social-preview-control'];
      for (const [value, text] of Object.entries(element['#options'] ?? {})) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = text;
        option.defaultSelected = value === element['#default_value'];
        control.appendChild(option);
      }
      control.value = element['#default_value'];
      if (element['#type'] !== 'select') control.defaultValue = element['#default_value'];
      wrapper.appendChild(control);
    }
    for (const [name, child] of Object.entries(element)) {
      if (!name.startsWith('#') && child && typeof child === 'object') addControls(child);
    }
  };
  addControls(definition.defaults);
  addControls(definition.options);
  const rows = definition.metadata['#rows'].map(row => `<tr>${row.map(cell => `<td>${cell.data['#markup']}</td>`).join('')}</tr>`).join('');
  wrapper.insertAdjacentHTML('beforeend', `<table><caption>${definition.metadata['#caption']}</caption><tbody>${rows}</tbody></table>${definition.card['#markup']}`);
  form.appendChild(wrapper);
  return form.outerHTML;
}

const metadataValue = (document, tag) => document.querySelector(`[data-social-meta-value="${tag}"]`).textContent;

function attachBehavior(relativePath, markup = previewMarkup()) {
  const { window } = new JSDOM(markup, { runScripts: 'outside-only' });
  const { document } = window;
  const attached = new WeakSet();
  window.Drupal = { behaviors: {} };
  window.once = (id, elements) => [...elements].filter((element) => {
    if (attached.has(element)) return false;
    attached.add(element);
    return true;
  });
  window.fetch = () => { throw new Error('The preview must not fetch remote metadata'); };
  window.eval(fs.readFileSync(path.join(root, relativePath), 'utf8'));
  const handlers = new WeakMap();
  window.jQuery = (element) => ({
    on(events, listener) {
      for (const event of events.split(' ')) {
        const name = event.split('.')[0];
        const registered = handlers.get(element) ?? {};
        registered[name] = listener;
        handlers.set(element, registered);
      }
    },
    trigger(event) {
      handlers.get(element)?.[event]?.();
    },
  });
  const attach = (context = document) => window.Drupal.behaviors.emulsifySocialSharePreview.attach(context);
  const change = (name, value) => {
    const control = document.querySelector(`[data-social-preview-control="${name}"]`);
    control.value = value;
    control.dispatchEvent(new window.Event('input'));
  };
  attach();
  return { window, document, attach, change };
}

test('saved defaults and local preview options use administration-themed Form API controls', () => {
  const preview = previewDefinition();
  assert.equal(preview['#type'], 'container');
  assert.equal(preview.defaults['#type'], 'fieldset');
  assert.equal(preview.defaults['#title'], 'Default sharing metadata');
  assert.equal(preview.options['#type'], 'fieldset');
  assert.equal(preview.options['#title'], 'Preview options');
  const controls = [
    preview.options.selectors.emulsify_social_preview_platform,
    preview.options.selectors.emulsify_social_preview_device,
    preview.defaults.social_share_image_title,
    preview.defaults.social_share_image_description,
    preview.defaults.social_share_image_url,
  ];
  assert.deepEqual(controls.map(element => element['#type']), ['select', 'select', 'textfield', 'textarea', 'textfield']);
  for (const element of controls) assert(element['#title'], 'Every control needs a themed label');
  assert.equal(preview.metadata['#type'], 'table');
  assert.equal(preview.metadata['#caption'], 'Fallback metadata after saving');
  const { document } = new JSDOM(previewMarkup()).window;
  assert.equal(document.querySelectorAll('script, [onerror], strong').length, 0);
  assert.equal(document.querySelector('img').getAttribute('alt'), savedSettings.social_share_image_alt);
  assert.equal(document.querySelector('img').getAttribute('width'), '1200');
  assert.equal(document.querySelector('[data-social-preview-title]').textContent, savedSettings.social_share_image_title);
  assert.equal(metadataValue(document, 'og:title'), savedSettings.social_share_image_title);
  assert.equal(metadataValue(document, 'twitter:description'), savedSettings.social_share_image_description);
  assert.equal(metadataValue(document, 'og:image:type'), 'image/png');
  assert.equal(metadataValue(document, 'og:image:width'), '1200');
  assert.equal(metadataValue(document, 'og:image:height'), '630');
  assert.match(metadataValue(document, 'og:image'), /\/processed\/saved\.png/);
});

test('unconfigured metadata stays blank with placeholders and a clearly labeled canonical URL example', () => {
  const preview = previewDefinition('', {});
  assert.equal(preview.defaults.social_share_image_title['#default_value'], '');
  assert.equal(preview.defaults.social_share_image_title['#placeholder'], 'Site <strong>name</strong>');
  assert.equal(preview.defaults.social_share_image_description['#default_value'], '');
  assert.equal(preview.defaults.social_share_image_url['#default_value'], '');
  const { document } = new JSDOM(previewMarkup('', {})).window;
  assert.equal(document.querySelector('img'), null);
  assert.match(document.querySelector('.emulsify-social-preview__empty').textContent, /Save an image path or upload an image/);
  assert.equal(document.querySelector('[data-social-preview-title]').hidden, true);
  assert.equal(metadataValue(document, 'og:title'), 'Not set');
  assert.equal(metadataValue(document, 'og:image:alt'), 'Not set');
  assert.equal(metadataValue(document, 'og:url'), 'Each page’s canonical URL (example: https://example.test/page)');
});

for (const relativePath of ['assets/admin/social-share-preview.js', 'dist/js/admin/social-share-preview.js']) {
  test(`${relativePath}: controls update the card and metadata safely without changing the saved image URL`, () => {
    const { window, document, attach, change } = attachBehavior(relativePath);
    const originalImage = document.querySelector('img').outerHTML;
    const savedImageUrl = metadataValue(document, 'og:image');
    assert.equal(metadataValue(document, 'og:image:alt'), savedSettings.social_share_image_alt);
    for (const platform of ['x', 'linkedin', 'pinterest', 'facebook']) {
      change('platform', platform);
      assert.equal(document.querySelector('[data-social-preview-card]').dataset.platform, platform);
      assert.equal(document.querySelector('[data-social-preview-platform]').textContent, document.querySelector('[data-social-preview-control="platform"]').selectedOptions[0].textContent);
    }
    change('device', 'mobile');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.device, 'mobile');
    change('title', '<img src=x onerror=bad()>');
    change('description', '<script>bad()</script>');
    change('url', 'https://preview.example.test/a?b=1');
    assert.equal(document.querySelector('[data-social-preview-domain]').textContent, 'preview.example.test');
    assert.equal(metadataValue(document, 'og:title'), '<img src=x onerror=bad()>');
    assert.equal(metadataValue(document, 'twitter:title'), '<img src=x onerror=bad()>');
    assert.equal(metadataValue(document, 'og:description'), '<script>bad()</script>');
    assert.equal(metadataValue(document, 'twitter:description'), '<script>bad()</script>');
    assert.equal(metadataValue(document, 'og:url'), 'https://preview.example.test/a?b=1');
    change('url', '<script>bad()</script>');
    assert.equal(document.querySelector('[data-social-preview-domain]').textContent, '<script>bad()</script>');
    assert.equal(document.querySelectorAll('script, [onerror]').length, 0);
    assert.equal(document.querySelector('img').outerHTML, originalImage);
    assert.equal(metadataValue(document, 'og:image'), savedImageUrl);
    const alt = document.querySelector('[name="social_share_image_alt"]');
    alt.value = 'A different <script>description</script>';
    alt.dispatchEvent(new window.Event('input'));
    assert.equal(document.querySelector('img').alt, alt.value);
    assert.equal(metadataValue(document, 'og:image:alt'), alt.value);
    assert.equal(metadataValue(document, 'twitter:image:alt'), alt.value);
    alt.value = '';
    alt.dispatchEvent(new window.Event('change'));
    assert.equal(metadataValue(document, 'og:image:alt'), 'Site <strong>name</strong>');
    change('title', '');
    change('description', '');
    change('url', '');
    assert.equal(document.querySelector('[data-social-preview-title]').hidden, true);
    assert.equal(document.querySelector('[data-social-preview-description]').hidden, true);
    assert.equal(document.querySelector('[data-social-preview-domain]').textContent, 'example.test');
    assert.equal(metadataValue(document, 'og:title'), 'Not set');
    assert.equal(metadataValue(document, 'twitter:description'), 'Not set');
    assert.match(metadataValue(document, 'og:url'), /Each page’s canonical URL \(example:/);
    const platform = document.querySelector('[data-social-preview-control="platform"]');
    platform.value = 'pinterest';
    window.jQuery(platform).trigger('change');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.platform, 'pinterest', 'Existing site enhancements may emit only a jQuery change event');
    const device = document.querySelector('[data-social-preview-control="device"]');
    device.value = 'desktop';
    window.jQuery(device).trigger('change');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.device, 'desktop');
    attach(document.querySelector('[data-social-share-preview]'));
    change('device', 'mobile');
    assert.equal(document.querySelector('[data-social-preview-card]').dataset.device, 'mobile');
    window.close();
  });

  test(`${relativePath}: an empty saved image does not claim image alt tags will be populated`, () => {
    const { window, document } = attachBehavior(relativePath, previewMarkup('', {}));
    assert.equal(metadataValue(document, 'og:image:alt'), 'Not set');
    assert.equal(metadataValue(document, 'twitter:image:alt'), 'Not set');
    window.close();
  });
}

for (const relativePath of ['assets/admin/social-share-preview.scss', 'dist/css/admin/social-share-preview.css']) {
  test(`${relativePath}: platform and device controls select distinct visible card layouts`, () => {
    const css = relativePath.endsWith('.scss')
      ? sass.compile(path.join(root, relativePath)).css
      : fs.readFileSync(path.join(root, relativePath), 'utf8');
    const { window, document, change } = attachBehavior('assets/admin/social-share-preview.js');
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
    const card = document.querySelector('[data-social-preview-card]');
    const cardStyle = () => window.getComputedStyle(card);
    const partStyle = name => window.getComputedStyle(card.querySelector(`.emulsify-social-preview__${name}`));
    const imageRatio = () => {
      const [width, height = '1'] = partStyle('image').aspectRatio.split('/');
      return Number(width) / Number(height);
    };
    assert.equal(cardStyle().borderRadius, '0');
    assert.equal(imageRatio(), 1.91);
    assert.equal(partStyle('domain').order, '0');
    assert.equal(partStyle('title').order, '1');
    assert.equal(partStyle('description').display, 'block');
    assert.equal(partStyle('description').textOverflow, 'ellipsis');
    change('platform', 'x');
    assert.equal(cardStyle().borderRadius, '1rem');
    assert.equal(partStyle('body').position, 'absolute', 'X places its headline inside the image');
    assert.equal(partStyle('title').display, 'inline-block');
    assert.match(partStyle('title').backgroundColor, /rgba\(0, 0, 0, 0\.85\)/, 'X uses a dark headline chip');
    assert.equal(partStyle('domain').display, 'none');
    assert.equal(partStyle('description').display, 'none');
    assert.equal(imageRatio(), 1.91);
    change('title', '');
    assert.equal(partStyle('title').display, 'none', 'An empty headline must stay hidden despite platform display rules');
    change('title', 'Saved title');
    change('platform', 'linkedin');
    assert.equal(cardStyle().borderRadius, '0');
    assert.notEqual(partStyle('body').position, 'absolute');
    assert.equal(partStyle('title').order, '0');
    assert.equal(partStyle('domain').order, '1');
    assert.equal(partStyle('domain').textTransform, 'none');
    assert.equal(partStyle('description').display, 'none');
    change('platform', 'pinterest');
    assert.equal(imageRatio(), 2 / 3);
    assert.equal(cardStyle().maxWidth, '20rem');
    change('device', 'mobile');
    assert.equal(cardStyle().maxWidth, '16rem');
    change('platform', 'facebook');
    assert.equal(cardStyle().maxWidth, '20rem');
    change('device', 'desktop');
    assert.equal(cardStyle().maxWidth, '32.5rem');
    window.close();
  });
}
