function requireAll(r) {
  r.keys().forEach(r);
}

requireAll(require.context('../assets/icons/', true, /\.svg$/));
