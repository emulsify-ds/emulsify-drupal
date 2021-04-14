Drupal.behaviors.displayColorDefinitions = {
  attach() {
    function unquoted(el) {
      return el.replace(/(^['"])|(['"]$)/g, '');
    }
    const elements = document.getElementsByClassName('cl-colors__definition');

    // eslint-disable-next-line func-names
    Array.prototype.forEach.call(elements, function (element) {
      const stylesBefore = window.getComputedStyle(element, '::before');
      const stylesAfter = window.getComputedStyle(element, '::after');
      const contentBefore = stylesBefore.getPropertyValue('content');
      const contentAfter = stylesAfter.getPropertyValue('content');
      const spanBefore = document.createElement('span');
      const spanAfter = document.createElement('span');
      spanBefore.classList.add('cl-colors__definition_item');
      spanAfter.classList.add('cl-colors__definition_item');
      spanBefore.innerHTML = unquoted(contentBefore);
      spanAfter.innerHTML = unquoted(contentAfter);
      element.appendChild(spanBefore);
      element.appendChild(spanAfter);
    });
  },
};
