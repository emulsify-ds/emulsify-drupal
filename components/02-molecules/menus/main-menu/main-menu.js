Drupal.behaviors.mainMenu = {
  attach(context) {
    const toggleExpand = context.getElementById('toggle-expand');
    const menu = context.getElementById('main-nav');
    if (menu) {
      const expandMenu = menu.getElementsByClassName('expand-sub');

      // Mobile Menu Show/Hide.
      toggleExpand.addEventListener('click', (e) => {
        toggleExpand.classList.toggle('toggle-expand--open');
        menu.classList.toggle('main-nav--open');
        e.preventDefault();
      });

      // Expose mobile sub menu on click.
      Array.from(expandMenu).forEach((item) => {
        item.addEventListener('click', (e) => {
          const menuItem = e.currentTarget;
          const subMenu = menuItem.nextElementSibling;

          menuItem.classList.toggle('expand-sub--open');
          subMenu.classList.toggle('main-menu--sub-open');
        });
      });
    }
  },
};
