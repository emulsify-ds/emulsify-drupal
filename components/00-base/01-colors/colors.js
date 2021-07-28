Drupal.behaviors.displayColorDefinitions = {
  attach() {
    /**
     * unquote
     * @description Returns a string without quotes.
     * @param {HTMLElement} el element with value
     */
    function handleClick(element, color) {
      element.addEventListener('click', () => {
        const clrValue = color.textContent.split('Usage: ').pop();
        navigator.clipboard.writeText(clrValue);
      });
    }

    /**
     * unquote
     * @description Returns a string without quotes.
     * @param {HTMLElement} el element with value
     */
    function unquoted(el) {
      return el.replace(/(^['"])|(['"]$)/g, '');
    }
    const elements = document.getElementsByClassName('cl-colors__definition');

    // eslint-disable-next-line func-names
    elements.forEach((element) => {
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

      handleClick(element, spanBefore);
    });
  },
};
