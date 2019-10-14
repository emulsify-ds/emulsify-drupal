function requireAll(r) {
  r.keys().forEach(r);
}

requireAll(require.context('../images/', true, /\.svg$/));
