/**
 * POD Customizer: Template-Driven Personalization Form Engine
 * Renders structured input controls matching template.fields and triggers live canvas updates.
 */

import { debounce } from './utils.js';

export function initTemplateForm(template, values, onFieldChange) {
  const container = document.getElementById('pod-template-form-fields');
  if (!container || !template || !Array.isArray(template.fields)) return;

  container.innerHTML = '';

  template.fields.forEach((field) => {
    const fieldGroup = document.createElement('div');
    fieldGroup.className = 'pod-form-group';
    fieldGroup.setAttribute('data-field-id', field.id);

    // 1. Label
    const label = document.createElement('label');
    label.className = 'pod-form-label';
    label.textContent = field.label || field.id;
    fieldGroup.appendChild(label);

    // 2. Render control by type
    if (field.type === 'text') {
      renderTextInput(fieldGroup, field, values[field.id], onFieldChange);
    } else if (field.type === 'preset_picker') {
      renderPresetPicker(fieldGroup, field, values[field.id], onFieldChange);
    } else if (field.type === 'repeater_counter') {
      renderRepeaterCounter(fieldGroup, field, values[field.id], onFieldChange);
    } else if (field.type === 'layer_selector') {
      renderLayerSelector(fieldGroup, field, values[field.id], onFieldChange);
    }

    container.appendChild(fieldGroup);
  });
}

/**
 * Render Text Input with Auto-Shrink feedback
 */
function renderTextInput(parent, field, currentValue, onFieldChange) {
  const wrapper = document.createElement('div');
  wrapper.className = 'pod-input-wrapper';

  const input = document.createElement('input');
  input.type = 'text';
  input.className = 'pod-form-input';
  input.id = `pod-field-${field.id}`;
  input.placeholder = field.placeholder || '';
  input.value = currentValue !== undefined ? currentValue : (field.default_value || '');

  const debouncedChange = debounce((val) => {
    onFieldChange(field.id, val);
  }, 60);

  input.addEventListener('input', (e) => {
    debouncedChange(e.target.value);
  });

  wrapper.appendChild(input);
  parent.appendChild(wrapper);
}

/**
 * Render Curated Preset Icon Swatch Grid
 */
function renderPresetPicker(parent, field, currentValue, onFieldChange) {
  const grid = document.createElement('div');
  grid.className = 'pod-preset-grid';

  const activeId = currentValue || field.default_value || field.options?.[0]?.id;

  (field.options || []).forEach((opt) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `pod-preset-btn ${opt.id === activeId ? 'active' : ''}`;
    btn.setAttribute('data-option-id', opt.id);
    btn.title = opt.label || opt.id;

    btn.innerHTML = `
      <div class="pod-preset-thumb">
        <img src="${opt.url}" alt="${opt.label || opt.id}" />
      </div>
      <span class="pod-preset-label">${opt.label || opt.id}</span>
    `;

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      // Remove active from sibling buttons
      grid.querySelectorAll('.pod-preset-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      onFieldChange(field.id, opt.id);
    });

    grid.appendChild(btn);
  });

  parent.appendChild(grid);
}

/**
 * Render Number Stepper Counter for Dynamic Repeater
 */
function renderRepeaterCounter(parent, field, currentValue, onFieldChange) {
  const min = field.min !== undefined ? field.min : 1;
  const max = field.max !== undefined ? field.max : 20;
  let val = parseInt(currentValue !== undefined ? currentValue : (field.default_value || min), 10);
  if (isNaN(val)) val = min;

  const stepper = document.createElement('div');
  stepper.className = 'pod-stepper-control';

  const btnMinus = document.createElement('button');
  btnMinus.type = 'button';
  btnMinus.className = 'pod-stepper-btn minus';
  btnMinus.textContent = '−';

  const input = document.createElement('input');
  input.type = 'number';
  input.className = 'pod-stepper-input';
  input.value = val;
  input.min = min;
  input.max = max;
  input.readOnly = true;

  const btnPlus = document.createElement('button');
  btnPlus.type = 'button';
  btnPlus.className = 'pod-stepper-btn plus';
  btnPlus.textContent = '+';

  const helperText = document.createElement('span');
  helperText.className = 'pod-stepper-hint';
  helperText.textContent = `${field.sub_image ? 'Items' : 'Count'}: min ${min}, max ${max}`;

  function updateValue(newVal) {
    if (newVal < min) newVal = min;
    if (newVal > max) newVal = max;
    val = newVal;
    input.value = val;
    btnMinus.disabled = val <= min;
    btnPlus.disabled = val >= max;
    onFieldChange(field.id, val);
  }

  btnMinus.addEventListener('click', (e) => {
    e.preventDefault();
    updateValue(val - 1);
  });

  btnPlus.addEventListener('click', (e) => {
    e.preventDefault();
    updateValue(val + 1);
  });

  stepper.appendChild(btnMinus);
  stepper.appendChild(input);
  stepper.appendChild(btnPlus);
  stepper.appendChild(helperText);

  btnMinus.disabled = val <= min;
  btnPlus.disabled = val >= max;

  parent.appendChild(stepper);
}

/**
 * Render Layer Selector Swatches (100% Layer-driven for skin tones, hairstyle, outfits)
 */
function renderLayerSelector(parent, field, currentValue, onFieldChange) {
  const grid = document.createElement('div');
  grid.className = 'pod-preset-grid pod-layer-selector-grid';

  const activeId = currentValue || field.default_value || field.options?.[0]?.layer_id || field.options?.[0]?.id;

  (field.options || []).forEach((opt) => {
    const optValue = opt.layer_id || opt.id;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `pod-preset-btn ${optValue === activeId || opt.id === activeId ? 'active' : ''}`;
    btn.setAttribute('data-option-id', optValue);
    btn.title = opt.label || opt.id;

    const thumbUrl = opt.thumbnail_url || opt.url || '';

    btn.innerHTML = `
      <div class="pod-preset-thumb">
        ${thumbUrl ? `<img src="${thumbUrl}" alt="${opt.label || opt.id}" />` : `<span style="font-size: 11px; font-weight: 600;">${opt.label || opt.id}</span>`}
      </div>
      <span class="pod-preset-label">${opt.label || opt.id}</span>
    `;

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      grid.querySelectorAll('.pod-preset-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      onFieldChange(field.id, optValue);
    });

    grid.appendChild(btn);
  });

  parent.appendChild(grid);
}

