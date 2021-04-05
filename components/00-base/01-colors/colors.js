function unquoted(el) {
  return el.replace(/(^['"])|(['"]$)/g, '');
}

const elements = document.getElementsByClassName('cl-colors__definition');

elements.forEach((element) => {
  const styles = window.getComputedStyle(element, '::before');
  const content = styles.getPropertyValue('content');
  const child = document.createElement('span');
  child.innerHTML = unquoted(content);

  // eslint-disable-next-line no-param-reassign
  element.innerHTML += child;

  // eslint-disable-next-line no-console
  console.log(element);

  // eslint-disable-next-line no-console
  console.log(child);
});
