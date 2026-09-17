/**
 * POD Customizer: WooCommerce Cart Form Interceptor & Validation
 */

import { state, elements } from './state.js';
import { getLayersByType, t } from './utils.js';
import { syncStateToForm } from './serializer.js';

export function onAddToCartSubmit(e) {
  if (!elements.stateInput || !state.canvas) return;

  syncStateToForm();

  const printableLayers = state.canvas.getObjects().filter((obj) => obj.podType && obj.podType !== 'mockup');

  if (printableLayers.length === 0) {
    e.preventDefault();
    alert(t('emptyDesignAlert', 'Please add at least one customization (text, clipart, or photo) to your design.'));
    return false;
  }

  // If text layers exist, ensure at least one has non-empty text
  const textLayers = getLayersByType('text');
  if (textLayers.length > 0) {
    const hasValidText = textLayers.some((t) => t.text && t.text.trim().length > 0);
    if (!hasValidText) {
      e.preventDefault();
      alert(t('emptyTextAlert', 'Please enter custom text for your design.'));
      return false;
    }
  }

  if (!elements.stateInput.value) {
    e.preventDefault();
    alert(t('saveStateFailed', 'Could not save custom design state. Please try again.'));
    return false;
  }
}
