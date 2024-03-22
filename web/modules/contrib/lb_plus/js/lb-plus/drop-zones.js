import * as section from './section.js';

/**
 * Drag start.
 *
 * This is a helper method for dragging actions that blocks and sections have in
 * common.
 *
 * @param e
 *   The drag start event.
 * @param type
 *   The type of dragging action e.g. new_block, move_block, new_section, move_section.
 */
export const dragStart = (e, type) => {
  // Track what element is being dragged.
  Drupal.LBPlus.draggedItem = e.target;
  // Store the element ID for placing once the element has been dropped.
  e.dataTransfer.setData('text/json', JSON.stringify({
    id: e.target.dataset.blockUuid ?? e.target.id,
    type: type,
  }));
  e.dataTransfer.setData(type, 'true');
}

/**
 * Drag end.
 *
 * This is a helper method for dragging actions that both blocks and sections
 * have in common.
 *
 * @param e
 *   The drag end event.
 */
export const dragEnd = (e) => {
  clearDropZones();

  // Stop adding region drop zones when the mouse hovers a region.
  document.querySelectorAll('.js-layout-builder-region').forEach(section => {
    section.removeEventListener('dragenter', Drupal.LBPlus.DropZonesEnterRegionListenerInstance);
  });

  // Stop adding section drop zones when the mouse hovers a section.
  document.querySelectorAll('.lb-plus-section, #lb-plus-blank-page').forEach(section => {
    section.removeEventListener('dragenter', Drupal.LBPlus.DropZonesEnterSectionListenerInstance);
  });
}

/**
 * Drop zone listener.
 *
 * Fires a LB+ drop zone event when an item is placed in a drop zone. See
 * blocks.js and section.js for how this is used.
 *
 * @param dropZone
 *   The drop zone element.
 *
 * @returns {*}
 *   The drop zone element.
 */
function dropZoneListener(dropZone) {
  dropZone.ondragover = e => {
    e.preventDefault();
  };
  dropZone.ondragenter = e => {
    e.target.classList.add('drop-zone-hover');
  };
  dropZone.ondragleave = e => {
    e.target.classList.remove('drop-zone-hover');
  };
  dropZone.ondrop = e => {
    e.preventDefault();
    const event = new CustomEvent('element-dropped', {
      bubbles: true,
      cancelable: true,
      detail: {
        dropEvent: e,
        ...JSON.parse(e.dataTransfer.getData('text/json')),
      },
    });

    document.dispatchEvent(event);
  };
  return dropZone;
}

/**
 * Drop zones enter section.
 *
 * Once a block or section has started to be dragged we reveal section drop
 * zones when the mouse enters a section. This listener is attached by
 * addSectionDropZones and removed by dragEnd.
 *
 * @param message
 *   The text of the drop zone e.g. Move here, Add new section.
 * @param e
 *   The drag enter event.
 */
