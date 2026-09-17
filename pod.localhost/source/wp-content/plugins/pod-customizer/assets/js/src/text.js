/**
 * POD Customizer: Text Layer & Text Modal Management
 */

import { state, elements, fabric, PREVIEW_SIZE } from './state.js';
import { escapeHtml, getLayersByType, highlightActiveLayerItem, bringDecorationsToFront, t } from './utils.js';
import { syncStateToForm } from './serializer.js';

export function addTextLayer(initialText, openModalNow = true) {
  if (!fabric || !state.canvas) return null;

  const textString = initialText || t('defaultText', 'Your Custom Text');
  const count = getLayersByType('text').length;
  const offset = count * 35;

  const textObj = new fabric.Text(textString, {
    podType: 'text',
    podId: 'text_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
    originX: 'center',
    originY: 'center',
    left: PREVIEW_SIZE / 2,
    top: PREVIEW_SIZE / 2 + 80 + offset,
    fontFamily: 'Roboto',
    fontSize: 44,
    fill: '#111827',
    textAlign: 'center',
    selectable: true,
    printable: true,
    cornerColor: '#4f46e5',
    cornerStrokeColor: '#ffffff',
    cornerSize: 9,
    transparentCorners: false,
    textBaseline: 'alphabetic',
  });

  state.canvas.add(textObj);
  bringDecorationsToFront();
  state.canvas.setActiveObject(textObj);
  state.canvas.renderAll();

  renderTextList();
  syncStateToForm();

  if (openModalNow) {
    openTextModal(textObj);
  }

  return textObj;
}

export function renderTextList() {
  if (!elements.textList) return;
  elements.textList.innerHTML = '';

  const textLayers = getLayersByType('text');
  if (textLayers.length === 0) {
    elements.textList.innerHTML = `
      <div class="pod-empty-state">
        <span>${t('emptyTextList', 'No custom text lines added yet. Click "Add Text" to create one.')}</span>
      </div>
    `;
    return;
  }

  const activeObj = state.canvas ? state.canvas.getActiveObject() : null;

  textLayers.forEach((obj) => {
    const item = document.createElement('div');
    item.className = 'pod-layer-item' + (activeObj === obj ? ' active' : '');
    item.dataset.podId = obj.podId;

    item.innerHTML = `
      <div class="pod-layer-info">
        <span class="pod-layer-color-dot" style="background-color: ${obj.fill};"></span>
        <div class="pod-layer-texts">
          <span class="pod-layer-title">${escapeHtml(obj.text || '(Empty)')}</span>
          <span class="pod-layer-subtitle">${escapeHtml(obj.fontFamily)} • ${Math.round(obj.fontSize * (obj.scaleY || 1))}px</span>
        </div>
      </div>
      <div class="pod-layer-actions">
        <button type="button" class="pod-btn-edit" title="${escapeHtml(t('titleEditText', 'Edit text'))}">✏️ ${escapeHtml(t('btnEdit', 'Edit'))}</button>
        <button type="button" class="pod-btn-delete" title="${escapeHtml(t('titleDeleteText', 'Delete text'))}">🗑️ ${escapeHtml(t('btnDelete', 'Delete'))}</button>
      </div>
    `;

    // Click item to select & open modal
    item.addEventListener('click', (e) => {
      if (e.target.closest('button')) return;
      if (state.canvas) {
        state.canvas.setActiveObject(obj);
        state.canvas.renderAll();
      }
      highlightActiveLayerItem(obj);
      openTextModal(obj);
    });

    // Edit Button
    item.querySelector('.pod-btn-edit').addEventListener('click', (e) => {
      e.stopPropagation();
      openTextModal(obj);
    });

    // Delete Button
    item.querySelector('.pod-btn-delete').addEventListener('click', (e) => {
      e.stopPropagation();
      if (state.canvas) {
        state.canvas.remove(obj);
        state.canvas.renderAll();
      }
      renderTextList();
      syncStateToForm();
    });

    elements.textList.appendChild(item);
  });
}

