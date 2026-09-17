/**
 * POD Customizer: Full Product Preview Modal
 * Generates crisp 2x resolution snapshot from Fabric Canvas and displays interactive popup.
 */

import { state, config, isTemplateMode } from './state.js';

export function openPreviewModal() {
  console.log('[POD Customizer] 🔍 openPreviewModal() invoked.');
  const modal = document.getElementById('pod-preview-modal');
  const img = document.getElementById('pod-preview-modal-img');
  const specs = document.getElementById('pod-preview-specs');

  console.log('[POD Customizer] Element status check:', {
    hasCanvas: !!state.canvas,
    hasModal: !!modal,
    hasImg: !!img,
    isTemplateMode: isTemplateMode,
  });

  if (!state.canvas || !modal) {
    console.error('[POD Customizer] ❌ Cannot open modal: canvas or modal element missing from DOM!');
    return;
  }

  // 1. Generate high-resolution render snapshot from canvas (1200 x 1200 px @ 2x)
  try {
    const dataUrl = state.canvas.toDataURL({
      format: 'png',
      multiplier: 2,
      quality: 1,
    });
    console.log('[POD Customizer] ✅ Canvas snapshot generated. Data length:', dataUrl.length);

    if (img) {
      img.src = dataUrl;
    }
  } catch (err) {
    console.error('[POD Customizer] ❌ Failed to export canvas snapshot:', err);
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

  // 3. Mount to document.body to break free from any theme stacking contexts or overflow:hidden
  if (modal.parentNode !== document.body) {
    document.body.appendChild(modal);
  }

  // 4. Display modal prominently
  modal.style.setProperty('display', 'flex', 'important');
  modal.style.setProperty('z-index', '9999999', 'important');
  modal.classList.add('is-open');
  document.body.style.overflow = 'hidden';
  console.log('[POD Customizer] 🚀 Preview modal displayed successfully!');
}

export function closePreviewModal() {
  console.log('[POD Customizer] 🔒 closePreviewModal() invoked.');
  const modal = document.getElementById('pod-preview-modal');
  if (!modal) return;
  modal.style.setProperty('display', 'none', 'important');
  modal.classList.remove('is-open');
  document.body.style.overflow = '';
}

// Global debug exposure for testing in devtools console
if (typeof window !== 'undefined') {
  window.podOpenPreview = openPreviewModal;
  window.podClosePreview = closePreviewModal;
}

export function initPreviewModal() {
  console.log('[POD Customizer] 🛠️ initPreviewModal() setting up listeners.');

  const checkElements = () => {
    const btn = document.getElementById('pod-btn-preview');
    const quickBtn = document.getElementById('pod-btn-quick-preview');
    const modal = document.getElementById('pod-preview-modal');
    console.log('[POD Customizer] Preview elements found in DOM:', {
      btnPreview: btn,
      btnQuickPreview: quickBtn,
      previewModal: modal,
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkElements);
  } else {
    checkElements();
  }

  // Document-level delegation for clicks
  document.addEventListener('click', (e) => {
    // 1. Preview button clicked
    const previewTrigger = e.target.closest('#pod-btn-preview, #pod-btn-quick-preview');
    if (previewTrigger) {
      console.log('[POD Customizer] 🖱️ Click detected on Preview Trigger:', previewTrigger.id, e.target);
      e.preventDefault();
      e.stopPropagation();
      openPreviewModal();
      return;
    }

    // 2. Close button clicked
    const closeTrigger = e.target.closest('#pod-preview-modal-btn-close, #pod-preview-modal-btn-done');
    if (closeTrigger) {
      console.log('[POD Customizer] 🖱️ Click detected on Close Button:', closeTrigger.id);
      e.preventDefault();
      e.stopPropagation();
      closePreviewModal();
      return;
    }

    // 3. Click backdrop outside dialog
    const modal = document.getElementById('pod-preview-modal');
    if (modal && e.target === modal) {
      console.log('[POD Customizer] 🖱️ Click detected on Modal Backdrop.');
      closePreviewModal();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const modal = document.getElementById('pod-preview-modal');
      if (modal && modal.style.display === 'flex') {
        console.log('[POD Customizer] ⌨️ Escape key pressed. Closing modal.');
        closePreviewModal();
      }
    }
  });
}
