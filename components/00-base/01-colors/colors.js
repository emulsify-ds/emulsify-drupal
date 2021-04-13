Drupal.behaviors.displayColorDefinitions = {
  attach() {
    function unquoted(el) {
      return el.replace(/(^['"])|(['"]$)/g, '');
    }
    const elements = document.getElementsByClassName('cl-colors__definition');

    // eslint-disable-next-line func-names
    Array.prototype.forEach.call(elements, function (element) {
      const styles = window.getComputedStyle(element, '::before');
      const content = styles.getPropertyValue('content');
      // eslint-disable-next-line no-param-reassign
      element.innerHTML = unquoted(content);
    });
  },
};
