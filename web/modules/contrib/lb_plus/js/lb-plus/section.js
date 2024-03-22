export default function ($ = jQuery, Drupal, once, dropZones) {

  /**
   *  Section sort handle icon.
   *
   *  This behavior allows the sort sections icon to act like a drag handle for
   *  the entire section. See LBPlusMoveSection ondragend for where it is
   *  disabled.
   */
  Drupal.behaviors.LBPlusDraggableSectionHandles = {
    attach: (context, settings) => {
      once('draggableSectionHandles', '.lb-plus-section-admin .sort', context).forEach(sortHandle => {
        sortHandle.onmousedown = e => {
          // The drag handle was clicked, set the section to draggable.
          let draggedSection = e.target.closest('.lb-plus-section');
          draggedSection.setAttribute('draggable', 'true');
        }
      });
    }
  };

  /**
   * Place new Section.
   *
   * Drag a new section from the sidebar and place it on the page.
   */
  Drupal.behaviors.LBPlusPlaceNewSection = {
    attach: (context, settings) => {
      once('LBPlusPlaceNewSection', '.tabbed-content .draggable-section', context).forEach(draggableSection => {
        draggableSection.ondragstart = e => {
          dropZones.addSectionDropZones(Drupal.t('Add a new section'));
          dropZones.dragStart(e, 'new_section');
        };
        draggableSection.ondragend = e => dropZones.dragEnd(e);
      });
    }
  };

  /**
   * Move section.
   *
   * Drag an existing section to a new location on the page.
   */
  Drupal.behaviors.LBPlusMoveSection = {
    attach: (context, settings) => {
      once('LBPlusMoveSection', '.lb-plus-section', context).forEach(draggableSection => {
        draggableSection.ondragstart = e => {
          // When we drag a block from within a section this event also fires.
          // Only act when we are dragging a section.
          if (e.target.classList.contains('layout-builder__section')) {
            dropZones.addSectionDropZones(Drupal.t('Move section here'));
            dropZones.dragStart(e, 'move_section');
            // Disable hovers on other sections.
            let draggedSection = e.target;
            getSections().forEach(draggableSection => {
              if (draggableSection !== draggedSection) {
                draggableSection.classList.remove('hover');
              }
            });
          }
        };
        draggableSection.ondragend = e => {
          // When we drag a block from within a section this event also fires.
          // Only act when we are dragging a section.
          if (e.target.classList.contains('layout-builder__section')) {
            dropZones.dragEnd(e);
            // The sort icon set this section to draggable. Return it to not
            // draggable now that the section has been dropped so that text can be
            // selected etc.
            e.target.setAttribute('draggable', 'false');
            // Enable section hovers.
            getSections().forEach(draggableSection => {
              if (!draggableSection.classList.contains('hover')) {
                draggableSection.classList.add('hover');
              }
            });
          }
        };
      });
    },
  };

  // A section was dropped.
  document.addEventListener('element-dropped', e => {
    if (e.detail.type !== 'move_section' && e.detail.type !== 'new_section') {
      return;
    }

    let ajaxConfig = {
      type: 'POST',
      dataType: 'text',
      progress: {
        type: 'fullscreen',
        message: Drupal.t('Saving section...')
      },
    };
    const dropZone = e.detail.dropEvent.target;

    // A section was moved.
    if (e.detail.type === 'move_section') {
      const draggedSection = document.getElementById(e.detail.id);
      let submit = {
        from_section_delta: draggedSection.dataset.layoutDelta,
        preceding_section_delta: document.getElementById(dropZone.getAttribute('preceding-section-id'))?.dataset.layoutDelta,
        nested_storage_path_to: document.getElementById(dropZone.getAttribute('section-id')).dataset.nestedStoragePath,
        nested_storage_path_from: draggedSection.dataset.nestedStoragePath,
      };

      // Save the section order.
      ajaxConfig.url = '/lb-plus/move-section/' + drupalSettings['LB+'].sectionStorageType + '/' + drupalSettings['LB+'].sectionStorage;
      ajaxConfig.submit = submit;
      ajaxConfig.error = (error, path) => {
        Drupal.LBPlus.ajaxError(Drupal.t('Unable to save the section order.'), error);
      };
    }

    // A new section was placed.
    if (e.detail.type === 'new_section') {
      // Place an empty section.
      ajaxConfig.url = '/lb-plus/add-empty-section/' + drupalSettings['LB+'].sectionStorageType + '/' + drupalSettings['LB+'].sectionStorage;
      ajaxConfig.submit = { preceding_section: dropZone.getAttribute('preceding-section-id') };
      ajaxConfig.error = (error, path) => {
        Drupal.LBPlus.ajaxError(Drupal.t('Unable to save the new section.'), error);
      };
      if (drupalSettings['LB+'].isLayoutBlock) {
        ajaxConfig.url = ajaxConfig.url + '/' + drupalSettings['LB+'].nestedStoragePath;
      }
    }
    let ajax = Drupal.ajax(ajaxConfig);
    ajax.execute();
  });

}

export const getSections = () => {
  return document.querySelectorAll('.layout-builder.active .lb-plus-section');
};

// Scroll up and down when items are dragged to the top and bottom of the page.
jQuery(document).ready(() => {
  jQuery().dndPageScroll({
    delay: 10,
    height: 50,
  });
});

// Clicks the configure link on a section.
jQuery.fn.LBPlusConfigureSection = (uuid) => {
  jQuery('#' + uuid).find('.configure-link').click();
};
