import * as section from './lb-plus/section.js';
import * as blocks from './lb-plus/blocks.js';
import * as dropZones from './lb-plus/drop-zones.js';

(($, Drupal, once) => {

  Drupal.LBPlus = {};

  /**
   * LB+ ajax error.
   *
   * Removes the ajax spinner and displays an error message.
   */
  Drupal.LBPlus.ajaxError = (message, error) => {
    // Remove the AJAX progress spinner.
    document.querySelectorAll('.ajax-progress').forEach(progress => {
      progress.remove();
    });
    // Display a message.
    const messages = new Drupal.Message();
    messages.add('<span id="lb-plus-ajax-error">' + message + '</span>', {type: 'error'});
    document.getElementById('lb-plus-ajax-error').scrollIntoView({behavior: 'smooth', block: 'start'});
    console.error(error.responseText);
  };

  section.default($, Drupal, once, dropZones);
  blocks.default($, Drupal, once, dropZones);
})(jQuery, Drupal, once);


/**
 * Set Layout Builder Inactive
 *
 * Sets the parent layout builders inactive while a child layout builder is
 * active when using nested layout blocks.
 */
jQuery.fn.LBPlusSetLayoutBuilderInactive = () => {
  for (const layoutBuilders of document.querySelectorAll('.layout-builder.active')) {
    layoutBuilders.classList.remove('active');
  }
  document.getElementById('lb-plus-nested-layout-breadcrumb').style.display = 'block';
};
