Drupal.behaviors.tabs = {
  attach(context) {
    const el = context.querySelectorAll('.tabs');
    const tabNavigationLinks = context.querySelectorAll('.tabs__link');
    const tabContentContainers = context.querySelectorAll('.tabs__tab');
    let activeIndex = 0;

    /**
     * goToTab
     * @description Goes to a specific tab based on index. Returns nothing.
     * @param {Number} index The index of the tab to go to
     */
    function goToTab(index) {
      if (
        index !== activeIndex &&
        index >= 0 &&
        index <= tabNavigationLinks.length
      ) {
        tabNavigationLinks[activeIndex].classList.remove('is-active');
        tabNavigationLinks[index].classList.add('is-active');
        tabContentContainers[activeIndex].classList.remove('is-active');
        tabContentContainers[index].classList.add('is-active');
        activeIndex = index;
      }
    }

    /**
     * handleClick
     * @description Handles click event listeners on each of the links in the
     *   tab navigation. Returns nothing.
     * @param {HTMLElement} link The link to listen for events on
     * @param {Number} index The index of that link
     */
    function handleClick(link, index) {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        goToTab(index);
      });
    }

    /**
     * init
     * @description Initializes the component by removing the no-js class from
     *   the component, and attaching event listeners to each of the nav items.
     *   Returns nothing.
     */
    for (let e = 0; e < el.length; e += 1) {
      el[e].classList.remove('no-js');
    }

    for (let i = 0; i < tabNavigationLinks.length; i += 1) {
      const link = tabNavigationLinks[i];
      handleClick(link, i);
    }
  },
};
