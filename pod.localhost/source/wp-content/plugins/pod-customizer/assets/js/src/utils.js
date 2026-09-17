/**
 * POD Customizer: Utility Functions
 */

import { state } from './state.js';

export function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

export function getLayersByType(type) {
  if (!state.canvas) return [];
  return state.canvas.getObjects().filter((obj) => obj.podType === type);
}

export function highlightActiveLayerItem(selected) {
  document.querySelectorAll('.pod-layer-item').forEach((item) => {
    if (selected && item.dataset.podId === selected.podId) {
      item.classList.add('active');
    } else {
      item.classList.remove('active');
    }
  });
}

export function bringDecorationsToFront() {
  if (state.mockupImg) state.mockupImg.sendToBack();
  const cliparts = getLayersByType('clipart');
  const photos = getLayersByType('photo');
  const texts = getLayersByType('text');

  cliparts.forEach((c) => state.canvas.bringForward(c));
  photos.forEach((p) => state.canvas.bringForward(p));
  texts.forEach((t) => state.canvas.bringToFront(t));
}

/**
 * Standard WordPress script translation resolver.
 * Priority: wp.i18n -> wp_localize_script (podCustomizerI18n) -> podCustomizerConfig.i18n -> fallback
 */
export function t(key, fallback = '') {
  if (window.podCustomizerI18n && window.podCustomizerI18n[key] !== undefined) {
    return window.podCustomizerI18n[key];
  }
  if (window.podCustomizerConfig && window.podCustomizerConfig.i18n && window.podCustomizerConfig.i18n[key] !== undefined) {
    return window.podCustomizerConfig.i18n[key];
  }
  if (window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function') {
    return window.wp.i18n.__(fallback || key, 'pod-customizer');
  }
  return fallback;
}

export function debounce(func, wait = 100) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