function dropZonesEnterSection (message, e) {
  const closestSectionDropZoneWrapper = e.target.closest('#section-drop-zone-wrapper');
  const layoutBuilder = e.target.closest('.layout-builder');
  if (
    // This event fires when crossing div boundaries within the same section.
    // Only add new drop zones around a section when we have traveled outside
    // the section drop zone wrapper.
    !closestSectionDropZoneWrapper ||
    // Add new drop zones when we are within the boundary of the
    // closestSectionDropZoneWrapper, but are now over an actively edited nested
    // layout block.
    (closestSectionDropZoneWrapper && !layoutBuilder.contains(closestSectionDropZoneWrapper))
  ) {
    // Remove any existing section drop zones now that we are over a new section.
    clearDropZones();
    const section = e.target.closest('.lb-plus-section, #lb-plus-blank-page');
    const draggedItem = Drupal.LBPlus.draggedItem;

    if (section === draggedItem) {
      // Don't add drop zones around the section that is being dragged since
      // it would be placed right where it was.
      return;
    }

    // Add a wrapper around this section. This allows us to detect if we've left
    // this section and are hovered over a new section. Especially in a nested
    // layout where you are within more than one section at a time.
    let sectionDropZoneWrapper = document.createElement('div');
    sectionDropZoneWrapper.id = 'section-drop-zone-wrapper';
    section.parentNode.insertBefore(sectionDropZoneWrapper, section);
    sectionDropZoneWrapper.appendChild(section);

    let sectionDropZone = createDropZone(message, 'section').cloneNode(true);
    if (sectionDropZoneWrapper.previousElementSibling !== draggedItem) {
      // Add a drop zone before the section.
      sectionDropZone.setAttribute('section-id', section.id);
      sectionDropZone.setAttribute('preceding-section-id', section.id);
      section.parentNode.insertBefore(dropZoneListener(sectionDropZone), section);
    }

    if (section.id === 'lb-plus-blank-page') {
      // Hide the blank page message.
      section.style.visibility = 'hidden';
    } else if (sectionDropZoneWrapper.nextElementSibling !== draggedItem) {
      // Add a drop zone after the section.
      sectionDropZone = createDropZone(message, 'section').cloneNode(true);
      sectionDropZone.setAttribute('section-id', section.id);
      const precedingSectionId = sectionDropZoneWrapper.nextElementSibling?.classList.contains('lb-plus-section') ? sectionDropZoneWrapper.nextElementSibling.id : 'last'
      sectionDropZone.setAttribute('preceding-section-id', precedingSectionId);
      section.parentNode.insertBefore(dropZoneListener(sectionDropZone), section.nextSibling);
    }
  }
}

/**
 * Add section drop zones.
 *
 * Enables dropZonesEnterSection when an item is being dragged.
 *
 * @param message
 *   The text of the drop zone e.g. Move here, Add new section.
 * @param active
 *   Whether section drop zones should only be added to the active layout builder.
 */
export const addSectionDropZones = (message, active = false) => {
  let sectionSelector = '.lb-plus-section, #lb-plus-blank-page';
  if (active) {
    sectionSelector = '.layout-builder.active .lb-plus-section, #lb-plus-blank-page';
  }
  const sections = document.querySelectorAll(sectionSelector);
  if (sections.length > 0) {
    // When you bind a parameter it makes a new instance of the function. Keep
    // track of it, so we can un-attach the event listener when we are done with it.
    Drupal.LBPlus.DropZonesEnterSectionListenerInstance = dropZonesEnterSection.bind(null, message);
    // Add drop zones around a section when the mouse hovers over a section.
    sections.forEach(section => {
      section.addEventListener('dragenter', Drupal.LBPlus.DropZonesEnterSectionListenerInstance);
    });
  }
}

/**
 * Drop zones enter region.
 *
 * Once a block or section has started to be dragged we reveal region drop
 * zones when the mouse enters the column of a layout. This listener is attached
 * by addRegionDropZones and removed by dragEnd.
 *
 * @param message
 *   The text of the drop zone e.g. Move here, Add new section.
 * @param e
 *   The drag enter event.
 */
function dropZonesEnterRegion (message, e) {
  const column = e.target.closest('.js-layout-builder-region');
  // This event fires when we cross div boundaries within the same region as well
  // as crossing into different regions. Only add region drop zones if this
  // region doesn't already have region drop zones.
  if (!column.querySelector('[drop-zone-type="region"]')) {
    // We are in a nested layout. Let's not place a drop zone in the parent region.
    if (column.querySelector('#lb-plus-blank-page')?.style.visibility === 'hidden') {
      return;
    }
    // Clear the drop zones from the last region since we are adding drop zones
    // to a new region.
    clearDropZones('region');
    let regionDropZone = createDropZone(message, 'region').cloneNode(true);
    regionDropZone.setAttribute('preceding-block-uuid', null);

    let draggedItem = Drupal.LBPlus.draggedItem;

    // Don't add a drop zone before the element being dragged since if it was
    // placed there it would not have moved.
    if (draggedItem !== column.firstElementChild) {
      // Add a drop zone at the beginning of the region.
      column.insertBefore(dropZoneListener(regionDropZone), column.firstElementChild);
    }
    // Add a dropzone after each block.
    for (let block of column.children) {
      if (
        // Ensure this is a block. It is tempting to call
        // column.querySelectorAll('.js-layout-builder-block') here, but we need
        // to exclude blocks within this column that are in nested layouts, so
        // lets loop through the child elements and check for blocks.
        block.classList.contains('js-layout-builder-block') &&
        // Don't add a superfluous drop zone next to the block being moved since
        // it would place it where it already is.
        draggedItem !== block.nextElementSibling &&
        draggedItem !== block
      ) {
        regionDropZone = createDropZone(message, 'region').cloneNode(true);
        regionDropZone.setAttribute('preceding-block-uuid', block.dataset.blockUuid);
        column.insertBefore(dropZoneListener(regionDropZone), block.nextSibling);
      }
    }
  }
}

