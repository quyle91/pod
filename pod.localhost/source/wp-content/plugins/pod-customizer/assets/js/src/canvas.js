/**
 * POD Customizer: Canvas Initialization & Interaction Orchestration
 */

import { state, elements, config, fabric, PREVIEW_SIZE } from './state.js';
import { highlightActiveLayerItem } from './utils.js';
import { syncStateToForm } from './serializer.js';
import { renderTextList, openTextModal } from './text.js';
import { renderClipartList, openClipartModal } from './clipart.js';
import { renderPhotoList, openPhotoModal } from './photo.js';
import { loadMockupImage } from './mockup.js';

export function renderAllLayerLists() {
  renderTextList();
  renderClipartList();
  renderPhotoList();
}

export function initCanvas() {
  if (!fabric) return;

  state.canvas = new fabric.Canvas('pod-live-canvas', {
    width: PREVIEW_SIZE,
    height: PREVIEW_SIZE,
    backgroundColor: '#f8fafc',
    selection: true,
    preserveObjectStacking: true,
  });

  // Handle user manipulation (drag, resize, rotate) on canvas
  state.canvas.on('object:modified', function () {
    syncStateToForm();
    renderAllLayerLists();
  });

  state.canvas.on('selection:created', onObjectSelected);
  state.canvas.on('selection:updated', onObjectSelected);
  state.canvas.on('selection:cleared', onSelectionCleared);

  // Double-click on canvas object opens edit modal
  state.canvas.on('mouse:dblclick', function (opt) {
    const target = opt.target;
    if (!target) return;
    if (target.podType === 'text') {
      openTextModal(target);
    } else if (target.podType === 'clipart') {
      openClipartModal(target);
    } else if (target.podType === 'photo') {
      openPhotoModal(target);
    }
  });
}

function onObjectSelected(e) {
  const selected = e.selected?.[0];
  if (!selected) return;
  highlightActiveLayerItem(selected);
}

function onSelectionCleared() {
  document.querySelectorAll('.pod-layer-item').forEach((item) => item.classList.remove('active'));
}

export function resetStudio() {
  if (!state.canvas) return;

  // Clear all printable layers
  const objects = state.canvas.getObjects().slice();
  objects.forEach((obj) => {
    if (obj.podType && obj.podType !== 'mockup') {
      state.canvas.remove(obj);
    }
  });

  if (state.currentMockup) {
    loadMockupImage(state.currentMockup.url);
  }

  renderAllLayerLists();
  syncStateToForm();
}
