/**
 * POD Customizer: Shared Runtime State & DOM References
 */

export const config = window.podCustomizerConfig || {};
export const fabric = window.fabric;

export const PREVIEW_SIZE = 600;
export const RENDER_SIZE = config.canvas?.width || 1200;
export const SCALE_RATIO = RENDER_SIZE / PREVIEW_SIZE; // e.g. 2.0

export const state = {
  canvas: null,
  mockupImg: null,
  currentMockup: config.mockups?.[0] || null,
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

  // Form & Reset
  btnReset: document.getElementById('pod-btn-reset'),
  stateInput: document.getElementById('pod_canvas_state'),
  previewInput: document.getElementById('pod_preview_image'),
  addToCartForm: document.querySelector('form.cart'),
};
