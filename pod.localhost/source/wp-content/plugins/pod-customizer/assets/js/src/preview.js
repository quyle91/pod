/**
 * POD Customizer: Full Product Preview Modal
 * Generates crisp 2x resolution snapshot from Fabric Canvas and displays interactive popup.
 */

import { state, elements, config, isTemplateMode } from './state.js';

export function openPreviewModal() {
  if (!state.canvas || !elements.previewModal) return;

  // 1. Generate high-resolution render snapshot from canvas
  try {
    const dataUrl = state.canvas.toDataURL({
      format: 'png',
      multiplier: 2, // 1200 x 1200 high-res crisp render
      quality: 1,
    });

    if (elements.previewModalImg) {
      elements.previewModalImg.src = dataUrl;
    }
  } catch (err) {
    console.error('[POD Preview] Failed to export canvas image:', err);
  }

  // 2. Render summary of customized specs if in template mode
  if (elements.previewModalSpecs) {
    elements.previewModalSpecs.innerHTML = '';
    const tpl = config.template;

    if (isTemplateMode && tpl && Array.isArray(tpl.fields)) {
      tpl.fields.forEach((field) => {
        const val = state.templateValues ? state.templateValues[field.id] : field.default_value;
        if (val !== undefined && String(val).trim() !== '') {
          const pill = document.createElement('span');
          pill.className = 'pod-preview-spec-pill';
          let displayVal = String(val);
          if (field.type === 'repeater_counter') {
            displayVal = `${val} items`;
          } else if (field.type === 'preset_picker') {
            const opt = (field.options || []).find((o) => o.id === val);
            displayVal = opt ? (opt.label || opt.id) : val;
          }
          pill.innerHTML = `<strong>${field.label || field.id}:</strong> ${displayVal}`;
          elements.previewModalSpecs.appendChild(pill);
        }
      });
      elements.previewModalSpecs.style.display = elements.previewModalSpecs.children.length ? 'flex' : 'none';
    } else {
      elements.previewModalSpecs.style.display = 'none';
    }
  }

  // 3. Display modal
  elements.previewModal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

export function closePreviewModal() {
  if (!elements.previewModal) return;
  elements.previewModal.style.display = 'none';
  document.body.style.overflow = '';
}

export function initPreviewModal() {
  // Trigger buttons
  if (elements.btnPreview) {
    elements.btnPreview.addEventListener('click', (e) => {
      e.preventDefault();
      openPreviewModal();
    });
  }

  if (elements.btnQuickPreview) {
    elements.btnQuickPreview.addEventListener('click', (e) => {
      e.preventDefault();
      openPreviewModal();
    });
  }

  // Close triggers
  if (elements.previewModalBtnClose) {
    elements.previewModalBtnClose.addEventListener('click', closePreviewModal);
  }

  if (elements.previewModalBtnDone) {
    elements.previewModalBtnDone.addEventListener('click', closePreviewModal);
  }

  if (elements.previewModal) {
    elements.previewModal.addEventListener('click', (e) => {
      if (e.target === elements.previewModal) {
        closePreviewModal();
      }
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && elements.previewModal && elements.previewModal.style.display === 'flex') {
      closePreviewModal();
    }
  });
}
