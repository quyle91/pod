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

/**
 * ============================================================================
 * TEMPLATE-DRIVEN PERSONALIZATION ENGINE
 * ============================================================================
 */

export function getTemplateScale() {
  const designWidth = config.template?.print_spec?.width_px || 2400;
  return PREVIEW_SIZE / designWidth;
}

/**
 * Render all template fields based on current templateValues
 */
export function renderAllTemplateFields() {
  if (!config.template || !Array.isArray(config.template.fields)) return;

  config.template.fields.forEach((field) => {
    const val = state.templateValues ? state.templateValues[field.id] : undefined;
    updateTemplateField(field.id, val);
  });
}

/**
 * Update a single template field value and re-render its layer
 */
export function updateTemplateField(fieldId, value) {
  if (!state.canvas || !config.template) return;

  const field = (config.template.fields || []).find((f) => f.id === fieldId);
  if (!field) return;

  const scale = getTemplateScale();

  if (field.type === 'text') {
    renderTextSlot(field, value, scale);
  } else if (field.type === 'preset_picker') {
    renderPresetImageSlot(field, value, scale);
  } else if (field.type === 'repeater_counter') {
    renderRepeaterSlot(field, value, scale);
  }
}

/**
 * 1. Text Slot with Auto-Shrink Algorithm
 */
function renderTextSlot(field, value, scale) {
  const text = value !== undefined ? String(value) : (field.default_value || '');
  const x = (field.position.x || 1200) * scale;
  const y = (field.position.y || 1200) * scale;
  const initialFontSize = (field.style.font_size_px || 60) * scale;
  const minFontSize = (field.style.min_font_size_px || 18) * scale;
  const maxWidth = (field.style.max_width_px || 800) * scale;
  const fontFamily = field.style.font_family || 'Montserrat';
  const color = field.style.color || '#1e293b';
  const rotation = field.style.rotation || 0;
  const align = field.style.align || 'center';

  // Remove previous text object
  if (state.templateObjects[field.id]) {
    state.canvas.remove(state.templateObjects[field.id]);
    delete state.templateObjects[field.id];
  }

  if (!text.trim()) {
    if (state.templateMetrics) {
      state.templateMetrics[field.id] = { effectiveFontSize: initialFontSize, isShrunk: false };
    }
    state.canvas.renderAll();
    syncStateToForm();
    return;
  }

  const textObj = new fabric.Text(text, {
    left: x,
    top: y,
    fontFamily: fontFamily,
    fontSize: initialFontSize,
    fontWeight: field.style.font_weight || 'normal',
    fill: color,
    textAlign: align,
    originX: align === 'center' ? 'center' : (align === 'right' ? 'right' : 'left'),
    originY: 'middle',
    angle: rotation,
    selectable: false,
    evented: false,
    podType: 'text',
    podFieldId: field.id,
  });

  // Calculate Auto-Shrink
  const measuredWidth = textObj.width;
  let effectiveFontSize = initialFontSize;
  let isShrunk = false;

  if (measuredWidth > maxWidth && measuredWidth > 0) {
    const shrinkRatio = maxWidth / measuredWidth;
    effectiveFontSize = Math.max(minFontSize, Math.floor(initialFontSize * shrinkRatio));
    textObj.set('fontSize', effectiveFontSize);
    textObj.initDimensions();
    textObj.setCoords();
    isShrunk = true;
  }

  if (state.templateMetrics) {
    state.templateMetrics[field.id] = {
      effectiveFontSize: Math.round(effectiveFontSize / scale),
      isShrunk: isShrunk,
    };
  }

  state.templateObjects[field.id] = textObj;
  state.canvas.add(textObj);
  state.canvas.bringToFront(textObj);
  state.canvas.renderAll();
  syncStateToForm();
}

/**
 * Image Cache for instant repeater and preset rendering without re-fetching
 */
const templateImageCache = {};

/**
 * 2. Preset Icon Picker Slot
 */
