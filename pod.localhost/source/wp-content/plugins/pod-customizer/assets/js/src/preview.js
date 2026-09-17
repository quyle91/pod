/**
 * POD Customizer: Full Product Preview Modal
 * Generates crisp 2x resolution snapshot from Fabric Canvas and displays interactive popup.
 */

import { state, config, isTemplateMode } from './state.js';

export function openPreviewModal() {
  const modal = document.getElementById('pod-preview-modal');
  const img = document.getElementById('pod-preview-modal-img');
  const specs = document.getElementById('pod-preview-specs');

  if (!state.canvas || !modal) {
    console.warn('[POD Preview] Canvas or modal not available yet.');
    return;
  }

  // 1. Generate high-resolution render snapshot from canvas (1200 x 1200 px @ 2x)
  try {
    const dataUrl = state.canvas.toDataURL({
      format: 'png',
      multiplier: 2,
      quality: 1,
    });

    if (img) {
      img.src = dataUrl;
    }
  } catch (err) {
    console.error('[POD Preview] Failed to export canvas snapshot:', err);
  }

  // 2. Render summary of customized specs if in template mode (Pure English)
  if (specs) {
    specs.innerHTML = '';
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
          specs.appendChild(pill);
        }
      });
      specs.style.display = specs.children.length ? 'flex' : 'none';
    } else {
      specs.style.display = 'none';
    }
  }

  // 3. Display modal
  modal.style.display = 'flex';
  modal.classList.add('is-open');
  document.body.style.overflow = 'hidden';
}

export function closePreviewModal() {
  const modal = document.getElementById('pod-preview-modal');
  if (!modal) return;
  modal.style.display = 'none';
  modal.classList.remove('is-open');
  document.body.style.overflow = '';
}

// Global debug exposure
if (typeof window !== 'undefined') {
  window.podOpenPreview = openPreviewModal;
  window.podClosePreview = closePreviewModal;
}

export function initPreviewModal() {
  // Use robust document-level delegation so clicks work regardless of load timing
  document.addEventListener('click', (e) => {
    // 1. Preview button clicked
    const previewTrigger = e.target.closest('#pod-btn-preview, #pod-btn-quick-preview');
    if (previewTrigger) {
      e.preventDefault();
      e.stopPropagation();
      openPreviewModal();
      return;
    }

    // 2. Close button clicked
    const closeTrigger = e.target.closest('#pod-preview-modal-btn-close, #pod-preview-modal-btn-done');
    if (closeTrigger) {
      e.preventDefault();
      e.stopPropagation();
      closePreviewModal();
      return;
    }

    // 3. Click backdrop outside dialog
    const modal = document.getElementById('pod-preview-modal');
    if (modal && e.target === modal) {
      closePreviewModal();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const modal = document.getElementById('pod-preview-modal');
      if (modal && modal.style.display === 'flex') {
        closePreviewModal();
      }
    }
  });
}
