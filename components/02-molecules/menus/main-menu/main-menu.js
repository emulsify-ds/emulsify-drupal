export default function mainMenu() {
  // Use context instead of document IF DRUPAL.
  const toggleExpand = document.getElementById('toggle-expand');
  const menu = document.getElementById('main-nav');
  const expandMenu = menu.getElementsByClassName('expand-sub');

  // Mobile Menu Show/Hide.
  toggleExpand.addEventListener('click', () => {
    toggleExpand.classList.toggle('toggle-expand--open');
    menu.classList.toggle('main-nav--open');
  });

  // Expose mobile sub menu on click.
  for (let i = 0; i < expandMenu.length; i += 1) {
    expandMenu[i].addEventListener('click', (e) => {
      const menuItem = e.currentTarget;
      const subMenu = menuItem.nextElementSibling;

      menuItem.classList.toggle('expand-sub--open');
      subMenu.classList.toggle('main-menu--sub-open');
    });
  }
}
