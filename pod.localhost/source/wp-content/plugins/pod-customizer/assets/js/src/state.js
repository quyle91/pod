/**
 * POD Customizer: Shared Runtime State & DOM References
 */

export const config = window.podCustomizerConfig || {};
export const fabric = window.fabric;

// Patch Fabric.js 5.3.0 CanvasTextBaseline invalid enum 'alphabetical' -> 'alphabetic'
if (fabric) {
  if (fabric.Text) fabric.Text.prototype.textBaseline = 'alphabetic';
  if (fabric.IText) fabric.IText.prototype.textBaseline = 'alphabetic';
  if (fabric.Textbox) fabric.Textbox.prototype.textBaseline = 'alphabetic';
}

export const template = config.template || null;
export const isTemplateMode = !!template;
export const templateValues = {};
export const templateMetrics = {};

// Initialize default template values
if (template && Array.isArray(template.fields)) {
  template.fields.forEach((f) => {
    templateValues[f.id] = f.default_value;
  });
}

export const PREVIEW_SIZE = 600;
export const RENDER_SIZE = template?.print_spec?.width_px || config.canvas?.width || 1200;
export const SCALE_RATIO = RENDER_SIZE / PREVIEW_SIZE; // e.g. 4.0

export const state = {
  canvas: null,
  mockupImg: null,
  currentMockup: template?.mockup || config.mockups?.[0] || null,
  templateValues: templateValues,
  templateMetrics: templateMetrics,
  templateObjects: {}, // Map of field_id -> fabric object or array of objects
  editingTextObj: null,
  editingClipartObj: null,
  editingPhotoObj: null,
};

// DOM Elements
export const elements = {
  loading: document.getElementById('pod-canvas-loading'),
  mockupsGrid: document.getElementById('pod-mockups-grid'),
  clipartGrid: document.getElementById('pod-clipart-grid'),
  btnAddText: document.getElementById('pod-btn-add-text'),
  textList: document.getElementById('pod-text-list'),
  btnAddClipart: document.getElementById('pod-btn-add-clipart'),
  clipartList: document.getElementById('pod-clipart-list'),
  btnAddPhoto: document.getElementById('pod-btn-add-photo'),
  photoList: document.getElementById('pod-photo-list'),

  // Text Modal
  textModal: document.getElementById('pod-text-modal'),
  textModalTitle: document.getElementById('pod-modal-title'),
  textModalBtnClose: document.getElementById('pod-modal-btn-close'),
  textModalBtnDone: document.getElementById('pod-modal-btn-done'),
  modalInputText: document.getElementById('pod-modal-input-text'),
  modalSelectFont: document.getElementById('pod-modal-select-font'),
  modalRangeSize: document.getElementById('pod-modal-range-size'),
  modalSizeVal: document.getElementById('pod-modal-size-val'),
  modalCustomColor: document.getElementById('pod-modal-custom-color'),
  modalColorsContainer: document.getElementById('pod-modal-text-colors'),

  // Clipart Modal
  clipartModal: document.getElementById('pod-clipart-modal'),
  clipartModalTitle: document.getElementById('pod-clipart-modal-title'),
  clipartModalBtnClose: document.getElementById('pod-clipart-modal-btn-close'),

  // Photo Modal
  photoModal: document.getElementById('pod-photo-modal'),
  photoModalTitle: document.getElementById('pod-photo-modal-title'),
  photoModalBtnClose: document.getElementById('pod-photo-modal-btn-close'),
  fileInput: document.getElementById('pod-file-input'),
  dropzone: document.getElementById('pod-upload-dropzone'),

  // Preview Modal & Buttons
  btnPreview: document.getElementById('pod-btn-preview'),
  btnQuickPreview: document.getElementById('pod-btn-quick-preview'),
  previewModal: document.getElementById('pod-preview-modal'),
  previewModalImg: document.getElementById('pod-preview-modal-img'),
  previewModalSpecs: document.getElementById('pod-preview-specs'),
  previewModalBtnClose: document.getElementById('pod-preview-modal-btn-close'),
  previewModalBtnDone: document.getElementById('pod-preview-modal-btn-done'),

  // Form & Reset
  btnReset: document.getElementById('pod-btn-reset'),
  stateInput: document.getElementById('pod_canvas_state'),
  previewInput: document.getElementById('pod_preview_image'),
  addToCartForm: document.querySelector('form.cart'),
};