function renderPresetImageSlot(field, value, scale) {
  const optionId = value || field.default_value || field.options?.[0]?.id;
  const option = (field.options || []).find((o) => o.id === optionId) || field.options?.[0];
  if (!option || !option.url) return;

  const x = (field.position.x || 1200) * scale;
  const y = (field.position.y || 1200) * scale;
  const targetW = (field.position.width_px || 100) * scale;
  const targetH = (field.position.height_px || 100) * scale;

  function applyPresetImage(imgElement) {
    if (state.templateObjects[field.id]) {
      state.canvas.remove(state.templateObjects[field.id]);
      delete state.templateObjects[field.id];
    }

    const svgObj = new fabric.Image(imgElement, {
      left: x,
      top: y,
      originX: 'center',
      originY: 'middle',
      selectable: false,
      evented: false,
      podType: 'clipart',
      clipartName: option.label || option.id,
      clipartUrl: option.url,
      podFieldId: field.id,
    });

    svgObj.scaleToWidth(targetW);
    if (svgObj.getScaledHeight() > targetH) {
      svgObj.scaleToHeight(targetH);
    }

    state.templateObjects[field.id] = svgObj;
    state.canvas.add(svgObj);
    state.canvas.bringToFront(svgObj);
    state.canvas.renderAll();
    syncStateToForm();
  }

  if (templateImageCache[option.url]) {
    applyPresetImage(templateImageCache[option.url]);
  } else {
    fabric.Image.fromURL(
      option.url,
      (img) => {
        if (!img) return;
        const elem = img.getElement();
        templateImageCache[option.url] = elem;
        applyPresetImage(elem);
      },
      { crossOrigin: 'anonymous' }
    );
  }
}

/**
 * 3. Dynamic Repeater Counter Slot (e.g. Birthday Candles)
 * Renders exactly `count` independent fabric.Image instances centered horizontally.
 */
function renderRepeaterSlot(field, value, scale) {
  const min = field.min !== undefined ? field.min : 1;
  const max = field.max !== undefined ? field.max : 20;
  let count = parseInt(value !== undefined ? value : (field.default_value || min), 10);
  if (isNaN(count)) count = min;
  count = Math.max(min, Math.min(max, count));

  const container = field.container_bounds || {};
  const centerX = (container.x || 1200) * scale;
  const centerY = (container.y || 400) * scale;
  const maxContainerW = (container.max_width_px || 700) * scale;
  const subImg = field.sub_image || {};
  let itemW = (subImg.width_px || 32) * scale;
  let itemH = (subImg.height_px || 64) * scale;
  let baseGap = (container.gap_px || 12) * scale;

  const url = subImg.url;
  if (!url) return;

  function buildRepeaterItems(imgElement) {
    // 1. Remove previous repeater items
    if (state.templateObjects[field.id]) {
      if (Array.isArray(state.templateObjects[field.id])) {
        state.templateObjects[field.id].forEach((obj) => state.canvas.remove(obj));
      } else {
        state.canvas.remove(state.templateObjects[field.id]);
      }
      state.templateObjects[field.id] = [];
    }

    // 2. Calculate dynamic responsive spacing & scaling
    let gap = baseGap;
    let totalW = count * itemW + (count - 1) * gap;

    if (totalW > maxContainerW && count > 1) {
      gap = (maxContainerW - count * itemW) / (count - 1);
      if (gap < 2) {
        const shrinkFactor = maxContainerW / (count * itemW + (count - 1) * 2);
        itemW *= shrinkFactor;
        itemH *= shrinkFactor;
        gap = 2;
        totalW = count * itemW + (count - 1) * gap;
      } else {
        totalW = count * itemW + (count - 1) * gap;
      }
    }

    const startX = centerX - totalW / 2 + itemW / 2;
    const createdObjs = [];

    // 3. Create independent fabric.Image for each item (no shared group mutation)
    for (let i = 0; i < count; i++) {
      const posX = startX + i * (itemW + gap);
      const itemObj = new fabric.Image(imgElement, {
        left: posX,
        top: centerY,
        originX: 'center',
        originY: 'middle',
        selectable: false,
        evented: false,
        podType: 'clipart',
        clipartName: `Repeater Item ${i + 1}`,
        clipartUrl: url,
        podFieldId: field.id,
      });
      itemObj.scaleToWidth(itemW);
      if (itemObj.getScaledHeight() > itemH) {
        itemObj.scaleToHeight(itemH);
      }
      state.canvas.add(itemObj);
      state.canvas.bringToFront(itemObj);
      createdObjs.push(itemObj);
    }

    state.templateObjects[field.id] = createdObjs;
    state.canvas.renderAll();
    syncStateToForm();
  }

  if (templateImageCache[url]) {
    buildRepeaterItems(templateImageCache[url]);
  } else {
    fabric.Image.fromURL(
      url,
      (img) => {
        if (!img) return;
        const elem = img.getElement();
        templateImageCache[url] = elem;
        buildRepeaterItems(elem);
      },
      { crossOrigin: 'anonymous' }
    );
  }
}

