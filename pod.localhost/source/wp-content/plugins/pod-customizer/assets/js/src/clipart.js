/**
 * POD Customizer: Clipart Layer & Clipart Modal Management
 */

import { state, elements, config, fabric, PREVIEW_SIZE } from './state.js';
import { escapeHtml, getLayersByType, highlightActiveLayerItem, bringDecorationsToFront, t } from './utils.js';
import { syncStateToForm } from './serializer.js';

export function renderClipartGrid() {
  if (!elements.clipartGrid || !config.cliparts) return;
  elements.clipartGrid.innerHTML = '';

  config.cliparts.forEach((c) => {
    const card = document.createElement('div');
    card.className = 'pod-clipart-card';
    card.dataset.id = c.id;
    card.innerHTML = `
      <img src="${c.url}" alt="${c.name}" class="pod-clipart-thumb" />
      <span class="pod-clipart-title">${c.name}</span>
    `;
    card.addEventListener('click', () => onSelectClipartFromModal(c));
    elements.clipartGrid.appendChild(card);
  });
}

export function onSelectClipartFromModal(c) {
  if (state.editingClipartObj) {
    replaceClipartLayer(state.editingClipartObj, c);
  } else {
    addClipartLayer(c);
  }
  closeClipartModal();
}

export function openClipartModal(targetObj = null) {
  state.editingClipartObj = targetObj;
  if (elements.clipartModalTitle) {
    elements.clipartModalTitle.textContent = targetObj ? t('modalChangeClipart', 'Change Clipart Graphic') : t('modalChooseClipart', 'Choose Clipart Graphic');
  }
  if (elements.clipartModal) {
    elements.clipartModal.style.display = 'flex';
  }
}

export function closeClipartModal() {
  state.editingClipartObj = null;
  if (elements.clipartModal) {
    elements.clipartModal.style.display = 'none';
  }
}

export function addClipartLayer(clipartItem) {
  if (!fabric || !state.canvas) return;

  fabric.Image.fromURL(
    clipartItem.url,
    function (img) {
      if (!img) return;

      const count = getLayersByType('clipart').length;
      const offset = (count % 4) * 20;

      img.set({
        podType: 'clipart',
        podId: 'clipart_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
        clipartName: clipartItem.name,
        clipartUrl: clipartItem.url,
        originX: 'center',
        originY: 'center',
        left: PREVIEW_SIZE / 2 + offset,
        top: PREVIEW_SIZE / 2 - 50 + offset,
        selectable: true,
        printable: true,
        cornerColor: '#4f46e5',
        cornerStrokeColor: '#ffffff',
        cornerSize: 9,
        transparentCorners: false,
      });

      img.scaleToWidth(150);
      state.canvas.add(img);
      bringDecorationsToFront();
      state.canvas.setActiveObject(img);
      state.canvas.renderAll();

      renderClipartList();
      syncStateToForm();
    },
    { crossOrigin: 'anonymous' }
  );
}

export function replaceClipartLayer(targetObj, clipartItem) {
  if (!fabric) return;

  fabric.Image.fromURL(
    clipartItem.url,
    function (newImg) {
      if (!newImg) return;
      targetObj.setElement(newImg.getElement());
      targetObj.clipartName = clipartItem.name;
      targetObj.clipartUrl = clipartItem.url;
      if (state.canvas) state.canvas.renderAll();
      renderClipartList();
      syncStateToForm();
    },
    { crossOrigin: 'anonymous' }
  );
}

export function renderClipartList() {
  if (!elements.clipartList) return;
  elements.clipartList.innerHTML = '';

  const clipartLayers = getLayersByType('clipart');
  if (clipartLayers.length === 0) {
    elements.clipartList.innerHTML = `
      <div class="pod-empty-state">
        <span>${t('emptyClipartList', 'No clipart graphics on canvas. Click "Add Clipart" to select one.')}</span>
      </div>
    `;
    return;
  }

  const activeObj = state.canvas ? state.canvas.getActiveObject() : null;

  clipartLayers.forEach((obj) => {
    const item = document.createElement('div');
    item.className = 'pod-layer-item' + (activeObj === obj ? ' active' : '');
    item.dataset.podId = obj.podId;

    item.innerHTML = `
      <div class="pod-layer-info">
        <img src="${obj.clipartUrl}" class="pod-layer-thumb" alt="Clipart" />
        <div class="pod-layer-texts">
          <span class="pod-layer-title">${escapeHtml(obj.clipartName || 'Clipart')}</span>
          <span class="pod-layer-subtitle">${Math.round(obj.getScaledWidth())} × ${Math.round(obj.getScaledHeight())} px</span>
        </div>
      </div>
      <div class="pod-layer-actions">
        <button type="button" class="pod-btn-edit" title="${escapeHtml(t('titleChangeClipart', 'Change clipart'))}">✏️ ${escapeHtml(t('btnChange', 'Change'))}</button>
        <button type="button" class="pod-btn-delete" title="${escapeHtml(t('titleDeleteClipart', 'Delete clipart'))}">🗑️ ${escapeHtml(t('btnDelete', 'Delete'))}</button>
      </div>
    `;

    // Click item to select & edit/change
    item.addEventListener('click', (e) => {
      if (e.target.closest('button')) return;
      if (state.canvas) {
        state.canvas.setActiveObject(obj);
        state.canvas.renderAll();
      }
      highlightActiveLayerItem(obj);
      openClipartModal(obj);
    });

    // Edit/Change Clipart
    item.querySelector('.pod-btn-edit').addEventListener('click', (e) => {
      e.stopPropagation();
      openClipartModal(obj);
    });

    // Delete Clipart
    item.querySelector('.pod-btn-delete').addEventListener('click', (e) => {
      e.stopPropagation();
      if (state.canvas) {
        state.canvas.remove(obj);
        state.canvas.renderAll();
      }
      renderClipartList();
      syncStateToForm();
    });

    elements.clipartList.appendChild(item);
  });
}

export function initClipartEvents() {
  if (elements.btnAddClipart) {
    elements.btnAddClipart.addEventListener('click', () => openClipartModal(null));
  }
  if (elements.clipartModalBtnClose) {
    elements.clipartModalBtnClose.addEventListener('click', closeClipartModal);
  }
  if (elements.clipartModal) {
    elements.clipartModal.addEventListener('click', (e) => {
      if (e.target === elements.clipartModal) closeClipartModal();
    });
  }
}
