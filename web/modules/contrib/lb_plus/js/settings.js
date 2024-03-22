(($, Drupal, once) => {
  // Disable LB+ Admin Buttons on this settings page.
  Drupal.behaviors.LBPlusDisableAdminButtons = {
    attach: (context, settings) => {
      once('LBPlusDisableAdminButtons', '.lb-plus-section-admin a', context).forEach(element => {
        const events = $._data(element, 'events');
        // Remove any already existing click handlers.
        $.each(events, (eventType, eventHandlers) => {
          $.each(eventHandlers, (index, handlerObj) => {
            $(element).off(eventType, handlerObj.handler);
          });
        });
        element.onclick = e => {
          // Disable the admin icons.
          e.preventDefault();
          e.stopPropagation();
        };
      });
    }
  };
  // Live update the colors
  Drupal.behaviors.LBPlusColors = {
    attach: (context, settings) => {
      const styleSettings = drupalSettings['LB+'].theme_styles;

      let styles = document.getElementById('lb-plus-color-styles');
      if (styles === null) {
        styles = document.createElement('style');
        styles.id = 'lb-plus-color-styles'
        let heads = document.getElementsByTagName('head');
        for (let head of heads) {
          head.appendChild(styles);
        }
      }
      const updateStyles = (e) => {
        let rules = '';
        document.querySelectorAll('[type="color"]').forEach((color) => {
          rules += color.getAttribute('css-rule') + ': ' + color.value + ";\n";
        });
        const styles = document.getElementById('lb-plus-color-styles');
        styles.innerHTML = ":root {\n" + rules + "\n}\n";
      };
      updateStyles();

      once('LBPlusColors', '[type="color"]', context).forEach(colorInput => {

        colorInput.oninput = (e) => {
          updateStyles();
        };
      });
    },
  };
})(jQuery, Drupal, once);
