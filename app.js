// Empty (needed for webpack)
import './components/style.css';

function requireAll(r) {
  r.keys().forEach(r);
}

requireAll(require.context('./images/', true, /\.svg$/));