/**
 * Add region drop zones.
 *
 * Enables dropZonesEnterRegion when an item is being dragged.
 *
 * @param message
 *   The text of the drop zone e.g. Add to region.
 * @param active
 *   Whether region drop zones should only be added to the active layout builder.
 */
export const addRegionDropZones = (message, active = false) => {
  let regionSelector = '.layout-builder .js-layout-builder-region';
  if (active) {
    regionSelector = '.layout-builder.active .js-layout-builder-region';
  }
  // When you bind a parameter it makes a new instance of the function. Keep
  // track of it, so we can un-attach the event listener when we are done with it.
  Drupal.LBPlus.DropZonesEnterRegionListenerInstance = dropZonesEnterRegion.bind(null, message);
  document.querySelectorAll(regionSelector).forEach(column => {
    // Add drop zones around blocks in a region when the mouse hovers over a region.
    column.addEventListener('dragenter', Drupal.LBPlus.DropZonesEnterRegionListenerInstance);
  });
}

/**
 * Create drop zone.
 *
 * @param message
 *   The drop zone text.
 * @param type
 *   The type of drop zone. section or region
 *
 * @returns {HTMLDivElement}
 *   A new drop zone element.
 */
function createDropZone(message, type) {
  const dropZone = document.createElement('div');
  dropZone.classList.add('drop-zone', type)
  dropZone.setAttribute('drop-zone-type', type);
  dropZone.innerText = message;

  return dropZone;
}

/**
 * Clear drop zones.
 */
export const clearDropZones = (type = null) => {
  if (
    type === null ||
    (type === 'section')
  ) {
    // Remove section drop zone wrappers.
    document.querySelectorAll('#section-drop-zone-wrapper').forEach(sectionDropZoneWrapper => {
      const section = sectionDropZoneWrapper.querySelector('.lb-plus-section, #lb-plus-blank-page');
      if (section) {
        sectionDropZoneWrapper.parentNode.insertBefore(section, sectionDropZoneWrapper);
        sectionDropZoneWrapper.remove();
      }
    });
    // Reveal the blank page instructions if a block wasn't placed.
    const blankPage = document.getElementById('lb-plus-blank-page');
    if (blankPage) {
      blankPage.style.visibility = 'visible';
    }
  }
  // Remove drop zone elements.
  document.querySelectorAll('.drop-zone').forEach(dropZone => {
    if (
      type === null ||
      (type === 'section' && dropZone.getAttribute('drop-zone-type') === 'section') ||
      (type === 'region' && dropZone.getAttribute('drop-zone-type') === 'region')
    ) {
      dropZone.remove();
    }
  });
}

/**
 * Is element visible.
 *
 * @param element
 *   The element to check.
 *
 * @returns {boolean}
 *   Whether the element is visible in the browsers view port.
 */
function isElementVisible(element) {
  const rect = element.getBoundingClientRect();
  return (
    (
      // Top of div is below the viewport
      rect.top >= 0 &&
      rect.top < (window.innerHeight || document.body.clientHeight)
    ) ||
    (
      // Bottom of div is above the bottom of the viewport.
      rect.bottom <= (window.innerHeight || document.body.clientHeight) &&
      // Bottom of div is not above the screen.
      rect.bottom >= 0
    )
  );
}
