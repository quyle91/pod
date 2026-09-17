/**
 * POD Customizer: Canvas State Serializer
 * Produces strict CanvasLayer[] JSON data contract for WooCommerce and backend render engine.
 */

import { state, elements, PREVIEW_SIZE, RENDER_SIZE, SCALE_RATIO } from './state.js';

export function serializeCanvasState() {
  const layers = [];

  // 1. Mockup Background Layer (non-printable)
  if (state.mockupImg) {
    layers.push({
      id: 'mockup_base',
      type: 'image',
      name: state.currentMockup ? state.currentMockup.name : 'Product Base',
      url: state.currentMockup ? state.currentMockup.url : '',
      x: Math.round(state.mockupImg.left * SCALE_RATIO),
      y: Math.round(state.mockupImg.top * SCALE_RATIO),
      width: Math.round(state.mockupImg.getScaledWidth() * SCALE_RATIO),
      height: Math.round(state.mockupImg.getScaledHeight() * SCALE_RATIO),
      rotation: Math.round(state.mockupImg.angle || 0),
      zIndex: 1,
      printable: false,
    });
  }

  // 2. Iterate canvas objects in actual z-index stacking order
  if (state.canvas) {
    const objects = state.canvas.getObjects();
    let zIdx = 2;

    objects.forEach((obj) => {
      if (obj === state.mockupImg) return;

      if (obj.podType === 'clipart') {
        layers.push({
          id: obj.podId || `clipart_${zIdx}`,
          type: 'image',
          name: obj.clipartName || 'Clipart',
          url: obj.clipartUrl || '',
          x: Math.round(obj.left * SCALE_RATIO),
          y: Math.round(obj.top * SCALE_RATIO),
          width: Math.round(obj.getScaledWidth() * SCALE_RATIO),
          height: Math.round(obj.getScaledHeight() * SCALE_RATIO),
          rotation: Math.round(obj.angle || 0),
          zIndex: zIdx++,
          printable: true,
        });
      } else if (obj.podType === 'photo') {
        layers.push({
          id: obj.podId || `photo_${zIdx}`,
          type: 'image',
          name: obj.photoName || 'User Photo',
          url: obj.toDataURL ? obj.toDataURL({ format: 'png' }) : '',
          x: Math.round(obj.left * SCALE_RATIO),
          y: Math.round(obj.top * SCALE_RATIO),
          width: Math.round(obj.getScaledWidth() * SCALE_RATIO),
          height: Math.round(obj.getScaledHeight() * SCALE_RATIO),
          rotation: Math.round(obj.angle || 0),
          zIndex: zIdx++,
          printable: true,
        });
      } else if (obj.podType === 'text') {
        layers.push({
          id: obj.podId || `text_${zIdx}`,
          type: 'text',
          name: obj.text || 'Custom Text',
          text: obj.text || '',
          fontFamily: obj.fontFamily || 'Roboto',
          fontSize: Math.round((obj.fontSize || 44) * (obj.scaleY || 1) * SCALE_RATIO),
          fill: obj.fill || '#111827',
          textAlign: obj.textAlign || 'center',
          x: Math.round(obj.left * SCALE_RATIO),
          y: Math.round(obj.top * SCALE_RATIO),
          width: Math.round(obj.getScaledWidth() * SCALE_RATIO),
          height: Math.round(obj.getScaledHeight() * SCALE_RATIO),
          rotation: Math.round(obj.angle || 0),
          zIndex: zIdx++,
          printable: true,
        });
      }
    });
  }

  return {
    version: '1.0',
    canvas: {
      width: RENDER_SIZE,
      height: RENDER_SIZE,
      dpi: 300,
      unit: 'px',
    },
    preview: {
      width: PREVIEW_SIZE,
      height: PREVIEW_SIZE,
    },
    layers: layers,
  };
}

export function syncStateToForm() {
  if (!elements.stateInput || !state.canvas) return;

  const payload = serializeCanvasState();
  elements.stateInput.value = JSON.stringify(payload);

  // Generate thumbnail preview image for cart item
  if (elements.previewInput) {
    try {
      const thumbUrl = state.canvas.toDataURL({
        format: 'jpeg',
        quality: 0.7,
        multiplier: 0.5,
      });
      elements.previewInput.value = thumbUrl;
    } catch (err) {
      // Ignore cross-origin error in local preview if any
    }
  }
}
