/**
 * POD Customizer: Photo Layer & Upload Modal Management
 */

import { state, elements, config, fabric, PREVIEW_SIZE } from './state.js';
import { escapeHtml, getLayersByType, highlightActiveLayerItem, bringDecorationsToFront, t } from './utils.js';
import { syncStateToForm } from './serializer.js';

export function openPhotoModal(targetObj = null) {
  state.editingPhotoObj = targetObj;
  if (elements.photoModalTitle) {
    elements.photoModalTitle.textContent = targetObj ? t('modalChangePhoto', 'Change Uploaded Photo') : t('modalUploadPhoto', 'Upload Personal Photo');
  }
  if (elements.photoModal) {
    elements.photoModal.style.display = 'flex';
  }
}

export function closePhotoModal() {
  state.editingPhotoObj = null;
  if (elements.photoModal) {
    elements.photoModal.style.display = 'none';
  }
}

export function handlePhotoUploadFiles(files) {
  if (!files || files.length === 0) return;
  if (state.editingPhotoObj) {
    replacePhotoLayer(state.editingPhotoObj, files[0]);
  } else {
    files.forEach((f) => addPhotoLayer(f));
  }
  closePhotoModal();
}

export function addPhotoLayer(file) {
  if (!file.type.match(/^image\/(png|jpeg|webp)$/)) {
    alert(t('invalidPhoto', 'Please select a valid image file (PNG, JPG, WebP)'));
    return;
  }

  const reader = new FileReader();
  reader.onload = function (f) {
    const dataUrl = f.target.result;
    fabric.Image.fromURL(dataUrl, function (img) {
      if (!img || !state.canvas) return;

      const count = getLayersByType('photo').length;
      const offset = (count % 3) * 25;

      img.set({
        podType: 'photo',
        podId: 'photo_' + Date.now() + '_' + Math.floor(Math.random() * 1000),
        photoName: file.name,
        originX: 'center',
        originY: 'center',
        left: PREVIEW_SIZE / 2 + offset,
        top: PREVIEW_SIZE / 2 - 30 + offset,
        selectable: true,
        printable: true,
        cornerColor: '#06b6d4',
        cornerStrokeColor: '#ffffff',
        cornerSize: 9,
        transparentCorners: false,
      });

      img.scaleToWidth(160);
      state.canvas.add(img);
      bringDecorationsToFront();
      state.canvas.setActiveObject(img);
      state.canvas.renderAll();

      renderPhotoList();
      syncStateToForm();
    });
  };
  reader.readAsDataURL(file);
}

export function replacePhotoLayer(targetObj, file) {
  if (!file.type.match(/^image\/(png|jpeg|jpg|webp)$/)) {
    alert(t('invalidPhoto', 'Please select a valid image file (PNG, JPG, WebP)'));
    return;
  }

  const reader = new FileReader();
  reader.onload = function (f) {
    const dataUrl = f.target.result;
    fabric.Image.fromURL(dataUrl, function (newImg) {
      if (!newImg) return;
      targetObj.setElement(newImg.getElement());
      targetObj.photoName = file.name;
      if (state.canvas) state.canvas.renderAll();
      renderPhotoList();
      syncStateToForm();
    });
  };
  reader.readAsDataURL(file);
}

export function renderPhotoList() {
  if (!elements.photoList) return;
  elements.photoList.innerHTML = '';

  const photoLayers = getLayersByType('photo');
  if (photoLayers.length === 0) {
    elements.photoList.innerHTML = `
      <div class="pod-empty-state">
        <span>${t('emptyPhotoList', 'No uploaded photos on canvas. Click "Add Photo" to upload.')}</span>
      </div>
    `;
    return;
  }

  const activeObj = state.canvas ? state.canvas.getActiveObject() : null;

  photoLayers.forEach((obj) => {
    const item = document.createElement('div');
    item.className = 'pod-layer-item' + (activeObj === obj ? ' active' : '');
    item.dataset.podId = obj.podId;

    const thumbSrc = obj.toDataURL ? obj.toDataURL({ format: 'jpeg', quality: 0.3, multiplier: 0.2 }) : '';

    item.innerHTML = `
      <div class="pod-layer-info">
        <img src="${thumbSrc}" class="pod-layer-thumb" alt="Photo" />
        <div class="pod-layer-texts">
          <span class="pod-layer-title">${escapeHtml(obj.photoName || t('uploadedPhoto', 'Uploaded Photo'))}</span>
          <span class="pod-layer-subtitle">${Math.round(obj.getScaledWidth())} × ${Math.round(obj.getScaledHeight())} px</span>
        </div>
      </div>
      <div class="pod-layer-actions">
        <button type="button" class="pod-btn-edit" title="${escapeHtml(t('titleChangePhoto', 'Change photo'))}">✏️ ${escapeHtml(t('btnChange', 'Change'))}</button>
        <button type="button" class="pod-btn-delete" title="${escapeHtml(t('titleDeletePhoto', 'Delete photo'))}">🗑️ ${escapeHtml(t('btnDelete', 'Delete'))}</button>
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
      openPhotoModal(obj);
    });

    // Edit/Change Photo
    item.querySelector('.pod-btn-edit').addEventListener('click', (e) => {
      e.stopPropagation();
      openPhotoModal(obj);
    });

    // Delete Photo
    item.querySelector('.pod-btn-delete').addEventListener('click', (e) => {
      e.stopPropagation();
      if (state.canvas) {
        state.canvas.remove(obj);
        state.canvas.renderAll();
      }
      renderPhotoList();
      syncStateToForm();
    });

    elements.photoList.appendChild(item);
  });
}

export function initPhotoEvents() {
  if (elements.btnAddPhoto) {
    elements.btnAddPhoto.addEventListener('click', () => openPhotoModal(null));
  }
  if (elements.photoModalBtnClose) {
    elements.photoModalBtnClose.addEventListener('click', closePhotoModal);
  }
  if (elements.photoModal) {
    elements.photoModal.addEventListener('click', (e) => {
      if (e.target === elements.photoModal) closePhotoModal();
    });
  }

  // Drag & drop and file input
  if (elements.dropzone && elements.fileInput) {
    elements.dropzone.addEventListener('click', () => elements.fileInput.click());

    elements.fileInput.addEventListener('change', (e) => {
      const files = Array.from(e.target.files || []);
      handlePhotoUploadFiles(files);
      elements.fileInput.value = '';
    });

    elements.dropzone.addEventListener('dragover', (e) => {
      e.preventDefault();
      elements.dropzone.style.borderColor = '#4f46e5';
    });

    elements.dropzone.addEventListener('dragleave', () => {
      elements.dropzone.style.borderColor = '#cbd5e1';
    });

    elements.dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      elements.dropzone.style.borderColor = '#cbd5e1';
      const files = Array.from(e.dataTransfer.files || []);
      handlePhotoUploadFiles(files);
    });
  }
}
