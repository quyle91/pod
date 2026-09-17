/**
 * POD Customizer: Application Entry Point
 * Orchestrates modular components and bootstraps the interactive customizer.
 */

import { state, elements, config, fabric } from './state.js';
import { initCanvas, resetStudio, renderAllTemplateFields, updateTemplateField } from './canvas.js';
import { renderMockupGrid, loadMockupImage } from './mockup.js';
import { renderClipartGrid, addClipartLayer, initClipartEvents } from './clipart.js';
import { addTextLayer, initTextEvents } from './text.js';
import { initPhotoEvents } from './photo.js';
import { onAddToCartSubmit } from './cart.js';
import { initTemplateForm } from './form.js';
import { initPreviewModal } from './preview.js';

function init() {
  if (typeof window.podCustomizerConfig === 'undefined' || typeof window.fabric === 'undefined') {
    console.warn('POD Customizer: missing podCustomizerConfig or fabric.js.');
    return;
  }

  initCanvas();
  initPreviewModal();

  if (config.template) {
    initTemplateMode();
  } else {
    renderMockupGrid();
    renderClipartGrid();
    bindGlobalEvents();
    loadInitialLayers();
  }
}

function initTemplateMode() {
  const tpl = config.template;

  // 1. Load Template Mockup Base
  if (tpl.mockup && tpl.mockup.url) {
    loadMockupImage(tpl.mockup.url);
  }

  // 2. Initialize Dynamic Personalization Form
  initTemplateForm(tpl, state.templateValues, (fieldId, newValue) => {
    state.templateValues[fieldId] = newValue;
    updateTemplateField(fieldId, newValue);
  });

  // 3. Render Initial Template Elements after fonts are loaded
  const fontsPromise = document.fonts ? document.fonts.ready : Promise.resolve();
  fontsPromise.then(() => {
    renderAllTemplateFields();
    if (elements.loading) {
      elements.loading.style.opacity = '0';
      setTimeout(() => (elements.loading.style.display = 'none'), 300);
    }
  });

  // 4. Reset Button
  if (elements.btnReset) {
    elements.btnReset.addEventListener('click', () => {
      if (Array.isArray(tpl.fields)) {
        tpl.fields.forEach((f) => {
          state.templateValues[f.id] = f.default_value;
        });
        initTemplateForm(tpl, state.templateValues, (fieldId, newValue) => {
          state.templateValues[fieldId] = newValue;
          updateTemplateField(fieldId, newValue);
        });
        renderAllTemplateFields();
      }
    });
  }

  // 5. Intercept Add To Cart
  if (elements.addToCartForm) {
    elements.addToCartForm.addEventListener('submit', onAddToCartSubmit);
  }
}

function bindGlobalEvents() {
  // 1. Navigation Tabs
  const tabBtns = document.querySelectorAll('.pod-tab-btn');
  tabBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      tabBtns.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');

      const targetId = btn.dataset.tab;
      document.querySelectorAll('.pod-tab-pane').forEach((p) => p.classList.remove('active'));
      const targetPane = document.getElementById(targetId);
      if (targetPane) targetPane.classList.add('active');
    });
  });

  // 2. Initialize Module Event Handlers
  initTextEvents();
  initClipartEvents();
  initPhotoEvents();

  // 3. Reset Button
  if (elements.btnReset) {
    elements.btnReset.addEventListener('click', resetStudio);
  }

  // 4. Add to Cart Form Submission Interceptor
  if (elements.addToCartForm) {
    elements.addToCartForm.addEventListener('submit', onAddToCartSubmit);
  }
}

function loadInitialLayers() {
  if (elements.loading) elements.loading.style.display = 'flex';

  if (state.currentMockup) {
    loadMockupImage(state.currentMockup.url);
  }

  if (config.cliparts && config.cliparts[0]) {
    addClipartLayer(config.cliparts[0]);
  }

  addTextLayer('Best Dad Ever', false);

  setTimeout(() => {
    if (elements.loading) {
      elements.loading.style.opacity = '0';
      setTimeout(() => (elements.loading.style.display = 'none'), 300);
    }
  }, 400);
}

// Bootstrap when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
