<img src="./hero.png" />

[![Four Kitchens](https://img.shields.io/badge/4K-Four%20Kitchens-35AA4E.svg)](https://fourkitchens.com/)

<h4 align="center">Design System + Pattern Library + Drupal 8</h4>

Gatsby Starter using the [Emulsify Gatsby Theme](https://github.com/fourkitchens/gatsby-theme-emulsify) for a Design System, [Storybook](https://storybook.js.org/) for a Pattern Library and serves as a starterkit Drupal 8 theme.

## 🚀 Install

`yarn` or `npm install`

## 🔧 Develop

### Storybook

Develop: `yarn develop` or `npm develop`

This combines 3 tasks:
1. `yarn webpack` (CSS compiling/minifying/linting, SVG Sprite generation)
2. `yarn babel` (ES6 transpiling, minification and Drupal behavior wrapping)
3. `yarn storybook` (Storybook watch task)

#### Deploy Storybook

`yarn deploy-storybook`

### Generate Design System

`yarn styleguide` or `npm styleguide`

### Build Tasks

Styleguide: `build-styleguide`
Storybook: `build-storybook`
Babel: `build-babel`
Webpack: `build-webpack`

### Linting

`yarn lint`
