(($, Drupal, once) => {

  Drupal.behaviors.LBPlusToolbarAndSideBarOffsets = {
    attach: (context, settings) => {
      $(document).on(`drupalViewportOffsetChange`, (e) => {
        // Set how far left the toolbar can go.
        for (const toolbar of document.getElementsByClassName('lb-plus-toolbar')) {
          toolbar.style.left = Drupal.displace.calculateOffset('left') + 'px';
        }
        // Set the upper and lower boundaries for the sidebar.
        const sideBar = document.getElementById('lb-plus-sidebar');
        sideBar.style.top = Drupal.displace.calculateOffset('top') + 'px';
        sideBar.style.bottom = Drupal.displace.calculateOffset('bottom') + 'px';
      });
    }
  };

  Drupal.behaviors.LBPlusToggleSidebar = {
    attach: (context, settings) => {
      // Toggle the sidebar on page load or when the sidebar contents change.
      once('LBPlusToggleSidebar', '#lb-plus-sidebar', context).forEach(toggleButton => {
        // Persist the current state through page loads.
        let sideBarState = localStorage.getItem('LBPlusSideBarState')
        if (sideBarState && sideBarState === 'block') {
          Drupal.behaviors.LBPlusToggleSidebar.openSidebar();
        } else {
          Drupal.behaviors.LBPlusToggleSidebar.closeSidebar();
        }
      });

      once('LBPlusToggleSidebar', '#lb-plus-toggle-sidebar', context).forEach(toggleButton => {
        // Toggle the sidebar on and off when clicked.
        toggleButton.onclick = e => {
          const sideBar = document.getElementById('lb-plus-sidebar');
          if (sideBar.style.display === 'none' || sideBar.style.display === '') {
            Drupal.behaviors.LBPlusToggleSidebar.openSidebar();
          } else {
            Drupal.behaviors.LBPlusToggleSidebar.closeSidebar();
          }
        };
      });

      // Close sidebar button.
      once('LBPlusCloseSidebar', '#close-add-block-sidebar', context).forEach(closeButton => {
        closeButton.onclick = e => {
          Drupal.behaviors.LBPlusToggleSidebar.closeSidebar();
        };
      });

      // Blank page sidebar button.
      once('LBPlusBlankPageOpenSidebar', '.blank-page-wrapper', context).forEach(blankPageButton => {
        Drupal.behaviors.LBPlusToggleSidebar.openSidebar();
        blankPageButton.onclick = e => {
          Drupal.behaviors.LBPlusToggleSidebar.openSidebar();
        };
      });

      // Close the sidebar when an offcanvas dialog opens.
      $(window).on('dialog:aftercreate', (event, dialog, $element) => {
        if ($element.length && $element[0].id === 'drupal-off-canvas') {
          Drupal.behaviors.LBPlusToggleSidebar.closeSidebar();
        }
      });
    },
    openSidebar: () => {
      const sideBar = document.getElementById('lb-plus-sidebar');
      // Position the sidebar between any other fixed thingies.
      sideBar.style.top = Drupal.displace.calculateOffset('top') + 'px';
      sideBar.style.bottom = Drupal.displace.calculateOffset('bottom') + 'px';
      // Open the sidebar, remember the state, and set the toggle button rotation.
      sideBar.style.display = 'block';
      localStorage.setItem('LBPlusSideBarState', 'block')
      const toggleSideBarIcon = document.getElementById('lb-plus-toggle-sidebar');
      toggleSideBarIcon.classList.add('spin-move');
      // Move the page content over a bit.
      document.getElementsByClassName('dialog-off-canvas-main-canvas')[0].style.paddingRight = Drupal.displace.calculateOffset('right') + 'px';
    },
    closeSidebar: () => {
      const sideBar = document.getElementById('lb-plus-sidebar');
      // Close the sidebar, remember the state, and set the toggle button rotation.
      sideBar.style.display = 'none'
      localStorage.setItem('LBPlusSideBarState', 'none');
      const toggleSideBarIcon = document.getElementById('lb-plus-toggle-sidebar');
      toggleSideBarIcon.classList.remove('spin-move');
      // Move the page content back in place.
      document.getElementsByClassName('dialog-off-canvas-main-canvas')[0].style.paddingRight = null;
    },
  };


  /**
   * Choose block tabs.
   *
   * @type {{attach: Drupal.behaviors.LBPlusChooseBlockTabs.attach}}
   */
  Drupal.behaviors.LBPlusChooseBlockTabs = {
    attach: (context, settings) => {
      once('LBPlusChooseBlockTabs', '.choose-block-tab', context).forEach(tab => {
        tab.onclick = (e) => {
          // Make no tab active.
          let noLongerActiveElements = [
            ...document.querySelectorAll('.tabbed-content'),
            ...document.querySelectorAll('.choose-block-tab'),
          ];
          noLongerActiveElements.forEach(tabbedContent => {
            tabbedContent.classList.remove('active');
          });
          // Activate the selected tab.
          e.target.classList.add('active')
          document.getElementById(e.target.id + '-content').classList.add('active');
        };
      });
    }
  };

  let layoutBuilderBlocksFiltered = false;

  /**
   * Provides the ability to filter the block listing in "Add block" dialog.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach block filtering behavior to "Add block" dialog.
   */
  Drupal.behaviors.LBPlusBlockFilter = {
    attach(context) {
      const $categories = $('.js-layout-builder-categories', context);
      const $filterLinks = $categories.find('.js-layout-builder-block-link');

      /**
       * Filters the block list.
       *
       * @param {jQuery.Event} e
       *   The jQuery event for the keyup event that triggered the filter.
       */
      const filterBlockList = (e) => {
        const query = e.target.value.toLowerCase();

        /**
         * Shows or hides the block entry based on the query.
         *
         * @param {number} index
         *   The index in the loop, as provided by `jQuery.each`
         * @param {HTMLElement} link
         *   The link to add the block.
         */
        const toggleBlockEntry = (index, link) => {
          const $link = $(link);
          const textMatch =
            link.textContent.toLowerCase().indexOf(query) !== -1;
          // Checks if a category is currently hidden.
          // Toggles the category on if so.
          if ($link.closest('.js-layout-builder-category').is(':hidden')) {
            $link.closest('.js-layout-builder-category').show();
          }
          // Toggle the li tag of the matching link.
          // $link.parent().toggle(textMatch);
          $link.toggle(textMatch);
        };

        // Filter if the length of the query is at least 2 characters.
        if (query.length >= 2) {
          // Attribute to note which categories are closed before opening all.
          $categories
            .find('.js-layout-builder-category:not([open])')
            .attr('remember-closed', '');

          // Open all categories so every block is available to filtering.
          $categories.find('.js-layout-builder-category').attr('open', '');
          // Toggle visibility of links based on query.
          $filterLinks.each(toggleBlockEntry);

          // Only display categories containing visible links.
          $categories
            .find(
              '.js-layout-builder-category:not(:has(.js-layout-builder-block-link:visible))',
            )
            .hide();

          Drupal.announce(
            Drupal.formatPlural(
              $categories.find('.js-layout-builder-block-link:visible').length,
              '1 block is available in the modified list.',
              '@count blocks are available in the modified list.',
            ),
          );
          layoutBuilderBlocksFiltered = true;
        } else if (layoutBuilderBlocksFiltered) {
          layoutBuilderBlocksFiltered = false;
          // Remove "open" attr from categories that were closed pre-filtering.
          $categories
            .find('.js-layout-builder-category[remember-closed]')
            .removeAttr('open')
            .removeAttr('remember-closed');
          // Show all categories since filter is turned off.
          $categories.find('.js-layout-builder-category').show();
          // Show all blocks since filter is turned off.
          $filterLinks.show();
          Drupal.announce(Drupal.t('All available blocks are listed.'));
        }
      };

      $(
        once('block-filter-text', 'input.js-layout-builder-filter', context),
      ).on('input', Drupal.debounce(filterBlockList, 200));
    },
  };
})(jQuery, Drupal, once);
