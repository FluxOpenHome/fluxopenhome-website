(function () {
  'use strict';

  var filterTabs = document.querySelectorAll('.filter-tab');
  var productCards = document.querySelectorAll('.product-card');

  if (!filterTabs.length || !productCards.length) return;

  filterTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var filter = this.getAttribute('data-filter');

      // Update active tab
      filterTabs.forEach(function (t) {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      this.classList.add('active');
      this.setAttribute('aria-selected', 'true');

      // Filter cards
      productCards.forEach(function (card) {
        var categories = card.getAttribute('data-category') || '';

        if (filter === 'all' || categories.indexOf(filter) !== -1) {
          card.style.display = '';
          card.classList.remove('hidden');
        } else {
          card.style.display = 'none';
          card.classList.add('hidden');
        }
      });
    });
  });
})();