export function openTextModal(textObj) {
  if (!elements.textModal || !textObj) return;

  state.editingTextObj = textObj;

  if (elements.textModalTitle) {
    elements.textModalTitle.textContent = t('modalEditText', 'Edit Text');
  }
  if (state.canvas) {
    state.canvas.setActiveObject(textObj);
    state.canvas.renderAll();
  }

  if (elements.textModalTitle) {
    elements.textModalTitle.textContent = 'Edit Text';
  }

  if (elements.modalInputText) {
    elements.modalInputText.value = textObj.text || '';
  }

  if (elements.modalSelectFont) {
    elements.modalSelectFont.value = textObj.fontFamily || 'Roboto';
  }

  const effectiveSize = Math.round((textObj.fontSize || 44) * (textObj.scaleY || 1));
  if (elements.modalRangeSize) {
    elements.modalRangeSize.value = effectiveSize;
  }
  if (elements.modalSizeVal) {
    elements.modalSizeVal.textContent = effectiveSize + 'px';
  }

  updateModalColorSelection(textObj.fill || '#111827');

  elements.textModal.style.display = 'flex';
  if (elements.modalInputText) {
    elements.modalInputText.focus();
  }
}

export function updateModalColorSelection(color) {
  if (!elements.modalColorsContainer) return;

  const btns = elements.modalColorsContainer.querySelectorAll('.pod-color-btn');
  btns.forEach((btn) => {
    if (btn.dataset.color.toLowerCase() === color.toLowerCase()) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });

  if (elements.modalCustomColor) {
    elements.modalCustomColor.value = color.startsWith('#') && color.length === 7 ? color : '#111827';
  }
}

export function closeTextModal() {
  if (!elements.textModal) return;
  elements.textModal.style.display = 'none';
  state.editingTextObj = null;
  renderTextList();
  syncStateToForm();
}

export function initTextEvents() {
  if (elements.btnAddText) {
    elements.btnAddText.addEventListener('click', () => {
      addTextLayer('Your Custom Text', true);
    });
  }

  if (elements.textModalBtnClose) {
    elements.textModalBtnClose.addEventListener('click', closeTextModal);
  }
  if (elements.textModalBtnDone) {
    elements.textModalBtnDone.addEventListener('click', closeTextModal);
  }
  if (elements.textModal) {
    elements.textModal.addEventListener('click', (e) => {
      if (e.target === elements.textModal) closeTextModal();
    });
  }

  // Live text update inside Modal
  if (elements.modalInputText) {
    elements.modalInputText.addEventListener('input', (e) => {
      if (!state.editingTextObj) return;
      state.editingTextObj.set({ text: e.target.value });
      if (state.canvas) state.canvas.renderAll();
      renderTextList();
    });
  }

  if (elements.modalSelectFont) {
    elements.modalSelectFont.addEventListener('change', (e) => {
      if (!state.editingTextObj) return;
      state.editingTextObj.set({ fontFamily: e.target.value });
      if (state.canvas) state.canvas.renderAll();
      renderTextList();
    });
  }

  if (elements.modalRangeSize) {
    elements.modalRangeSize.addEventListener('input', (e) => {
      if (!state.editingTextObj) return;
      const size = parseInt(e.target.value, 10);
      state.editingTextObj.set({ fontSize: size, scaleX: 1, scaleY: 1 });
      if (elements.modalSizeVal) elements.modalSizeVal.textContent = size + 'px';
      if (state.canvas) state.canvas.renderAll();
      renderTextList();
    });
  }

  if (elements.modalColorsContainer) {
    const colorBtns = elements.modalColorsContainer.querySelectorAll('.pod-color-btn');
    colorBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        if (!state.editingTextObj) return;
        const color = btn.dataset.color;
        state.editingTextObj.set({ fill: color });
        updateModalColorSelection(color);
        if (state.canvas) state.canvas.renderAll();
        renderTextList();
      });
    });
  }

  if (elements.modalCustomColor) {
    elements.modalCustomColor.addEventListener('input', (e) => {
      if (!state.editingTextObj) return;
      const color = e.target.value;
      state.editingTextObj.set({ fill: color });
      updateModalColorSelection(color);
      if (state.canvas) state.canvas.renderAll();
      renderTextList();
    });
  }
}
