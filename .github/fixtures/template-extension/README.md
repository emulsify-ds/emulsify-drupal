# Pre-extension rendered goldens

These seven HTML files were captured from commit
`56007e686017baa4c117a5e1a03ba14ea75a97da`, before adding the nine Twig extension
blocks. Drupal 11.3.16 and 11.4.6 produced identical bytes for every case using
the deterministic contexts in `../../scripts/template-extension-smoke.php`.

The cases cover all four modified templates, including empty pages and regions,
escaped titles and attributes, and blocks with and without a visible label.
`region-empty.html` is deliberately zero bytes. The tests do not normalize
whitespace, parse and reserialize HTML, or regenerate the expected output in CI.

Capture was performed with `EMULSIFY_CAPTURE_TEMPLATE_GOLDENS=1` while invoking
the smoke script through Drush against the pre-change parent. That opt-in mode
is for deliberate baseline maintenance; normal invocations only compare bytes.
Do not regenerate these files to hide a rendered-output regression.
