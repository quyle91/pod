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
/**
 * Render all template fields based on current templateValues
 */
export function renderAllTemplateFields() {
  if (!config.template) return;
  const scale = getTemplateScale();

  // 1. Render Fixed PSD Layers (Background, Decorative Frames)
  renderFixedTemplateLayers(scale);

  // 2. Render Interactive Fields
  if (Array.isArray(config.template.fields)) {
    config.template.fields.forEach((field) => {
      const val = state.templateValues ? state.templateValues[field.id] : undefined;
      updateTemplateField(field.id, val);
    });
  }
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
  } else if (field.type === 'layer_selector') {
    renderLayerSelectorSlot(field, value, scale);
  }
}

/**
 * 1. Text Slot with Auto-Shrink Algorithm (supports Spec 008 position & Spec 009 target_layer)
 */
function renderTextSlot(field, value, scale) {
  const targetLayer = (config.template.layers || []).find((l) => l.id === field.target_layer || l.id === field.id);
  const text = value !== undefined ? String(value) : (field.default_value || targetLayer?.default_value || '');

  // Safe position resolution
  const rawX = field.position?.x !== undefined ? field.position.x : (targetLayer ? (targetLayer.x + (targetLayer.width || 0) / 2) : 1200);
  const rawY = field.position?.y !== undefined ? field.position.y : (targetLayer ? (targetLayer.y + (targetLayer.height || 0) / 2) : 1200);
  const x = rawX * scale;
  const y = rawY * scale;

  const style = field.style || {};
  const initialFontSize = (style.font_size_px || targetLayer?.font_size_pt || 48) * scale;
  const minFontSize = (style.min_font_size_px || targetLayer?.behavior?.min_font_size_pt || 18) * scale;
  const maxWidth = (style.max_width_px || targetLayer?.width || 800) * scale;
  const fontFamily = style.font_family || targetLayer?.font_family || 'Montserrat';
  const color = style.color || targetLayer?.color || '#1e293b';
  const rotation = style.rotation !== undefined ? style.rotation : (targetLayer?.rotation || 0);
  const align = style.align || targetLayer?.text_align || 'center';
  const fontWeight = style.font_weight || 'normal';

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
    fontWeight: fontWeight,
    fill: color,
    textAlign: align,
    originX: align === 'center' ? 'center' : (align === 'right' ? 'right' : 'left'),
    originY: 'middle',
    angle: rotation,
    selectable: false,
    evented: false,
    podType: 'text',
    podFieldId: field.id,
    podZIndex: targetLayer?.z_index || 20,
    textBaseline: 'alphabetic',
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
 * 2. Preset Icon Picker Slot (supports Spec 008 options & Spec 009 target_layer placeholder)
 */
function renderPresetImageSlot(field, value, scale) {
  const targetLayer = (config.template.layers || []).find((l) => l.id === field.target_layer || l.id === field.id);
  const optionId = value || field.default_value || field.options?.[0]?.id;
  const option = (field.options || []).find((o) => o.id === optionId) || field.options?.[0];

  const imgUrl = option?.url || option?.thumbnail_url || targetLayer?.placeholder_url;
  if (!imgUrl) return;

  const rawX = field.position?.x !== undefined ? field.position.x : (targetLayer ? (targetLayer.x + (targetLayer.width || 0) / 2) : 1200);
  const rawY = field.position?.y !== undefined ? field.position.y : (targetLayer ? (targetLayer.y + (targetLayer.height || 0) / 2) : 1200);
  const rawW = field.position?.width_px !== undefined ? field.position.width_px : (targetLayer?.width || 200);
  const rawH = field.position?.height_px !== undefined ? field.position.height_px : (targetLayer?.height || 200);

  const x = rawX * scale;
  const y = rawY * scale;
  const targetW = rawW * scale;
  const targetH = rawH * scale;

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
      clipartName: option?.label || option?.id || 'Icon',
      clipartUrl: imgUrl,
      podFieldId: field.id,
      podZIndex: targetLayer?.z_index || 15,
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

  if (templateImageCache[imgUrl]) {
    applyPresetImage(templateImageCache[imgUrl]);
  } else {
    fabric.Image.fromURL(
      imgUrl,
      (img) => {
        if (!img) return;
        const elem = img.getElement();
        templateImageCache[imgUrl] = elem;
        applyPresetImage(elem);
      },
      { crossOrigin: 'anonymous' }
    );
  }
}

/**
 * 3. Layer Selector Slot (100% Layer-driven for skin tone, hair style, etc.)
 */
function renderLayerSelectorSlot(field, value, scale) {
  const selectedId = value || field.default_value || field.options?.[0]?.layer_id || field.options?.[0]?.id;
  const opt = (field.options || []).find((o) => o.id === selectedId || o.layer_id === selectedId);
  const targetLayerId = opt ? (opt.layer_id || opt.id) : selectedId;

  const groupLayers = (config.template.layers || []).filter((l) => l.group_id === field.id);

  groupLayers.forEach((layer) => {
    const isChosen = (layer.id === targetLayerId || layer.id === selectedId);
    let fabricObj = state.templateObjects[layer.id];

    if (!fabricObj && layer.url) {
      fabric.Image.fromURL(
        layer.url,
        (img) => {
          if (!img) return;
          img.set({
            left: (layer.x || 0) * scale,
            top: (layer.y || 0) * scale,
            scaleX: ((layer.width || img.width) * scale) / img.width,
            scaleY: ((layer.height || img.height) * scale) / img.height,
            angle: layer.rotation || 0,
            selectable: false,
            evented: false,
            visible: isChosen,
            podType: 'template_layer',
            podLayerId: layer.id,
            podLayerName: layer.name,
            podLayerUrl: layer.url,
            podZIndex: layer.z_index || 5,
          });
          state.templateObjects[layer.id] = img;
          state.canvas.add(img);
          sortTemplateObjectsZIndex();
          state.canvas.renderAll();
        },
        { crossOrigin: 'anonymous' }
      );
    } else if (fabricObj) {
      fabricObj.set('visible', isChosen);
      state.canvas.renderAll();
    }
  });

  syncStateToForm();
}

/**
 * Render fixed graphic layers (e.g. [fixed] Background Frame)
 */
function renderFixedTemplateLayers(scale) {
  if (!config.template || !Array.isArray(config.template.layers)) return;

  config.template.layers.forEach((layer) => {
    if (layer.type === 'fixed_image' && layer.url) {
      if (state.templateObjects[layer.id]) return;

      fabric.Image.fromURL(
        layer.url,
        (img) => {
          if (!img) return;
          img.set({
            left: (layer.x || 0) * scale,
            top: (layer.y || 0) * scale,
            scaleX: ((layer.width || img.width) * scale) / img.width,
            scaleY: ((layer.height || img.height) * scale) / img.height,
            angle: layer.rotation || 0,
            selectable: false,
            evented: false,
            visible: true,
            podType: 'template_fixed',
            podLayerId: layer.id,
            podLayerName: layer.name,
            podLayerUrl: layer.url,
            podZIndex: layer.z_index || 1,
          });
          state.templateObjects[layer.id] = img;
          state.canvas.add(img);
          sortTemplateObjectsZIndex();
          state.canvas.renderAll();
        },
        { crossOrigin: 'anonymous' }
      );
    }
  });
}

function sortTemplateObjectsZIndex() {
  if (!state.canvas) return;
  const objects = state.canvas.getObjects().slice();
  objects.sort((a, b) => (a.podZIndex || 10) - (b.podZIndex || 10));
  objects.forEach((obj, idx) => {
    state.canvas.moveTo(obj, idx);
  });
}

/**
 * 3. Dynamic Repeater Counter Slot (e.g. Birthday Candles, Family Icons, Badges)
 * Supports:
 * - "horizontal_center": 1-row centered layout with dynamic gap & shrink
 * - "grid" or "rectangle": Multi-row rectangular grid with auto-wrapping, row-balancing & bounds packing
 */
function renderRepeaterSlot(field, value, scale) {
  const min = field.min !== undefined ? field.min : 1;
  const max = field.max !== undefined ? field.max : 50;
  let count = parseInt(value !== undefined ? value : (field.default_value || min), 10);
  if (isNaN(count)) count = min;
  count = Math.max(min, Math.min(max, count));

  const container = field.container_bounds || {};
  const isGrid = container.distribution === 'grid' || container.distribution === 'rectangle' || !!container.cols;
  const centerX = (container.x || 1200) * scale;
  const centerY = (container.y || 1100) * scale;
  const maxContainerW = (container.max_width_px || container.width_px || 700) * scale;
  const maxContainerH = (container.max_height_px || container.height_px || 400) * scale;
  const subImg = field.sub_image || {};
  let itemW = (subImg.width_px || 32) * scale;
  let itemH = (subImg.height_px || 64) * scale;
  let gapX = (container.gap_x_px || container.gap_px || 12) * scale;
  let gapY = (container.gap_y_px || container.gap_px || 12) * scale;

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

    const createdObjs = [];

    if (isGrid) {
      // MULTI-ROW GRID / RECTANGLE DISTRIBUTION
      const maxCols = container.cols || container.max_per_row || 6;
      const numRows = Math.ceil(count / maxCols);
      // Auto-balance columns across rows (e.g. 7 items with maxCols 6 -> 4 on row 0, 3 on row 1)
      const effectiveCols = Math.ceil(count / numRows);

      // Auto-scale if height or width exceeds rectangle bounds
      let totalGridH = numRows * itemH + (numRows - 1) * gapY;
      let maxRowItems = Math.min(effectiveCols, count);
      let totalGridW = maxRowItems * itemW + (maxRowItems - 1) * gapX;

      let scaleFactor = 1;
      if (totalGridW > maxContainerW) {
        scaleFactor = Math.min(scaleFactor, maxContainerW / totalGridW);
      }
      if (totalGridH > maxContainerH) {
        scaleFactor = Math.min(scaleFactor, maxContainerH / totalGridH);
      }

      if (scaleFactor < 1) {
        itemW *= scaleFactor;
        itemH *= scaleFactor;
        gapX *= scaleFactor;
        gapY *= scaleFactor;
        totalGridH = numRows * itemH + (numRows - 1) * gapY;
      }

      const gridStartY = centerY - totalGridH / 2 + itemH / 2;
      let itemIdx = 0;

      for (let r = 0; r < numRows; r++) {
        const itemsInThisRow = Math.min(effectiveCols, count - itemIdx);
        const rowW = itemsInThisRow * itemW + (itemsInThisRow - 1) * gapX;
        const rowStartX = centerX - rowW / 2 + itemW / 2;
        const rowY = gridStartY + r * (itemH + gapY);

        for (let c = 0; c < itemsInThisRow; c++) {
          const posX = rowStartX + c * (itemW + gapX);
          const itemObj = new fabric.Image(imgElement, {
            left: posX,
            top: rowY,
            originX: 'center',
            originY: 'middle',
            selectable: false,
            evented: false,
            podType: 'clipart',
            clipartName: `Repeater Item ${itemIdx + 1}`,
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
          itemIdx++;
        }
      }
    } else {
      // SINGLE-ROW HORIZONTAL CENTER DISTRIBUTION
      let gap = gapX;
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

