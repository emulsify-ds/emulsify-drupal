---
title: Icons
---

### How to use icons

We are using an SVG sprite generator (details [here](https://www.npmjs.com/package/svg-sprite-loader)), which automatically takes individual SVGs from `/images/icons` and generates `/dist/icons.svg`. Webpack will automatically add your individual SVGs to this sprite.

**Usage**

The SVG component is found here: 
`/components/_patterns/01-atoms/04-images/icons/_icon.twig`. 
See available variables in that file 
as well as instructions for Drupal. Examples of usage below:

Simple: (no BEM renaming)

```
{% include "@atoms/images/icons/_icon.twig" with {
  icon_name: 'menu',
} %}
```

... creates...

```
<svg class="icon">
  <use xmlns:xlink="http://www.w3.org/1999/xlink" 
  xlink:href="/icons.svg#src--menu"></use>
</svg>
```

Complex (BEM classes):

```
{% include "@atoms/04-images/icons/_icon.twig" with {
  icon_base_class: 'toggle',
  icon_blockname: 'main-nav',
  icon_name: 'menu',
} %}
```

... creates...

```
<svg class="main-nav__toggle">
  <use xmlns:xlink="http://www.w3.org/1999/xlink" 
  xlink:href="/icons.svg#src--menu"></use>
</svg>
```
