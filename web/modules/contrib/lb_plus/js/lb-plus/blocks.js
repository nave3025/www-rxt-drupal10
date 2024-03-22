export default function ($ = jQuery, Drupal, once, dropZones) {

  // Click the open sidebar link for the user on blank pages.
  Drupal.behaviors.LBPlusClickAddBlock = {
    attach: (context, settings) => {
      once('clickAddBlock', '.blank-page', context).forEach(addBlockLink => {
        addBlockLink.click();
      });
    },
  };

  /**
   * Drag blocks.
   *
   * Drag to move an existing block to a new location or place a new block.
   */
  Drupal.behaviors.LBPlusDragBlocks = {
    attach: (context, settings) => {
      // Drag a new block from the sidebar and place it on the page.
      once('LBPlusPlaceNewBlock', '.tabbed-content .draggable-block', context).forEach(draggableBlock => {
        Drupal.behaviors.LBPlusDragBlocks.initDragging(draggableBlock, 'new_block', true);
      });
      // Drag an existing block to a new location on the page.
      once('LBPlusMoveExistingBlock', '.layout-builder.active .layout-builder-block', context).forEach(draggableBlock => {
        Drupal.behaviors.LBPlusDragBlocks.initDragging(draggableBlock, 'move_block', false);
        // Make child images not-draggable.
        draggableBlock.querySelectorAll('img').forEach(image => {
          image.setAttribute('draggable', 'false');
        });
      });
    },
    initDragging: (draggableBlock, type, activeLayoutBuilder) => {
      draggableBlock.setAttribute('draggable', 'true');
      draggableBlock.ondragstart = e => {
        dropZones.addSectionDropZones(Drupal.t('Place block in a new section'), activeLayoutBuilder);
        dropZones.addRegionDropZones(Drupal.t('Place block here'), activeLayoutBuilder);
        dropZones.dragStart(e, type);
      }
      draggableBlock.ondragend = e => dropZones.dragEnd(e);
    },
  };

}

/**
 * An existing block was moved to another location on the page.
 */
document.addEventListener('element-dropped', e => {
  if (e.detail.type !== 'move_block') {
    return;
  }
  // Remove the blank page instructions if the block was placed.
  const blankPage = document.getElementById('lb-plus-blank-page');
  if (blankPage) {
    blankPage.remove();
  }

  const draggedBlock = document.querySelector('[data-block-uuid="' + e.detail.id + '"]');
  const dropZone = e.detail.dropEvent.target;
  const type = dropZone.getAttribute('drop-zone-type');
  let destination = {
    type: type,
    block_uuid: e.detail.id,
    delta_from: draggedBlock.closest('[data-layout-delta]').dataset.layoutDelta,
  };
  if (type === 'region') {
    destination = {
      ...destination,
      section: dropZone.closest('.lb-plus-section').id,
      preceding_block_uuid: dropZone.getAttribute('preceding-block-uuid'),
      region: dropZone.closest('.layout__region').getAttribute('region'),
      delta_to: dropZone.closest('[data-layout-delta]').dataset.layoutDelta,
      region_to: dropZone.closest('.js-layout-builder-region').getAttribute('region'),
    };
  }
  if (type === 'section') {
    // Pass either the section ID to put this block in front of, or the string
    // "last" to put it at the end.
    destination.section = dropZone.getAttribute('preceding-section-id');
  }
  // Check if the block is coming from a different section storage.
  const nestedStoragePathFrom = draggedBlock.closest('[data-nested-storage-path]')?.dataset.nestedStoragePath;
  if (typeof nestedStoragePathFrom !== 'undefined') {
    destination.nested_storage_path_from = nestedStoragePathFrom;
  }
  const nestedStoragePathTo = type === 'section' ? dropZone.closest('#section-drop-zone-wrapper').querySelector('[data-nested-storage-path]')?.dataset.nestedStoragePath : dropZone.closest('[data-nested-storage-path]')?.dataset.nestedStoragePath;
  if (typeof nestedStoragePathTo !== 'undefined') {
    destination.nested_storage_path_to = nestedStoragePathTo;
  }

  // Place the block.
  let ajaxConfig = {
    url: draggedBlock.closest('[data-layout-update-url]').dataset.layoutUpdateUrl,
    type: 'POST',
    dataType: 'text',
    progress: {
      type: 'fullscreen',
      message: Drupal.t('Saving block placement...')
    },
    submit: {
      place_block: {
        plugin_id: draggedBlock.id,
        destination: destination,
      },
    },
    error: (error, path) => {
      Drupal.LBPlus.ajaxError(Drupal.t('Unable to place the block.'), error);
    },
  };

  let ajax = Drupal.ajax(ajaxConfig);
  ajax.execute();
});

/**
 * A new block was placed on the page.
 */
document.addEventListener('element-dropped', e => {
  if (e.detail.type !== 'new_block') {
    return;
  }
  // Remove the blank page instructions if the block was placed.
  const blankPage = document.getElementById('lb-plus-blank-page');
  if (blankPage) {
    blankPage.remove();
  }

  const draggedBlock = document.getElementById(e.detail.id);
  const dropZone = e.detail.dropEvent.target;
  const type = dropZone.getAttribute('drop-zone-type');
  let destination = {
    type: type,
  };

  if (type === 'region') {
    destination = {
      ...destination,
      section: dropZone.closest('.lb-plus-section').id,
      preceding_block_uuid: dropZone.getAttribute('preceding-block-uuid'),
      region: dropZone.closest('.layout__region').getAttribute('region'),
    };
  }
  if (type === 'section') {
    // Pass either the section ID to put this block in front of, or the string
    // "last" to put it at the end.
    destination.section = dropZone.getAttribute('preceding-section-id');
  }

  // Place the block.
  let ajaxConfig = {
    url: '/lb-plus/place-block/' + drupalSettings['LB+'].sectionStorageType + '/' + drupalSettings['LB+'].sectionStorage,
    type: 'POST',
    dataType: 'text',
    progress: {
      type: 'fullscreen',
      message: Drupal.t('Saving block placement...')
    },
    submit: {
      place_block: {
        plugin_id: draggedBlock.id,
        destination: destination,
      },
    },
    error: (error, path) => {
      Drupal.LBPlus.ajaxError(Drupal.t('Unable to place the block.'), error);
    },
  };

  if (drupalSettings['LB+'].isLayoutBlock) {
    ajaxConfig.url = ajaxConfig.url + '/' + drupalSettings['LB+'].nestedStoragePath;
  }

  let ajax = Drupal.ajax(ajaxConfig);
  ajax.execute();
});
